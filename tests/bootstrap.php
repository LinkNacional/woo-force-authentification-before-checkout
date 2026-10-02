<?php

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.DB.DirectDatabaseQuery -- CLI test bootstrap; not shipped in the plugin release.

/**
 * Test bootstrap: loads the REAL WordPress + WooCommerce of the Local site so
 * the suite exercises the same database, options, plugins and mail (Mailpit)
 * the site actually uses.
 *
 * The WP root defaults to three levels above the plugin, i.e.
 * <root>/wp-content/plugins/<plugin> → <root>. Override with WC_FA_WP_ROOT.
 *
 * @package WcfaTests
 */

$plugin_dir = dirname( __DIR__ );
$wp_root    = getenv( 'WC_FA_WP_ROOT' );

if ( ! $wp_root ) {
	$wp_root = dirname( $plugin_dir, 3 );
}

$wp_load = rtrim( $wp_root, '/' ) . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Cannot find wp-load.php at {$wp_load}. Set WC_FA_WP_ROOT.\n" );
	exit( 2 );
}

// Some SQLite/db drop-ins and admin screens assume these exist under CLI.
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define( 'WC_FA_TESTS', true );

require $wp_load;

// ── Test library ─────────────────────────────────────────
require __DIR__ . '/lib/TestCase.php';
require __DIR__ . '/lib/Runner.php';
require __DIR__ . '/lib/Mailpit.php';
require __DIR__ . '/lib/Wp.php';
require __DIR__ . '/lib/WpTestCase.php';

if ( ! class_exists( 'Lkn\\WcForceAuth\\Includes\\WcForceAuthOtp' ) ) {
	fwrite( STDERR, "Plugin classes not loaded — is woo-force-authentification-before-checkout active?\n" );
	exit( 2 );
}
