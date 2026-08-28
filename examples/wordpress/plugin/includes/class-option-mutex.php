<?php
/**
 * A small database-backed mutex for the reference adapters.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package AgenticWebGovernanceReference
 */

declare(strict_types=1);

namespace AtShift\AgenticWebGovernance\WordPressReference;

use RuntimeException;

final class OptionMutex {
	private string $option_name;

	private int $ttl;

	private string $owner_token;

	public function __construct( string $option_name, int $ttl = 5 ) {
		$this->option_name = $option_name;
		$this->ttl         = max( 1, $ttl );
		$this->owner_token = bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Runs a callback while holding an option-backed lock.
	 *
	 * @return mixed
	 */
	public function synchronized( callable $callback ) {
		if ( ! $this->acquire() ) {
			throw new RuntimeException( 'The governance storage lock is unavailable.' );
		}

		try {
			return $callback();
		} finally {
			$this->release();
		}
	}

	private function acquire(): bool {
		$now        = time();
		$lock_value = array(
			'owner_token' => $this->owner_token,
			'expires_at'  => $now + $this->ttl,
		);

		// add_option() relies on the unique option name, so only one request wins.
		if ( add_option( $this->option_name, $lock_value, '', false ) ) {
			return true;
		}

		$current        = get_option( $this->option_name, null );
		$current_expiry = is_array( $current ) ? ( $current['expires_at'] ?? null ) : $current;
		// Scalar values support recovery from the initial reference lock format.
		if ( is_string( $current_expiry ) && ctype_digit( $current_expiry ) ) {
			$current_expiry = (int) $current_expiry;
		}

		if ( ! is_int( $current_expiry ) || $current_expiry > $now ) {
			return false;
		}

		// A crashed request must not leave governance permanently unavailable.
		delete_option( $this->option_name );

		return add_option( $this->option_name, $lock_value, '', false );
	}

	private function release(): void {
		$current = get_option( $this->option_name, null );
		if (
			! is_array( $current ) ||
			! isset( $current['owner_token'] ) ||
			! is_string( $current['owner_token'] ) ||
			! hash_equals( $this->owner_token, $current['owner_token'] )
		) {
			return;
		}

		// A stale owner must never release a lock acquired by a newer request.
		delete_option( $this->option_name );
	}
}
