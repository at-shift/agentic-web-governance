<?php
/**
 * Integration tests for the WordPress reference boundary without WordPress.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

use AtShift\AgenticWebGovernance\WordPressReference\AbilityGovernanceGate;
use AtShift\AgenticWebGovernance\WordPressReference\OptionEvidenceStore;
use AtShift\AgenticWebGovernance\WordPressReference\OptionMutex;
use AtShift\AgenticWebGovernance\WordPressReference\ReferenceReadPolicy;
use AtShift\AgenticWebGovernance\WordPressReference\TransientRateLimiter;

final class WP_Error {
	private string $code;

	private string $message;

	/** @var mixed */
	private $data;

	public function __construct( string $code, string $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code(): string {
		return $this->code;
	}

	public function get_error_message(): string {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

$GLOBALS['test_hooks']             = array();
$GLOBALS['test_options']           = array();
$GLOBALS['test_transients']        = array();
$GLOBALS['test_actions']           = array();
$GLOBALS['test_uuid']              = 0;
$GLOBALS['test_update_fails']      = false;
$GLOBALS['test_transient_fails']   = false;
$GLOBALS['test_registered_ability'] = null;

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['test_hooks']['filter'][ $hook ][] = array( $callback, $priority, $accepted_args );

	return true;
}

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['test_hooks']['action'][ $hook ][] = array( $callback, $priority, $accepted_args );

	return true;
}

function do_action( string $hook, ...$arguments ): void {
	$GLOBALS['test_actions'][] = array( $hook, $arguments );
}

function add_option( string $name, $value, string $deprecated = '', $autoload = null ): bool {
	if ( array_key_exists( $name, $GLOBALS['test_options'] ) ) {
		return false;
	}

	$GLOBALS['test_options'][ $name ] = $value;

	return true;
}

function get_option( string $name, $default = false ) {
	return $GLOBALS['test_options'][ $name ] ?? $default;
}

function update_option( string $name, $value, $autoload = null ): bool {
	if ( $GLOBALS['test_update_fails'] ) {
		return false;
	}

	$GLOBALS['test_options'][ $name ] = $value;

	return true;
}

function delete_option( string $name ): bool {
	if ( ! array_key_exists( $name, $GLOBALS['test_options'] ) ) {
		return false;
	}

	unset( $GLOBALS['test_options'][ $name ] );

	return true;
}

function get_transient( string $name ) {
	return $GLOBALS['test_transients'][ $name ] ?? false;
}

function set_transient( string $name, $value, int $expiration = 0 ): bool {
	if ( $GLOBALS['test_transient_fails'] ) {
		return false;
	}

	$GLOBALS['test_transients'][ $name ] = $value;

	return true;
}

function wp_generate_uuid4(): string {
	++$GLOBALS['test_uuid'];

	return sprintf( '00000000-0000-4000-8000-%012d', $GLOBALS['test_uuid'] );
}

function get_current_user_id(): int {
	return 7;
}

function current_user_can( string $capability ): bool {
	return 'read' === $capability;
}

function get_bloginfo( string $field ): string {
	return 'name' === $field ? 'Reference Site' : 'Reference Description';
}

function home_url( string $path = '' ): string {
	return 'https://example.test' . $path;
}

function wp_register_ability_category( string $slug, array $args ): object {
	return (object) array( 'slug' => $slug, 'args' => $args );
}

function wp_register_ability( string $name, array $args ): object {
	$GLOBALS['test_registered_ability'] = array( 'name' => $name, 'args' => $args );

	return (object) $GLOBALS['test_registered_ability'];
}

require_once __DIR__ . '/../examples/wordpress/plugin/includes/class-option-mutex.php';
require_once __DIR__ . '/../examples/wordpress/plugin/includes/class-transient-rate-limiter.php';
require_once __DIR__ . '/../examples/wordpress/plugin/includes/class-option-evidence-store.php';
require_once __DIR__ . '/../examples/wordpress/plugin/includes/class-reference-read-policy.php';
require_once __DIR__ . '/../examples/wordpress/plugin/includes/class-ability-governance-gate.php';

$passed = 0;
$failed = 0;

function reset_wordpress_test_state(): void {
	$GLOBALS['test_hooks']           = array();
	$GLOBALS['test_options']         = array();
	$GLOBALS['test_transients']      = array();
	$GLOBALS['test_actions']         = array();
	$GLOBALS['test_update_fails']    = false;
	$GLOBALS['test_transient_fails'] = false;
}

function assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			$message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.'
		);
	}
}

function test_case( string $name, callable $test ): void {
	global $passed, $failed;

	reset_wordpress_test_state();

	try {
		$test();
		++$passed;
	} catch ( Throwable $throwable ) {
		++$failed;
		fwrite( STDERR, "FAIL {$name}: {$throwable->getMessage()}\n" );
	}
}

function context_provider(): array {
	return array(
		'application'           => 'wordpress',
		'application_principal' => 'wp-user:7',
	);
}

test_case(
	'WordPress 7.1 uses the post-permission filter',
	static function (): void {
		$gate = new AbilityGovernanceGate(
			array( 'test/read' ),
			static fn(): array => array( 'decision' => 'allow', 'reason_codes' => array( 'test_allow' ) ),
			static fn(): bool => true,
			'context_provider'
		);
		$gate->register_hooks( '7.1' );

		assert_true( isset( $GLOBALS['test_hooks']['filter']['wp_ability_permission_result'] ), '7.1 permission filter missing.' );
		assert_same(
			PHP_INT_MAX,
			$GLOBALS['test_hooks']['filter']['wp_ability_permission_result'][0][1],
			'Governance filter is not last among ordinary priorities.'
		);
		assert_true( ! isset( $GLOBALS['test_hooks']['filter']['wp_register_ability_args'] ), '7.1 must not wrap registrations.' );
	}
);

test_case(
	'WordPress versions before 7.1 are rejected',
	static function (): void {
		$gate = new AbilityGovernanceGate(
			array( 'test/read' ),
			static fn(): array => array( 'decision' => 'allow', 'reason_codes' => array( 'test_allow' ) ),
			static fn(): bool => true,
			'context_provider'
		);

		try {
			$gate->register_hooks( '7.0.9' );
		} catch ( InvalidArgumentException $exception ) {
			assert_true( ! isset( $GLOBALS['test_hooks']['filter'] ), 'Unsupported version registered a filter.' );
			return;
		}

		throw new RuntimeException( 'Unsupported WordPress version was accepted.' );
	}
);

test_case(
	'ungoverned abilities retain the application result',
	static function (): void {
		$gate = new AbilityGovernanceGate(
			array( 'test/read' ),
			static fn(): array => array( 'decision' => 'deny', 'reason_codes' => array( 'never_called' ) ),
			static fn(): bool => true,
			'context_provider'
		);

		assert_same( true, $gate->filter_permission_result( true, 'other/read' ), 'Ungoverned permission changed.' );
	}
);

test_case(
	'application false is preserved and policy is skipped',
	static function (): void {
		$policy_calls = 0;
		$events       = array();
		$gate         = new AbilityGovernanceGate(
			array( 'test/read' ),
			static function () use ( &$policy_calls ): array {
				++$policy_calls;
				return array( 'decision' => 'allow', 'reason_codes' => array( 'test_allow' ) );
			},
			static function ( array $event ) use ( &$events ): bool {
				$events[] = $event;
				return true;
			},
			'context_provider'
		);

		assert_same( false, $gate->filter_permission_result( false, 'test/read' ), 'Application denial changed.' );
		assert_same( 0, $policy_calls, 'Policy ran after an application denial.' );
		assert_same( 'policy.denied', $events[0]['event_type'], 'Application denial event missing.' );
		assert_same( false, $events[0]['application_permission'], 'Application denial was mislabeled.' );
	}
);

test_case(
	'application WP_Error is preserved by identity',
	static function (): void {
		$application_error = new WP_Error( 'application_denied' );
		$gate              = new AbilityGovernanceGate(
			array( 'test/read' ),
			static fn(): array => array( 'decision' => 'allow', 'reason_codes' => array( 'never_called' ) ),
			static fn(): bool => true,
			'context_provider'
		);

		assert_same( $application_error, $gate->filter_permission_result( $application_error, 'test/read' ), 'WP_Error changed.' );
	}
);

test_case(
	'governance can narrow an application allow',
	static function (): void {
		$events = array();
		$gate   = new AbilityGovernanceGate(
			array( 'test/read' ),
			static fn(): array => array(
				'decision'       => 'deny',
				'reason_codes'   => array( 'site_policy_denied' ),
				'policy_id'      => 'test-policy',
				'policy_version' => '1',
			),
			static function ( array $event ) use ( &$events ): bool {
				$events[] = $event;
				return true;
			},
			'context_provider'
		);

		$result = $gate->filter_permission_result( true, 'test/read', array( 'secret' => 'do-not-log' ) );
		assert_true( $result instanceof WP_Error, 'Governance denial did not return WP_Error.' );
		assert_same( 'agentic_governance_denied', $result->get_error_code(), 'Wrong governance denial code.' );
		assert_same( 'policy.denied', $events[0]['event_type'], 'Governance denial event missing.' );
		assert_true( ! array_key_exists( 'input', $events[0] ), 'Raw input entered evidence.' );
		assert_true( false === strpos( serialize( $events ), 'do-not-log' ), 'Secret input entered evidence.' );
	}
);

test_case(
	'MCP-style duplicate checks reserve one policy decision',
	static function (): void {
		$policy_calls = 0;
		$events       = array();
		$gate         = new AbilityGovernanceGate(
			array( 'test/read' ),
			static function () use ( &$policy_calls ): array {
				++$policy_calls;
				return array( 'decision' => 'allow', 'reason_codes' => array( 'rate_limit_reserved' ) );
			},
			static function ( array $event ) use ( &$events ): bool {
				$events[] = $event;
				return true;
			},
			'context_provider'
		);

		assert_same( true, $gate->filter_permission_result( true, 'test/read', array( 'page' => 1 ) ), 'First check denied.' );
		assert_same( true, $gate->filter_permission_result( true, 'test/read', array( 'page' => 1 ) ), 'Second check denied.' );
		assert_same( 1, $policy_calls, 'Duplicate check consumed policy twice.' );

		$gate->record_execution_started( 'test/read', array( 'page' => 1 ) );
		assert_same( true, $gate->filter_permission_result( true, 'test/read', array( 'page' => 1 ) ), 'Later execution denied.' );
		assert_same( 2, $policy_calls, 'Execution-start boundary did not clear the allow cache.' );
	}
);

test_case(
	'successful execution emits minimized lifecycle evidence',
	static function (): void {
		$events = array();
		$gate   = new AbilityGovernanceGate(
			array( 'test/read' ),
			static fn(): array => array( 'decision' => 'allow', 'reason_codes' => array( 'test_allow' ) ),
			static function ( array $event ) use ( &$events ): bool {
				$events[] = $event;
				return true;
			},
			'context_provider'
		);

		$input  = array( 'secret' => 'input-secret' );
		$result = array( 'secret' => 'result-secret' );
		$gate->filter_permission_result( true, 'test/read', $input );
		$gate->record_execution_started( 'test/read', $input );
		$gate->record_execution_succeeded( 'test/read', $input, $result );

		assert_same(
			array( 'policy.allowed', 'execution.started', 'execution.succeeded' ),
			array_column( $events, 'event_type' ),
			'Successful lifecycle evidence is incomplete.'
		);
		assert_true( ! isset( $events[0]['agent_identity'] ), 'Unknown agent identity was invented.' );
		assert_true( false === strpos( serialize( $events ), 'input-secret' ), 'Raw input entered lifecycle evidence.' );
		assert_true( false === strpos( serialize( $events ), 'result-secret' ), 'Raw result entered lifecycle evidence.' );
	}
);

test_case(
	'in-request allows are isolated by execution context',
	static function (): void {
		$policy_calls = 0;
		$client       = 'client-a';
		$gate         = new AbilityGovernanceGate(
			array( 'test/read' ),
			static function () use ( &$policy_calls ): array {
				++$policy_calls;
				return array( 'decision' => 'allow', 'reason_codes' => array( 'test_allow' ) );
			},
			static fn(): bool => true,
			static function () use ( &$client ): array {
				return array(
					'application_principal' => 'wp-user:7',
					'client_identity'       => $client,
				);
			}
		);

		$gate->filter_permission_result( true, 'test/read', array( 'page' => 1 ) );
		$client = 'client-b';
		$gate->filter_permission_result( true, 'test/read', array( 'page' => 1 ) );

		assert_same( 2, $policy_calls, 'A cached allow crossed client contexts.' );
	}
);

test_case(
	'policy exceptions fail closed',
	static function (): void {
		$gate = new AbilityGovernanceGate(
			array( 'test/read' ),
			static function (): array {
				throw new RuntimeException( 'sensitive failure detail' );
			},
			static fn(): bool => true,
			'context_provider'
		);

		$result = $gate->filter_permission_result( true, 'test/read' );
		assert_true( $result instanceof WP_Error, 'Policy exception did not deny.' );
		assert_same( 'agentic_governance_unavailable', $result->get_error_code(), 'Wrong unavailable code.' );
		assert_true( false === strpos( $result->get_error_message(), 'sensitive' ), 'Exception detail leaked.' );
	}
);

test_case(
	'malformed policy decisions fail closed',
	static function (): void {
		$gate = new AbilityGovernanceGate(
			array( 'test/read' ),
			static fn(): array => array( 'decision' => 'maybe', 'reason_codes' => array() ),
			static fn(): bool => true,
			'context_provider'
		);

		$result = $gate->filter_permission_result( true, 'test/read' );
		assert_same( 'agentic_governance_unavailable', $result->get_error_code(), 'Malformed decision did not fail closed.' );
	}
);

test_case(
	'an allow is denied when decision evidence cannot be stored',
	static function (): void {
		$gate = new AbilityGovernanceGate(
			array( 'test/read' ),
			static fn(): array => array( 'decision' => 'allow', 'reason_codes' => array( 'test_allow' ) ),
			static fn(): bool => false,
			'context_provider'
		);

		$result = $gate->filter_permission_result( true, 'test/read' );
		assert_same( 'agentic_governance_unavailable', $result->get_error_code(), 'Evidence failure did not deny allow.' );
	}
);

test_case(
	'rate limiter reserves up to the configured limit',
	static function (): void {
		$limiter = new TransientRateLimiter();

		assert_same( true, $limiter->reserve( 'wp-user:7|test/read', 2, 60 )['allowed'], 'First reservation denied.' );
		assert_same( true, $limiter->reserve( 'wp-user:7|test/read', 2, 60 )['allowed'], 'Second reservation denied.' );
		$third = $limiter->reserve( 'wp-user:7|test/read', 2, 60 );
		assert_same( false, $third['allowed'], 'Rate limit did not deny the third reservation.' );
		assert_same( 'rate_limit_exceeded', $third['reason_code'], 'Wrong rate-limit reason.' );
	}
);

test_case(
	'rate limiter storage write failures deny',
	static function (): void {
		$GLOBALS['test_transient_fails'] = true;
		$result                           = ( new TransientRateLimiter() )->reserve( 'wp-user:7|test/read', 2, 60 );

		assert_same( false, $result['allowed'], 'Storage write failure allowed a reservation.' );
		assert_same( 'rate_limit_storage_unavailable', $result['reason_code'], 'Wrong storage failure reason.' );
	}
);

test_case(
	'malformed rate state denies',
	static function (): void {
		$digest = substr( hash( 'sha256', 'wp-user:7|test/read' ), 0, 40 );
		$GLOBALS['test_transients'][ 'awg_ref_rate_' . $digest ] = array( 'count' => 'one', 'expires_at' => time() + 60 );
		$result = ( new TransientRateLimiter() )->reserve( 'wp-user:7|test/read', 2, 60 );

		assert_same( false, $result['allowed'], 'Malformed state allowed a reservation.' );
		assert_same( 'rate_limit_storage_invalid', $result['reason_code'], 'Wrong malformed-state reason.' );
	}
);

test_case(
	'expired string-valued locks are recovered',
	static function (): void {
		$GLOBALS['test_options']['test_mutex'] = (string) ( time() - 10 );
		$result = ( new OptionMutex( 'test_mutex' ) )->synchronized( static fn(): string => 'ok' );

		assert_same( 'ok', $result, 'Expired lock was not recovered.' );
		assert_true( ! isset( $GLOBALS['test_options']['test_mutex'] ), 'Mutex was not released.' );
	}
);

test_case(
	'a stale mutex owner cannot release a newer lock',
	static function (): void {
		( new OptionMutex( 'test_mutex' ) )->synchronized(
			static function (): void {
				$GLOBALS['test_options']['test_mutex'] = array(
					'owner_token' => 'new-owner',
					'expires_at'  => time() + 5,
				);
			}
		);

		assert_same( 'new-owner', $GLOBALS['test_options']['test_mutex']['owner_token'], 'Stale owner removed a newer lock.' );
	}
);

test_case(
	'evidence store is bounded and projects fields',
	static function (): void {
		$store = new OptionEvidenceStore( 2 );
		for ( $index = 1; $index <= 3; ++$index ) {
			$store->append(
				array(
					'event_id'    => 'event-' . $index,
					'event_type'  => 'policy.allowed',
					'capability'  => 'test/read',
					'reason_codes' => array( 'test_allow', 'INVALID CODE' ),
					'input'       => array( 'secret' => 'do-not-store' ),
				)
			);
		}

		$events = $GLOBALS['test_options']['awg_reference_evidence'];
		assert_same( 2, count( $events ), 'Evidence store exceeded its bound.' );
		assert_same( 'event-2', $events[0]['event_id'], 'Evidence store retained the wrong events.' );
		assert_true( ! isset( $events[0]['input'] ), 'Evidence projection retained raw input.' );
		assert_same( array( 'test_allow' ), $events[0]['reason_codes'], 'Reason codes were not projected.' );
	}
);

test_case(
	'reference policy requires an authenticated principal',
	static function (): void {
		$policy = new ReferenceReadPolicy(
			new TransientRateLimiter(),
			array( 'test/read' => array( 'limit' => 1, 'window' => 60 ) )
		);
		$result = $policy( 'test/read', null, array() );

		assert_same( 'deny', $result['decision'], 'Anonymous principal was allowed.' );
		assert_same( 'authenticated_principal_required', $result['reason_codes'][0], 'Wrong anonymous denial reason.' );
	}
);

test_case(
	'reference plugin exposes one read-only MCP Ability',
	static function (): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ . '/' );
		}
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$GLOBALS['wp_version'] = '7.1';
		require_once __DIR__ . '/../examples/wordpress/plugin/agentic-web-governance-reference.php';
		AtShift\AgenticWebGovernance\WordPressReference\register_reference_ability();

		$ability = $GLOBALS['test_registered_ability'];
		assert_same( 'agentic-web-governance/site-summary', $ability['name'], 'Wrong reference Ability name.' );
		assert_same( true, $ability['args']['meta']['mcp']['public'], 'Ability is not MCP-exposed.' );
		assert_same( false, $ability['args']['meta']['public'], 'Ability widened generic public exposure.' );
		assert_same( false, $ability['args']['meta']['show_in_rest'], 'Ability was exposed through REST.' );
		assert_same( true, $ability['args']['meta']['annotations']['readonly'], 'Ability lacks read-only annotation.' );
		assert_same( true, $ability['args']['permission_callback'](), 'Reference WordPress permission failed.' );
	}
);

if ( $failed > 0 ) {
	fwrite( STDERR, "WordPress ability governance: {$passed} passed, {$failed} failed.\n" );
	exit( 1 );
}

fwrite( STDOUT, "WordPress ability governance: {$passed} passed, {$failed} failed.\n" );
