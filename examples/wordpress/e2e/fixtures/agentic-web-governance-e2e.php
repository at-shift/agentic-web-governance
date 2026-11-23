<?php
/**
 * Plugin Name: Agentic Web Governance E2E Fixture
 * Description: Isolated authenticated permission and input-preparation fixtures.
 * Version: 0.1.0
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'AWG_E2E' ) || true !== AWG_E2E ) {
	return;
}

// The adapter normally requires `read` at both its HTTP transport and execution
// wrapper. E2E uses `exist` at only those outer gates so a role-less but
// authenticated user reaches the target callback. The target still has to grant
// its own `read` capability and governance checks.
$authenticated_outer_gate = static function (): string {
	// WordPress maps `exist` to true even for the anonymous user. Select it only
	// after Application Password authentication has established a principal;
	// otherwise the transport discovery endpoint would become public.
	return get_current_user_id() > 0 ? 'exist' : 'do_not_allow';
};

add_filter( 'mcp_adapter_default_transport_permission_user_capability', $authenticated_outer_gate );
add_filter( 'mcp_adapter_execute_ability_capability', $authenticated_outer_gate );

// This capability exists only in the isolated fixture and is intentionally
// absent from MCP discovery, even for an authenticated administrator.
add_action(
	'wp_abilities_api_init',
	static function (): void {
		wp_register_ability(
			'agentic-web-governance/e2e-hidden',
			array(
				'label'               => 'awg-private-fixture-marker',
				'description'         => 'awg-private-fixture-marker',
				'category'            => 'agentic-web-governance',
				'input_schema'        => array( 'type' => 'object' ),
				'execute_callback'    => static function (): array {
					return array( 'secret' => 'awg-private-fixture-marker' );
				},
				'permission_callback' => '__return_true',
				'meta'                => array( 'mcp' => array( 'public' => false ) ),
			)
		);
	},
	20
);

// This synthetic Ability exists only under AWG_E2E. Keep its policy and probe
// observations separate from the shipped site-summary allowlist and evidence.
// Adapter 0.7.2 must prepare the same input for probes and execution, or the
// gate's input-bound memoization would reserve one external call twice.
function awg_e2e_record_input( string $stage, $input ): void {
	$state = get_option( 'awg_e2e_input_observations', array() );
	$state[ $stage ][] = $input;
	update_option( 'awg_e2e_input_observations', $state, false );
}

add_action(
	'plugins_loaded',
	static function (): void {
		$gate = new \AtShift\AgenticWebGovernance\WordPressReference\AbilityGovernanceGate(
			array( 'agentic-web-governance/e2e-input' ),
			static function ( string $ability, $input, array $context ): array {
				awg_e2e_record_input( 'policy', $input );
				return array( 'decision' => 'allow', 'reason_codes' => array( 'e2e_input_allowed' ) );
			},
			// Probe instrumentation, not a durable audit store or approval verifier.
			static function ( array $event ): bool {
				return true;
			},
			static function (): array {
				return array( 'application_principal' => 'wp-user:' . get_current_user_id() );
			}
		);
		global $wp_version;
		$gate->register_hooks( $wp_version );
	},
	20
);

add_filter(
	'wp_ability_normalize_input',
	static function ( $input, string $ability_name ) {
		if ( 'agentic-web-governance/e2e-input' === $ability_name && is_array( $input ) &&
			isset( $input['count'] ) && is_string( $input['count'] ) && preg_match( '/^[0-9]{1,2}$/', $input['count'] ) ) {
			// Normalization belongs before schema validation and authorization,
			// never in a later pre-tool-call filter that repairs rejected input.
			$input['count'] = (int) $input['count'];
		}
		return $input;
	},
	10,
	2
);

add_action(
	'wp_abilities_api_init',
	static function (): void {
		wp_register_ability(
			'agentic-web-governance/e2e-input',
			array(
				'label'               => 'Synthetic input preparation fixture',
				'description'         => 'Checks normalized input before permission and execution.',
				'category'            => 'agentic-web-governance',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'count' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ) ),
					'required'             => array( 'count' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ): bool {
					awg_e2e_record_input( 'permission', $input );
					return current_user_can( 'read' );
				},
				'execute_callback'    => static function ( $input ): array {
					awg_e2e_record_input( 'execution', $input );
					return $input;
				},
				'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
			)
		);
	},
	20
);
