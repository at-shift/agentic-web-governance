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
	return 'exist';
};

add_filter( 'mcp_adapter_default_transport_permission_user_capability', $authenticated_outer_gate );
add_filter( 'mcp_adapter_execute_ability_capability', $authenticated_outer_gate );
