<?php
/**
 * A bounded evidence sink for the reference plugin.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package AgenticWebGovernanceReference
 */

declare(strict_types=1);

namespace AtShift\AgenticWebGovernance\WordPressReference;

use RuntimeException;

final class OptionEvidenceStore {
	private const OPTION_NAME = 'awg_reference_evidence';

	private const LOCK_NAME = 'awg_reference_evidence_lock';

	private int $maximum_events;

	public function __construct( int $maximum_events = 100 ) {
		$this->maximum_events = max( 1, $maximum_events );
	}

	public function append( array $event ): bool {
		$event = $this->project_event( $event );
		$mutex = new OptionMutex( self::LOCK_NAME );

		$result = $mutex->synchronized(
			function () use ( $event ): bool {
				$events = get_option( self::OPTION_NAME, array() );
				if ( ! is_array( $events ) ) {
					throw new RuntimeException( 'The governance evidence store is invalid.' );
				}

				$events[] = $event;
				$events   = array_slice( $events, -$this->maximum_events );

				if ( ! update_option( self::OPTION_NAME, $events, false ) ) {
					throw new RuntimeException( 'The governance evidence store could not be updated.' );
				}

				return true;
			}
		);

		if ( true === $result ) {
			do_action( 'agentic_web_governance_evidence_recorded', $event );
		}

		return true === $result;
	}

	private function project_event( array $event ): array {
		// Evidence is an explicit projection. Raw Ability input and output have no slot.
		$allowed_fields = array_fill_keys(
			array(
				'event_id',
				'event_type',
				'occurred_at',
				'request_id',
				'trace_id',
				'application',
				'application_principal',
				'agent_identity',
				'client_identity',
				'protocol',
				'capability',
				'decision',
				'reason_codes',
				'application_permission',
				'policy_id',
				'policy_version',
				'result_status',
				'error_code',
				'retention_class',
				'schema_version',
			),
			true
		);

		$projected = array_intersect_key( $event, $allowed_fields );
		foreach ( $projected as $key => $value ) {
			if ( 'reason_codes' === $key ) {
				$projected[ $key ] = $this->project_reason_codes( $value );
				continue;
			}

			if ( is_string( $value ) ) {
				$projected[ $key ] = substr( $value, 0, 255 );
			}
		}

		return $projected;
	}

	/**
	 * @return list<string>
	 */
	private function project_reason_codes( $reason_codes ): array {
		if ( ! is_array( $reason_codes ) ) {
			return array();
		}

		$result = array();
		foreach ( $reason_codes as $reason_code ) {
			if ( is_string( $reason_code ) && preg_match( '/^[a-z0-9_.:-]{1,80}$/', $reason_code ) ) {
				$result[] = $reason_code;
			}
		}

		return array_values( array_unique( $result ) );
	}
}
