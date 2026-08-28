<?php
/**
 * Router for the local PHP server used by the WordPress E2E environment.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

$document_root = __DIR__ . '/runtime/wordpress';
$path          = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );

if ( is_string( $path ) && '/' !== $path && is_file( $document_root . $path ) ) {
	return false;
}

// The PHP development server has no rewrite module, so reproduce the REST
// rewrite that normally maps /wp-json/... to WordPress's rest_route query.
if ( is_string( $path ) && preg_match( '#^/wp-json(?:/(.*))?$#', $path, $matches ) ) {
	$_GET['rest_route'] = isset( $matches[1] ) ? '/' . $matches[1] : '/';
}

$_SERVER['SCRIPT_FILENAME'] = $document_root . '/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';

require $document_root . '/index.php';
