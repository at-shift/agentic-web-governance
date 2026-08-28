<?php
/**
 * WordPress Abilities governance integration.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package AgenticWebGovernanceReference
 */

declare(strict_types=1);

namespace AtShift\AgenticWebGovernance\WordPressReference;

use InvalidArgumentException;
use Throwable;

final class AbilityGovernanceGate {
	private const SCHEMA_VERSION = '0.1';

	private const RETENTION_CLASS = 'operational';

	/** @var array<string, true> */
	private array $governed_abilities;

	/** @var callable */
	private $policy;

	/** @var callable */
	private $evidence_sink;

	/** @var callable */
	private $context_provider;

	/** @var array<string, true> */
	private array $allow_cache = array();

	/** @var array<string, array<string, true>> */
	private array $cache_keys_by_ability = array();

	public function __construct(
		array $governed_abilities,
		callable $policy,
		callable $evidence_sink,
		callable $context_provider
	) {
		$this->governed_abilities = array_fill_keys( array_values( $governed_abilities ), true );
		$this->policy             = $policy;
		$this->evidence_sink      = $evidence_sink;
		$this->context_provider   = $context_provider;
	}

	public function register_hooks( string $wordpress_version ): void {
		// WordPress 7.1 is the minimum because it is the first release with an
		// official post-permission authorization filter. Supporting older releases
		// would require a weaker registration-time callback wrapper.
		if ( version_compare( $wordpress_version, '7.1', '<' ) ) {
			throw new InvalidArgumentException( 'WordPress 7.1 or later is required.' );
		}

		// Maximum priority keeps this restriction after ordinary extension filters.
		add_filter( 'wp_ability_permission_result', array( $this, 'filter_permission_result' ), PHP_INT_MAX, 4 );
		add_action( 'wp_before_execute_ability', array( $this, 'record_execution_started' ), 100, 3 );
		add_action( 'wp_after_execute_ability', array( $this, 'record_execution_succeeded' ), 100, 4 );
	}

	/**
	 * WordPress 7.1+ integration, after the original permission callback.
	 *
	 * @param bool|\WP_Error $permission
	 * @return bool|\WP_Error
	 */
	public function filter_permission_result( $permission, string $ability_name, $input = null, $ability = null ) {
		if ( ! $this->is_governed( $ability_name ) ) {
			return $permission;
		}

		return $this->apply_after_application_permission( $permission, $ability_name, $input );
	}

	public function record_execution_started( string $ability_name, $input = null, $ability = null ): void {
		if ( ! $this->is_governed( $ability_name ) ) {
			return;
		}

		$this->clear_cached_allows( $ability_name );
		$this->record_event(
			$this->base_event( 'execution.started', $ability_name ) + array(
				'result_status' => 'started',
			)
		);
	}

	public function record_execution_succeeded( string $ability_name, $input, $result, $ability = null ): void {
		if ( ! $this->is_governed( $ability_name ) ) {
			return;
		}

		$this->record_event(
			$this->base_event( 'execution.succeeded', $ability_name ) + array(
				'result_status' => 'success',
			)
		);
	}

	/**
	 * @param bool|\WP_Error $permission
	 * @return bool|\WP_Error
	 */
	private function apply_after_application_permission( $permission, string $ability_name, $input ) {
		// Governance may only narrow WordPress authority. Never convert any
		// non-literal-true application result, including WP_Error, into an allow.
		if ( true !== $permission ) {
			$this->record_decision(
				$ability_name,
				'deny',
				array( 'application_permission_denied' ),
				false
			);

			return $permission;
		}

		try {
			$context = ( $this->context_provider )();
			if ( ! is_array( $context ) ) {
				return $this->deny_unavailable( $ability_name, 'governance_context_invalid' );
			}

			$cache_key = $this->cache_key( $ability_name, $input, $context );
			// MCP Adapter checks permission before execute(), and execute() checks it
			// again. Reuse the first allow so one external call reserves one budget.
			if ( isset( $this->allow_cache[ $cache_key ] ) ) {
				return true;
			}

			$decision = ( $this->policy )( $ability_name, $input, $context );
			if ( ! $this->is_valid_decision( $decision ) ) {
				return $this->deny_unavailable( $ability_name, 'governance_decision_invalid' );
			}

			$allowed = 'allow' === $decision['decision'];
			$event   = $this->decision_event( $ability_name, $decision, $context );
			if ( ! $this->record_event( $event ) ) {
				// An allow without its decision evidence is not a safe authorization.
				return $this->unavailable_error( 'evidence_store_unavailable' );
			}

			if ( ! $allowed ) {
				return new \WP_Error(
					'agentic_governance_denied',
					'Governance policy denied this Ability.',
					array(
						'status'       => 403,
						'reason_codes' => $decision['reason_codes'],
					)
				);
			}

			$this->allow_cache[ $cache_key ]                             = true;
			$this->cache_keys_by_ability[ $ability_name ][ $cache_key ] = true;

			return true;
		} catch ( Throwable $throwable ) {
			return $this->deny_unavailable( $ability_name, 'governance_evaluation_failed' );
		}
	}

	private function deny_unavailable( string $ability_name, string $reason_code ): \WP_Error {
		$this->record_decision( $ability_name, 'deny', array( $reason_code ), true );

		return $this->unavailable_error( $reason_code );
	}

	private function unavailable_error( string $reason_code ): \WP_Error {
		return new \WP_Error(
			'agentic_governance_unavailable',
			'Governance could not safely authorize this Ability.',
			array(
				'status'      => 503,
				'reason_code' => $reason_code,
			)
		);
	}

	private function is_governed( string $ability_name ): bool {
		return isset( $this->governed_abilities[ $ability_name ] );
	}

	private function is_valid_decision( $decision ): bool {
		if (
			! is_array( $decision ) ||
			! isset( $decision['decision'], $decision['reason_codes'] ) ||
			! in_array( $decision['decision'], array( 'allow', 'deny' ), true ) ||
			! is_array( $decision['reason_codes'] ) ||
			array() === $decision['reason_codes']
		) {
			return false;
		}

		foreach ( $decision['reason_codes'] as $reason_code ) {
			if ( ! is_string( $reason_code ) || ! preg_match( '/^[a-z0-9_.:-]{1,80}$/', $reason_code ) ) {
				return false;
			}
		}

		return true;
	}

	private function decision_event( string $ability_name, array $decision, array $context ): array {
		$event = $this->base_event( $this->decision_event_type( $decision['decision'] ), $ability_name, $context ) + array(
			'decision'              => $decision['decision'],
			'reason_codes'          => array_values( $decision['reason_codes'] ),
			'application_permission' => true,
		);

		foreach ( array( 'policy_id', 'policy_version' ) as $field ) {
			if ( isset( $decision[ $field ] ) && is_string( $decision[ $field ] ) ) {
				$event[ $field ] = $decision[ $field ];
			}
		}

		return $event;
	}

	private function record_decision(
		string $ability_name,
		string $decision,
		array $reason_codes,
		bool $application_permission
	): bool {
		return $this->record_event(
			$this->base_event( $this->decision_event_type( $decision ), $ability_name ) + array(
				'decision'               => $decision,
				'reason_codes'           => $reason_codes,
				'application_permission' => $application_permission,
			)
		);
	}

	private function decision_event_type( string $decision ): string {
		return 'allow' === $decision ? 'policy.allowed' : 'policy.denied';
	}

	private function base_event( string $event_type, string $ability_name, ?array $context = null ): array {
		if ( null === $context ) {
			try {
				$candidate = ( $this->context_provider )();
				$context   = is_array( $candidate ) ? $candidate : array();
			} catch ( Throwable $throwable ) {
				$context = array();
			}
		}

		$event = array(
			'event_id'       => $this->event_id(),
			'event_type'     => $event_type,
			'occurred_at'    => gmdate( 'c' ),
			'application'    => 'wordpress',
			'capability'     => $ability_name,
			'retention_class' => self::RETENTION_CLASS,
			'schema_version' => self::SCHEMA_VERSION,
		);

		foreach (
			array(
				'application',
				'application_principal',
				'agent_identity',
				'client_identity',
				'protocol',
				'request_id',
				'trace_id',
			) as $field
		) {
			if ( isset( $context[ $field ] ) && is_string( $context[ $field ] ) && '' !== $context[ $field ] ) {
				$event[ $field ] = $context[ $field ];
			}
		}

		return $event;
	}

	private function event_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return substr( hash( 'sha256', uniqid( '', true ) ), 0, 32 );
	}

	private function record_event( array $event ): bool {
		try {
			return true === ( $this->evidence_sink )( $event );
		} catch ( Throwable $throwable ) {
			return false;
		}
	}

	private function cache_key( string $ability_name, $input, array $context ): string {
		// Include every supplied context claim so one client or agent cannot reuse
		// another context's in-request allow. Only the digest remains in memory.
		return hash( 'sha256', serialize( array( $ability_name, $context, $input ) ) );
	}

	private function clear_cached_allows( string $ability_name ): void {
		// wp_before_execute_ability runs after the final permission check. Clearing
		// here keeps a later same-request execution from reusing the reservation.
		foreach ( array_keys( $this->cache_keys_by_ability[ $ability_name ] ?? array() ) as $cache_key ) {
			unset( $this->allow_cache[ $cache_key ] );
		}

		unset( $this->cache_keys_by_ability[ $ability_name ] );
	}
}
