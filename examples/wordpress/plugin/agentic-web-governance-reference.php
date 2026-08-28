<?php
/**
 * Plugin Name: Agentic Web Governance Reference
 * Description: A minimal read-only WordPress Abilities governance integration.
 * Version: 0.1.0
 * Requires at least: 7.1
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package AgenticWebGovernanceReference
 */

declare(strict_types=1);

namespace AtShift\AgenticWebGovernance\WordPressReference;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-option-mutex.php';
require_once __DIR__ . '/includes/class-transient-rate-limiter.php';
require_once __DIR__ . '/includes/class-option-evidence-store.php';
require_once __DIR__ . '/includes/class-reference-read-policy.php';
require_once __DIR__ . '/includes/class-ability-governance-gate.php';

const REFERENCE_ABILITY = 'agentic-web-governance/site-summary';

/**
 * Registers the reference governance gate before Abilities are registered.
 */
function bootstrap(): void {
	global $wp_version;

	// The reference intentionally starts at the first Core release with a
	// post-permission Ability filter instead of carrying a legacy wrapper path.
	if ( ! is_string( $wp_version ) || version_compare( $wp_version, '7.1', '<' ) ) {
		return;
	}

	$rate_limiter  = new TransientRateLimiter();
	$evidence_store = new OptionEvidenceStore( 100 );
	$policy         = new ReferenceReadPolicy(
		$rate_limiter,
		array(
			REFERENCE_ABILITY => array(
				'limit'  => 10,
				'window' => MINUTE_IN_SECONDS,
			),
		)
	);

	$gate = new AbilityGovernanceGate(
		array( REFERENCE_ABILITY ),
		$policy,
		array( $evidence_store, 'append' ),
		static function (): array {
			$user_id = get_current_user_id();

			return array(
				'application'           => 'wordpress',
				'application_principal' => $user_id > 0 ? 'wp-user:' . $user_id : null,
			);
		}
	);

	$gate->register_hooks( $wp_version );

	add_action( 'wp_abilities_api_categories_init', __NAMESPACE__ . '\\register_category' );
	add_action( 'wp_abilities_api_init', __NAMESPACE__ . '\\register_reference_ability' );
}

function register_category(): void {
	wp_register_ability_category(
		'agentic-web-governance',
		array(
			'label'       => 'Agentic Web Governance',
			'description' => 'Narrow reference Abilities protected by governance policy.',
		)
	);
}

function register_reference_ability(): void {
	wp_register_ability(
		REFERENCE_ABILITY,
		array(
			'label'               => 'Read site summary',
			'description'         => 'Returns the public site title, tagline, and home URL.',
			'category'            => 'agentic-web-governance',
			'execute_callback'    => static function (): array {
				return array(
					'name'        => get_bloginfo( 'name' ),
					'description' => get_bloginfo( 'description' ),
					'url'         => home_url( '/' ),
				);
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'read' );
			},
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'url'         => array( 'type' => 'string', 'format' => 'uri' ),
				),
				'required'             => array( 'name', 'description', 'url' ),
				'additionalProperties' => false,
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				// Expose through the official MCP Adapter, but not Core REST discovery.
				'public'       => false,
				'show_in_rest' => false,
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

bootstrap();
