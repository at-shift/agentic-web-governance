<?php
/**
 * Verifies the read-only reference path against a deployed WordPress site.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

const AWG_LIVE_PROTOCOL = '2025-11-25';
const AWG_LIVE_ABILITY  = 'agentic-web-governance/site-summary';

$site_url     = require_environment( 'AWG_WORDPRESS_URL' );
$username     = require_environment( 'AWG_WORDPRESS_USER' );
$password_file = require_environment( 'AWG_WORDPRESS_APP_PASSWORD_FILE' );
$expected_name = optional_environment( 'AWG_EXPECTED_SITE_NAME' );
$expected_url  = optional_environment( 'AWG_EXPECTED_SITE_URL' );
$rate_limit    = optional_integer_environment( 'AWG_EXPECT_RATE_LIMIT' );

assert_safe_site_url( $site_url );

$password = read_secret_file( $password_file );
$endpoint = rtrim( $site_url, '/' ) . '/wp-json/mcp/mcp-adapter-default-server';
$auth     = array( $username, $password );
$session  = null;
$failed   = false;

try {
	$unauthenticated = request(
		$endpoint,
		null,
		'POST',
		array(
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'initialize',
			'params'  => initialize_params(),
		)
	);
	expect(
		in_array( $unauthenticated['status'], array( 401, 403 ), true ),
		'Unauthenticated MCP initialization was not rejected.'
	);
	expect(
		empty( $unauthenticated['headers']['mcp-session-id'] ),
		'Rejected initialization created a session.'
	);
	pass( 'Unauthenticated MCP initialization is rejected' );

	$principal = request(
		rtrim( $site_url, '/' ) . '/wp-json/wp/v2/users/me?context=edit',
		$auth,
		'GET'
	);
	if (
		401 === $principal['status']
		&& 'rest_not_logged_in' === ( $principal['json']['code'] ?? null )
	) {
		throw new RuntimeException(
			'WordPress REST received no authenticated principal. Verify that the '
			. 'Authorization header reaches PHP and that host-level REST API restrictions are disabled.'
		);
	}
	expect_status( 200, $principal, 'WordPress Application Password authentication' );
	expect(
		$username === ( $principal['json']['slug'] ?? null ),
		'Application Password authenticated an unexpected WordPress principal.'
	);
	pass( 'Application Password authenticates the expected WordPress principal' );

	$initialized = request(
		$endpoint,
		$auth,
		'POST',
		array(
			'jsonrpc' => '2.0',
			'id'      => 10,
			'method'  => 'initialize',
			'params'  => initialize_params(),
		)
	);
	expect_status( 200, $initialized, 'initialize' );
	expect(
		AWG_LIVE_PROTOCOL === ( $initialized['json']['result']['protocolVersion'] ?? null ),
		'MCP protocol negotiation returned an unexpected version.'
	);

	$session = $initialized['headers']['mcp-session-id'][0] ?? null;
	expect( is_string( $session ) && '' !== $session, 'Initialize omitted Mcp-Session-Id.' );

	$notification = request(
		$endpoint,
		$auth,
		'POST',
		array(
			'jsonrpc' => '2.0',
			'method'  => 'notifications/initialized',
		),
		$session
	);
	expect_status( 202, $notification, 'notifications/initialized' );
	pass( 'Authenticated MCP session initializes' );

	$tools      = mcp_call( $endpoint, $auth, $session, 11, 'tools/list' );
	$tool_names = array_column( $tools['result']['tools'] ?? array(), 'name' );
	foreach (
		array(
			'mcp-adapter-discover-abilities',
			'mcp-adapter-get-ability-info',
			'mcp-adapter-execute-ability',
		) as $expected_tool
	) {
		expect( in_array( $expected_tool, $tool_names, true ), 'Missing MCP tool: ' . $expected_tool );
	}
	pass( 'Default MCP Ability tools are listed' );

	$discovery = call_tool(
		$endpoint,
		$auth,
		$session,
		12,
		'mcp-adapter-discover-abilities',
		array()
	);
	expect( false === ( $discovery['result']['isError'] ?? true ), 'Ability discovery failed.' );
	$abilities = $discovery['result']['structuredContent']['abilities'] ?? array();
	expect(
		in_array( AWG_LIVE_ABILITY, array_column( $abilities, 'name' ), true ),
		'Reference Ability was not discoverable over MCP.'
	);
	pass( 'MCP-only reference Ability is discoverable' );

	// A configured rate-limit expectation intentionally consumes a fresh
	// principal's whole window so the deployed fail-closed boundary is proven.
	$execution_count = $rate_limit > 0 ? $rate_limit : 1;
	$first_summary   = null;
	for ( $index = 1; $index <= $execution_count; ++$index ) {
		$execution = execute_reference_ability( $endpoint, $auth, $session, 100 + $index );
		expect( false === ( $execution['result']['isError'] ?? true ), 'Authorized Ability call failed.' );
		$content = $execution['result']['structuredContent'] ?? array();
		expect( true === ( $content['success'] ?? false ), 'Ability did not report success.' );
		if ( null === $first_summary ) {
			$first_summary = $content['data'] ?? null;
		}
	}

	expect( is_array( $first_summary ), 'Ability did not return a site summary.' );
	if ( null !== $expected_name ) {
		expect( $expected_name === ( $first_summary['name'] ?? null ), 'Unexpected site name.' );
	}
	if ( null !== $expected_url ) {
		expect( $expected_url === ( $first_summary['url'] ?? null ), 'Unexpected site URL.' );
	}
	pass( 'Governed read-only Ability returns the expected site summary' );

	if ( $rate_limit > 0 ) {
		$limited = execute_reference_ability( $endpoint, $auth, $session, 101 + $rate_limit );
		expect( true === ( $limited['result']['isError'] ?? false ), 'Call beyond the rate limit was allowed.' );
		expect(
			false !== strpos( tool_text( $limited ), 'Governance policy denied' ),
			'Rate-limit denial was not returned through MCP.'
		);
		pass( 'Call beyond the configured rate limit is denied' );
	}

	echo "\nWordPress live MCP governance: ACCEPTED\n";
} catch ( Throwable $error ) {
	$failed = true;
	fwrite( STDERR, 'FAIL: ' . $error->getMessage() . "\n" );
} finally {
	// Session deletion is best-effort and never prints authentication material.
	if ( is_string( $session ) && '' !== $session ) {
		try {
			$closed = request( $endpoint, $auth, 'DELETE', null, $session );
			if ( ! in_array( $closed['status'], array( 200, 202, 204 ), true ) ) {
				fwrite( STDERR, 'WARN: MCP session cleanup returned HTTP ' . $closed['status'] . ".\n" );
			}
		} catch ( Throwable $cleanup_error ) {
			fwrite( STDERR, 'WARN: MCP session cleanup failed.\n' );
		}
	}

	// Drop the in-process copy as soon as all authenticated requests are done.
	$password = '';
	$auth     = array();
}

exit( $failed ? 1 : 0 );

function initialize_params(): array {
	return array(
		'protocolVersion' => AWG_LIVE_PROTOCOL,
		'capabilities'    => new stdClass(),
		'clientInfo'      => array(
			'name'    => 'agentic-web-governance-live-acceptance',
			'version' => '0.1.0',
		),
	);
}

function execute_reference_ability(
	string $endpoint,
	array $auth,
	string $session,
	int $id
): array {
	return call_tool(
		$endpoint,
		$auth,
		$session,
		$id,
		'mcp-adapter-execute-ability',
		array(
			'ability_name' => AWG_LIVE_ABILITY,
			'parameters'   => array(),
		)
	);
}

function call_tool(
	string $endpoint,
	array $auth,
	string $session,
	int $id,
	string $tool,
	array $arguments
): array {
	return mcp_call(
		$endpoint,
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

function mcp_call(
	string $endpoint,
	array $auth,
	string $session,
	int $id,
	string $method,
	array $params = array()
): array {
	$response = request(
		$endpoint,
		$auth,
		'POST',
		array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => $method,
			'params'  => $params,
		),
		$session
	);
	expect_status( 200, $response, $method );
	expect( is_array( $response['json'] ), $method . ' did not return JSON.' );
	expect( ! isset( $response['json']['error'] ), $method . ' returned a JSON-RPC error.' );

	return $response['json'];
}

function request(
	string $endpoint,
	?array $auth,
	string $method,
	?array $payload = null,
	?string $session = null
): array {
	$headers = array( 'Accept: application/json, text/event-stream' );
	if ( null !== $payload ) {
		$headers[] = 'Content-Type: application/json';
	}
	if ( null !== $session ) {
		$headers[] = 'Mcp-Session-Id: ' . $session;
		$headers[] = 'MCP-Protocol-Version: ' . AWG_LIVE_PROTOCOL;
	}

	$response_headers = array();
	$handle           = curl_init( $endpoint );
	$options          = array(
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 20,
		CURLOPT_HEADERFUNCTION => static function ( $curl, string $line ) use ( &$response_headers ): int {
			$length = strlen( $line );
			if ( false === strpos( $line, ':' ) ) {
				return $length;
			}
			list( $name, $value ) = explode( ':', $line, 2 );
			$response_headers[ strtolower( trim( $name ) ) ][] = trim( $value );
			return $length;
		},
	);
	if ( null !== $payload ) {
		$options[ CURLOPT_POSTFIELDS ] = json_encode( $payload, JSON_UNESCAPED_SLASHES );
	}
	if ( null !== $auth ) {
		$options[ CURLOPT_USERPWD ] = $auth[0] . ':' . $auth[1];
		$options[ CURLOPT_HTTPAUTH ] = CURLAUTH_BASIC;
	}
	curl_setopt_array( $handle, $options );

	$body = curl_exec( $handle );
	if ( false === $body ) {
		$error = curl_error( $handle );
		curl_close( $handle );
		throw new RuntimeException( 'HTTP request failed: ' . $error );
	}
	$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	curl_close( $handle );

	return array(
		'status'  => $status,
		'headers' => $response_headers,
		'json'    => '' === $body ? null : json_decode( $body, true ),
	);
}

function read_secret_file( string $path ): string {
	expect( is_file( $path ) && is_readable( $path ), 'Application Password file is not readable.' );
	$secret = trim( (string) file_get_contents( $path ) );
	expect( '' !== $secret, 'Application Password file is empty.' );
	return $secret;
}

function assert_safe_site_url( string $url ): void {
	$parts = parse_url( $url );
	expect( is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ), 'WordPress URL is invalid.' );
	$is_local = in_array( strtolower( $parts['host'] ), array( '127.0.0.1', 'localhost', '::1' ), true );
	expect( 'https' === strtolower( $parts['scheme'] ) || $is_local, 'Live credentials require HTTPS.' );
}

function require_environment( string $name ): string {
	$value = getenv( $name );
	expect( is_string( $value ) && '' !== trim( $value ), 'Missing environment variable: ' . $name );
	return trim( $value );
}

function optional_environment( string $name ): ?string {
	$value = getenv( $name );
	return is_string( $value ) && '' !== $value ? $value : null;
}

function optional_integer_environment( string $name ): int {
	$value = optional_environment( $name );
	if ( null === $value ) {
		return 0;
	}
	expect( ctype_digit( $value ) && (int) $value > 0, $name . ' must be a positive integer.' );
	return (int) $value;
}

function expect_status( int $expected, array $response, string $label ): void {
	expect(
		$expected === ( $response['status'] ?? null ),
		$label . ' expected HTTP ' . $expected . ', got ' . ( $response['status'] ?? 'none' ) . '.'
	);
}

function tool_text( array $response ): string {
	return (string) ( $response['result']['content'][0]['text'] ?? '' );
}

function expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function pass( string $message ): void {
	echo 'PASS: ' . $message . "\n";
}
