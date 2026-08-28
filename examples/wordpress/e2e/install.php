<?php
/**
 * Installs the isolated WordPress E2E database and test principals.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

const AWG_E2E_DB_NAME = 'agentic_web_governance_e2e';
const AWG_E2E_DB_USER = 'awg_e2e';
const AWG_E2E_URL     = 'http://127.0.0.1:8081';

$runtime_dir    = __DIR__ . '/runtime';
$wordpress_dir  = $runtime_dir . '/wordpress';
$credentials_file = $runtime_dir . '/credentials.json';
$config_file    = $wordpress_dir . '/wp-config.php';

if ( ! is_file( $wordpress_dir . '/wp-includes/version.php' ) ) {
	fail( 'WordPress is not extracted. Run setup.sh from its beginning.' );
}

foreach ( array( 'curl', 'mysqli', 'pdo_mysql' ) as $extension ) {
	if ( ! extension_loaded( $extension ) ) {
		fail( 'Required PHP extension is missing: ' . $extension );
	}
}

$credentials = read_credentials( $credentials_file );
$credentials += array(
	'database_password' => secret( 24 ),
	'admin_username'    => 'awg_admin',
	'admin_password'    => secret( 24 ),
	'admin_email'       => 'awg-admin@example.test',
	'blocked_username'  => 'awg_blocked',
	'blocked_password'  => secret( 24 ),
	'blocked_email'     => 'awg-blocked@example.test',
);

provision_database( $credentials['database_password'] );
// The runtime is wholly owned by this harness. Rewriting config on every setup
// keeps a retry consistent if an earlier run stopped after database creation.
write_wordpress_config( $config_file, $credentials['database_password'] );

define( 'WP_INSTALLING', true );
require $wordpress_dir . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wp_version;
if ( '7.1' !== $wp_version ) {
	fail( 'Expected WordPress 7.1, found ' . (string) $wp_version . '.' );
}

if ( ! is_blog_installed() ) {
	$result = wp_install(
		'Agentic Web Governance E2E',
		$credentials['admin_username'],
		$credentials['admin_email'],
		false,
		'',
		$credentials['admin_password']
	);

	if ( empty( $result['user_id'] ) ) {
		fail( 'WordPress installation did not create an administrator.' );
	}
}

$admin_id = upsert_user(
	$credentials['admin_username'],
	$credentials['admin_password'],
	$credentials['admin_email'],
	'administrator'
);
$blocked_id = upsert_user(
	$credentials['blocked_username'],
	$credentials['blocked_password'],
	$credentials['blocked_email'],
	''
);

foreach (
	array(
		'mcp-adapter/mcp-adapter.php',
		'agentic-web-governance-reference/agentic-web-governance-reference.php',
	) as $plugin
) {
	if ( ! is_plugin_active( $plugin ) ) {
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			fail( 'Could not activate ' . $plugin . ': ' . $result->get_error_message() );
		}
	}
}

$credentials['admin_user_id']       = $admin_id;
$credentials['blocked_user_id']     = $blocked_id;
$credentials['admin_app_password']  = create_application_password( $admin_id, 'AWG E2E administrator' );
$credentials['blocked_app_password'] = create_application_password( $blocked_id, 'AWG E2E blocked user' );

if ( false === file_put_contents(
	$credentials_file,
	json_encode( $credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n",
	LOCK_EX
) ) {
	fail( 'Could not write E2E credentials.' );
}

chmod( $credentials_file, 0600 );
echo "WordPress 7.1 database, plugins, and E2E principals are ready.\n";

function read_credentials( string $path ): array {
	if ( ! is_file( $path ) ) {
		return array();
	}

	$data = json_decode( (string) file_get_contents( $path ), true );
	if ( ! is_array( $data ) ) {
		fail( 'Existing E2E credentials are invalid JSON.' );
	}

	return $data;
}

function secret( int $bytes ): string {
	return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
}

function provision_database( string $password ): void {
	$root_password = getenv( 'AWG_E2E_MYSQL_ROOT_PASSWORD' );
	$root_password = false === $root_password ? '' : $root_password;

	try {
		$pdo = new PDO(
			'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
			'root',
			$root_password,
			array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
		);
	} catch ( Throwable $throwable ) {
		fail( 'Could not connect to MySQL as root: ' . $throwable->getMessage() );
	}

	$quoted_password = $pdo->quote( $password );
	$pdo->exec( 'CREATE DATABASE IF NOT EXISTS `' . AWG_E2E_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );

	// Create both local host variants because MySQL host resolution differs
	// between installations even when the client connects over 127.0.0.1.
	foreach ( array( 'localhost', '127.0.0.1' ) as $host ) {
		$account = "'" . AWG_E2E_DB_USER . "'@'" . $host . "'";
		$pdo->exec( 'CREATE USER IF NOT EXISTS ' . $account . ' IDENTIFIED BY ' . $quoted_password );
		$pdo->exec( 'ALTER USER ' . $account . ' IDENTIFIED BY ' . $quoted_password );
		$pdo->exec( 'GRANT ALL PRIVILEGES ON `' . AWG_E2E_DB_NAME . '`.* TO ' . $account );
	}
}

function write_wordpress_config( string $path, string $database_password ): void {
	$salts = '';
	foreach (
		array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		) as $constant
	) {
		$salts .= "define( '" . $constant . "', " . var_export( secret( 48 ), true ) . " );\n";
	}

	$config = "<?php\n"
		. "define( 'DB_NAME', '" . AWG_E2E_DB_NAME . "' );\n"
		. "define( 'DB_USER', '" . AWG_E2E_DB_USER . "' );\n"
		. "define( 'DB_PASSWORD', " . var_export( $database_password, true ) . " );\n"
		. "define( 'DB_HOST', '127.0.0.1:3306' );\n"
		. "define( 'DB_CHARSET', 'utf8mb4' );\n"
		. "define( 'DB_COLLATE', '' );\n\n"
		. $salts . "\n"
		. "\$table_prefix = 'awg_';\n\n"
		. "define( 'WP_ENVIRONMENT_TYPE', 'local' );\n"
		. "define( 'WP_DEBUG', true );\n"
		. "define( 'WP_DEBUG_LOG', true );\n"
		. "define( 'WP_DEBUG_DISPLAY', false );\n"
		. "define( 'WP_HOME', '" . AWG_E2E_URL . "' );\n"
		. "define( 'WP_SITEURL', '" . AWG_E2E_URL . "' );\n"
		. "define( 'AWG_E2E', true );\n\n"
		. "if ( ! defined( 'ABSPATH' ) ) {\n"
		. "\tdefine( 'ABSPATH', __DIR__ . '/' );\n"
		. "}\n\n"
		. "require_once ABSPATH . 'wp-settings.php';\n";

	if ( false === file_put_contents( $path, $config, LOCK_EX ) ) {
		fail( 'Could not write wp-config.php.' );
	}
	chmod( $path, 0600 );
}

function upsert_user( string $login, string $password, string $email, string $role ): int {
	$user = get_user_by( 'login', $login );
	if ( false === $user ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => $password,
				'user_email' => $email,
				'role'       => $role,
			)
		);
	} else {
		$user_id = wp_update_user(
			array(
				'ID'         => $user->ID,
				'user_pass'  => $password,
				'user_email' => $email,
			)
		);
	}

	if ( is_wp_error( $user_id ) ) {
		fail( 'Could not create E2E user ' . $login . ': ' . $user_id->get_error_message() );
	}

	$wp_user = new WP_User( (int) $user_id );
	$wp_user->set_role( $role );

	return (int) $user_id;
}

function create_application_password( int $user_id, string $name ): string {
	WP_Application_Passwords::delete_all_application_passwords( $user_id );
	$result = WP_Application_Passwords::create_new_application_password(
		$user_id,
		array(
			'name'   => $name,
			'app_id' => wp_generate_uuid4(),
		)
	);

	if ( is_wp_error( $result ) ) {
		fail( 'Could not create an E2E Application Password: ' . $result->get_error_message() );
	}

	return str_replace( ' ', '', $result[0] );
}

function fail( string $message ): void {
	fwrite( STDERR, "E2E setup failed: {$message}\n" );
	exit( 1 );
}
