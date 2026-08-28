<?php
/**
 * The narrow policy used by the reference read-only Ability.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package AgenticWebGovernanceReference
 */

declare(strict_types=1);

namespace AtShift\AgenticWebGovernance\WordPressReference;

final class ReferenceReadPolicy {
	private TransientRateLimiter $rate_limiter;

	/**
	 * @var array<string, array{limit: int, window: int}>
	 */
	private array $limits;

	/**
	 * @param array<string, array{limit: int, window: int}> $limits
	 */
	public function __construct( TransientRateLimiter $rate_limiter, array $limits ) {
		$this->rate_limiter = $rate_limiter;
		$this->limits       = $limits;
	}

	/**
	 * @return array{decision: string, reason_codes: list<string>, policy_id: string, policy_version: string}
	 */
	public function __invoke( string $ability_name, $input, array $context ): array {
		if ( ! isset( $this->limits[ $ability_name ] ) ) {
			return $this->deny( 'ability_not_enabled' );
		}

		// Read-only does not mean public; an authenticated WordPress principal is required.
		$principal = $context['application_principal'] ?? null;
		if ( ! is_string( $principal ) || '' === $principal ) {
			return $this->deny( 'authenticated_principal_required' );
		}

		$configuration = $this->limits[ $ability_name ];
		$reservation   = $this->rate_limiter->reserve(
			$principal . '|' . $ability_name,
			$configuration['limit'],
			$configuration['window']
		);

		if ( true !== $reservation['allowed'] ) {
			return $this->deny( $reservation['reason_code'] );
		}

		return array(
			'decision'       => 'allow',
			'reason_codes'   => array( $reservation['reason_code'] ),
			'policy_id'      => 'wordpress-reference-read',
			'policy_version' => '1',
		);
	}

	/**
	 * @return array{decision: string, reason_codes: list<string>, policy_id: string, policy_version: string}
	 */
	private function deny( string $reason_code ): array {
		return array(
			'decision'       => 'deny',
			'reason_codes'   => array( $reason_code ),
			'policy_id'      => 'wordpress-reference-read',
			'policy_version' => '1',
		);
	}
}
