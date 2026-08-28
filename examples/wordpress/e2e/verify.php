<?php
/**
 * Exercises the governance path through the MCP Adapter HTTP transport.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

const AWG_E2E_ENDPOINT = 'http://127.0.0.1:8081/wp-json/mcp/mcp-adapter-default-server';
const AWG_E2E_PROTOCOL = '2025-11-25';
const AWG_E2E_ABILITY  = 'agentic-web-governance/site-summary';

$runtime_dir     = __DIR__ . '/runtime';
$wordpress_dir   = $runtime_dir . '/wordpress';
$credentials_file = $runtime_dir . '/credentials.json';

if ( ! is_file( $credentials_file ) || ! is_file( $wordpress_dir . '/wp-load.php' ) ) {
	fail( 'Run npm run e2e:wordpress:setup first.' );
}

$credentials = json_decode( (string) file_get_contents( $credentials_file ), true );
if ( ! is_array( $credentials ) ) {
	fail( 'The E2E credentials file is invalid.' );
}

require $wordpress_dir . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wp_version;
assert_same( '7.1', $wp_version, 'WordPress version' );
assert_true( is_plugin_active( 'mcp-adapter/mcp-adapter.php' ), 'MCP Adapter is inactive.' );
assert_true(
	is_plugin_active( 'agentic-web-governance-reference/agentic-web-governance-reference.php' ),
	'Reference plugin is inactive.'
);
$mcp_data = get_plugin_data( WP_PLUGIN_DIR . '/mcp-adapter/mcp-adapter.php', false, false );
assert_same( '0.6.1', $mcp_data['Version'] ?? null, 'MCP Adapter version' );
pass( 'Pinned WordPress and MCP Adapter versions are active' );

$debug_log = WP_CONTENT_DIR . '/debug.log';
if ( false === file_put_contents( $debug_log, '' ) ) {
	fail( 'Could not reset the WordPress debug log.' );
}

$unauthenticated = request(
	null,
	array(
		'jsonrpc' => '2.0',
		'id'      => 1,
		'method'  => 'initialize',
		'params'  => initialize_params(),
	)
);
assert_true(
	in_array( $unauthenticated['status'], array( 401, 403 ), true ),
	'Unauthenticated initialize was not rejected.'
);
assert_true( empty( $unauthenticated['headers']['mcp-session-id'] ), 'Rejected request created a session.' );
pass( 'Unauthenticated MCP initialization is rejected' );

$admin_auth = array( $credentials['admin_username'], $credentials['admin_app_password'] );
$blocked_auth = array( $credentials['blocked_username'], $credentials['blocked_app_password'] );
$admin_session = initialize_session( $admin_auth, 10 );
$blocked_session = initialize_session( $blocked_auth, 20 );
pass( 'Authenticated MCP sessions initialize for both test principals' );

$tools = mcp_call( $admin_auth, $admin_session, 11, 'tools/list' );
$tool_names = array_column( $tools['result']['tools'] ?? array(), 'name' );
foreach (
	array(
		'mcp-adapter-discover-abilities',
		'mcp-adapter-get-ability-info',
		'mcp-adapter-execute-ability',
	) as $expected_tool
) {
	assert_true( in_array( $expected_tool, $tool_names, true ), 'Missing MCP tool: ' . $expected_tool );
}
pass( 'The default MCP server lists its three Ability tools' );

$discovery = call_tool( $admin_auth, $admin_session, 12, 'mcp-adapter-discover-abilities', array() );
assert_false( $discovery['result']['isError'] ?? true, 'Ability discovery returned an error.' );
$abilities = $discovery['result']['structuredContent']['abilities'] ?? array();
assert_true(
	in_array( AWG_E2E_ABILITY, array_column( $abilities, 'name' ), true ),
	'Reference Ability was not discoverable over MCP.'
);
pass( 'The MCP-only reference Ability is discoverable' );

reset_governance_state( (int) $credentials['admin_user_id'] );

$blocked = execute_reference_ability( $blocked_auth, $blocked_session, 21 );
assert_true( true === ( $blocked['result']['isError'] ?? false ), 'Capability-less user was not denied.' );
assert_contains(
	'Permission denied',
	tool_text( $blocked ),
	'Application permission denial was not returned through MCP.'
);
pass( 'The target WordPress permission callback remains authoritative' );

$successful_results = array();
for ( $index = 1; $index <= 10; ++$index ) {
	$response = execute_reference_ability( $admin_auth, $admin_session, 100 + $index );
	assert_false( $response['result']['isError'] ?? true, 'Authorized call ' . $index . ' failed.' );
	$result = $response['result']['structuredContent'] ?? array();
	assert_true( true === ( $result['success'] ?? false ), 'Authorized call did not report success.' );
	$successful_results[] = $result['data'] ?? null;
}

$summary = $successful_results[0] ?? array();
assert_same( 'Agentic Web Governance E2E', $summary['name'] ?? null, 'Ability site name' );
assert_same( 'http://127.0.0.1:8081/', $summary['url'] ?? null, 'Ability home URL' );
pass( 'Ten authorized MCP calls return the expected site summary' );

$limited = execute_reference_ability( $admin_auth, $admin_session, 111 );
assert_true( true === ( $limited['result']['isError'] ?? false ), 'The eleventh call was not denied.' );
assert_contains( 'Governance policy denied', tool_text( $limited ), 'Governance denial was not returned through MCP.' );
pass( 'The eleventh call is denied by governance' );

wp_cache_delete( 'awg_reference_evidence', 'options' );
// This verifier deletes the option before separate HTTP requests recreate it.
// Clear WordPress's negative option cache as well as the value cache before the
// originating CLI process reads the database again.
wp_cache_delete( 'notoptions', 'options' );
$evidence = get_option( 'awg_reference_evidence', array() );
assert_true( is_array( $evidence ), 'Evidence option is not an array.' );
assert_true( count( $evidence ) <= 100, 'Evidence exceeded its configured bound.' );

$allowed = events_of_type( $evidence, 'policy.allowed' );
$denied  = events_of_type( $evidence, 'policy.denied' );
$started = events_of_type( $evidence, 'execution.started' );
$succeeded = events_of_type( $evidence, 'execution.succeeded' );

assert_same( 10, count( $allowed ), 'Allowed-decision evidence count' );
assert_same( 2, count( $denied ), 'Denied-decision evidence count' );
assert_same( 10, count( $started ), 'Started-execution evidence count' );
assert_same( 10, count( $succeeded ), 'Succeeded-execution evidence count' );

$application_denials = array_values(
	array_filter(
		$denied,
		static fn( array $event ): bool => false === ( $event['application_permission'] ?? null )
	)
);
$rate_denials = array_values(
	array_filter(
		$denied,
		static fn( array $event ): bool => in_array( 'rate_limit_exceeded', $event['reason_codes'] ?? array(), true )
	)
);
assert_same( 1, count( $application_denials ), 'Application denial evidence count' );
assert_same( 1, count( $rate_denials ), 'Rate-limit denial evidence count' );

$forbidden_fields = array( 'input', 'output', 'parameters', 'data', 'result' );
foreach ( $evidence as $event ) {
	foreach ( $forbidden_fields as $field ) {
		assert_false( array_key_exists( $field, $event ), 'Evidence contains raw field: ' . $field );
	}
}
assert_false(
	str_contains( wp_json_encode( $evidence ), 'Agentic Web Governance E2E' ),
	'Evidence serialized the Ability output.'
);
pass( 'Evidence proves one reservation per call and excludes raw input/output' );

$debug_output = (string) file_get_contents( $debug_log );
foreach ( array( 'PHP Fatal error', 'PHP Parse error', 'PHP Warning', 'PHP Notice', 'PHP Deprecated' ) as $php_problem ) {
	assert_false( str_contains( $debug_output, $php_problem ), 'WordPress debug log contains: ' . $php_problem );
}
pass( 'The accepted flow emits no PHP runtime warnings or fatal errors' );

echo "\nWordPress MCP governance E2E: ACCEPTED\n";

function initialize_params(): array {
	return array(
		'protocolVersion' => AWG_E2E_PROTOCOL,
		'capabilities'    => new stdClass(),
		'clientInfo'      => array(
			'name'    => 'agentic-web-governance-e2e',
			'version' => '0.1.0',
		),
	);
}

function initialize_session( array $auth, int $id ): string {
	$response = request(
		$auth,
		array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => 'initialize',
			'params'  => initialize_params(),
		)
	);
	assert_same( 200, $response['status'], 'Initialize HTTP status' );
	assert_same( AWG_E2E_PROTOCOL, $response['json']['result']['protocolVersion'] ?? null, 'Negotiated MCP protocol' );

	$session = $response['headers']['mcp-session-id'][0] ?? null;
	assert_true( is_string( $session ) && '' !== $session, 'Initialize response omitted Mcp-Session-Id.' );

	$notification = request(
		$auth,
		array(
			'jsonrpc' => '2.0',
			'method'  => 'notifications/initialized',
		),
		$session
	);
	assert_same( 202, $notification['status'], 'Initialized notification HTTP status' );

	return $session;
}

function mcp_call( array $auth, string $session, int $id, string $method, array $params = array() ): array {
	$response = request(
		$auth,
		array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => $method,
			'params'  => $params,
		),
		$session
	);
	assert_same( 200, $response['status'], $method . ' HTTP status' );
	assert_true( is_array( $response['json'] ), $method . ' did not return JSON.' );
	assert_false( isset( $response['json']['error'] ), $method . ' returned a JSON-RPC error.' );

	return $response['json'];
}

function call_tool( array $auth, string $session, int $id, string $tool, array $arguments ): array {
	return mcp_call(
		$auth,
		$session,
		$id,
		'tools/call',
		array(
			'name'      => $tool,
			'arguments' => $arguments,
		)
	);
}

function execute_reference_ability( array $auth, string $session, int $id ): array {
	return call_tool(
		$auth,
		$session,
		$id,
		'mcp-adapter-execute-ability',
		array(
			'ability_name' => AWG_E2E_ABILITY,
			'parameters'   => array(),
		)
	);
}

function request( ?array $auth, array $payload, ?string $session = null ): array {
	$headers = array(
		'Accept: application/json, text/event-stream',
		'Content-Type: application/json',
	);
	if ( null !== $session ) {
		$headers[] = 'Mcp-Session-Id: ' . $session;
		$headers[] = 'MCP-Protocol-Version: ' . AWG_E2E_PROTOCOL;
	}

	$response_headers = array();
	$handle = curl_init( AWG_E2E_ENDPOINT );
	curl_setopt_array(
		$handle,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode( $payload, JSON_UNESCAPED_SLASHES ),
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_HEADERFUNCTION => static function ( $curl, string $line ) use ( &$response_headers ): int {
				$length = strlen( $line );
				if ( ! str_contains( $line, ':' ) ) {
					return $length;
				}
				list( $name, $value ) = explode( ':', $line, 2 );
				$response_headers[ strtolower( trim( $name ) ) ][] = trim( $value );
				return $length;
			},
		)
	);
	if ( null !== $auth ) {
		curl_setopt( $handle, CURLOPT_USERPWD, $auth[0] . ':' . $auth[1] );
		curl_setopt( $handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
	}

	$body = curl_exec( $handle );
	if ( false === $body ) {
		$error = curl_error( $handle );
		curl_close( $handle );
		fail( 'HTTP request failed: ' . $error );
	}
	$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	curl_close( $handle );

	$json = '' === $body ? null : json_decode( $body, true );
	return array(
		'status'  => $status,
		'headers' => $response_headers,
		'body'    => $body,
		'json'    => $json,
	);
}

function reset_governance_state( int $admin_user_id ): void {
	delete_option( 'awg_reference_evidence' );
	delete_option( 'awg_reference_evidence_lock' );
	$bucket = 'wp-user:' . $admin_user_id . '|' . AWG_E2E_ABILITY;
	$digest = substr( hash( 'sha256', $bucket ), 0, 40 );
	delete_transient( 'awg_ref_rate_' . $digest );
	delete_option( 'awg_ref_rate_lock_' . $digest );
	wp_cache_delete( 'awg_reference_evidence', 'options' );
}

function events_of_type( array $events, string $type ): array {
	return array_values(
		array_filter(
			$events,
			static fn( array $event ): bool => $type === ( $event['event_type'] ?? null )
		)
	);
}

function tool_text( array $response ): string {
	return (string) ( $response['result']['content'][0]['text'] ?? '' );
}

function assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fail( $message );
	}
}

function assert_false( bool $condition, string $message ): void {
	assert_true( ! $condition, $message );
}

function assert_same( $expected, $actual, string $label ): void {
	if ( $expected !== $actual ) {
		fail( $label . ' expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.' );
	}
}

function assert_contains( string $needle, string $haystack, string $message ): void {
	assert_true( str_contains( $haystack, $needle ), $message . ' Response: ' . $haystack );
}

function pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}
