<?php
/**
 * A conservative fixed-window rate limiter for the reference plugin.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package AgenticWebGovernanceReference
 */

declare(strict_types=1);

namespace AtShift\AgenticWebGovernance\WordPressReference;

use Throwable;

final class TransientRateLimiter {
	private const KEY_PREFIX = 'awg_ref_rate_';

	private const LOCK_PREFIX = 'awg_ref_rate_lock_';

	/**
	 * Reserves one invocation in a fixed window.
	 *
	 * Storage corruption, contention, and write failures deny the reservation.
	 *
	 * @return array{allowed: bool, reason_code: string, remaining?: int}
	 */
	public function reserve( string $bucket, int $limit, int $window_seconds ): array {
		if ( '' === $bucket || $limit < 1 || $window_seconds < 1 ) {
			return $this->denied( 'rate_limit_configuration_invalid' );
		}

		$digest        = substr( hash( 'sha256', $bucket ), 0, 40 );
		$transient_key = self::KEY_PREFIX . $digest;
		$mutex         = new OptionMutex( self::LOCK_PREFIX . $digest );

		try {
			return $mutex->synchronized(
				static function () use ( $transient_key, $limit, $window_seconds ): array {
					$now   = time();
					$state = get_transient( $transient_key );

					if ( false === $state ) {
						$state = array(
							'count'      => 0,
							'expires_at' => $now + $window_seconds,
						);
					} elseif (
						! is_array( $state ) ||
						! isset( $state['count'], $state['expires_at'] ) ||
						! is_int( $state['count'] ) ||
						! is_int( $state['expires_at'] ) ||
						$state['count'] < 0
					) {
						// Unknown state could undercount prior calls, so it must fail closed.
						return array(
							'allowed'     => false,
							'reason_code' => 'rate_limit_storage_invalid',
						);
					}

					if ( $state['expires_at'] <= $now ) {
						$state = array(
							'count'      => 0,
							'expires_at' => $now + $window_seconds,
						);
					}

					if ( $state['count'] >= $limit ) {
						return array(
							'allowed'     => false,
							'reason_code' => 'rate_limit_exceeded',
							'remaining'   => 0,
						);
					}

					++$state['count'];
					$ttl = max( 1, $state['expires_at'] - $now );

					if ( ! set_transient( $transient_key, $state, $ttl ) ) {
						// An allow is valid only after its budget reservation is durable.
						return array(
							'allowed'     => false,
							'reason_code' => 'rate_limit_storage_unavailable',
						);
					}

					return array(
						'allowed'     => true,
						'reason_code' => 'rate_limit_reserved',
						'remaining'   => $limit - $state['count'],
					);
				}
			);
		} catch ( Throwable $throwable ) {
			return $this->denied( 'rate_limit_storage_unavailable' );
		}
	}

	/**
	 * @return array{allowed: false, reason_code: string}
	 */
	private function denied( string $reason_code ): array {
		return array(
			'allowed'     => false,
			'reason_code' => $reason_code,
		);
	}
}
