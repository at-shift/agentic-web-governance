<?php
/**
 * Plugin Name: Agentic Web Governance E2E Fixture
 * Description: Keeps the MCP outer gate open for authenticated permission-boundary tests.
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
