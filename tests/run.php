<?php

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.DB.DirectDatabaseQuery -- CLI test runner; not shipped in the plugin release.

/**
 * Test runner entry point.
 *
 * Usage (prefer the wrapper `tests/run.sh`, which sets the PHP binary, MySQL
 * socket and Mailpit sendmail path):
 *   php tests/run.php [filter] [--verbose]
 *
 * @package WcfaTests
 */

use WcfaTests\Lib\Runner;
use WcfaTests\Lib\Wp;

// Buffer all output from the very start (before loading WP, since some plugins
// emit whitespace/BOM on load) so setcookie()/header() during login never warn
// about "headers already sent". The whole run is flushed at the end.
ob_start();

require __DIR__ . '/bootstrap.php';

$args    = array_slice( $argv, 1 );
$verbose = in_array( '--verbose', $args, true );
$filter  = trim( implode( ' ', array_filter( $args, static fn( $a ) => '--verbose' !== $a ) ) );

// Keep the real site's configuration intact: snapshot now, restore on exit
// (even on a fatal error) and clear the transient OTP state.
$snapshot = Wp::snapshotOptions();
Wp::clearOtpState();

register_shutdown_function(
	static function () use ( $snapshot ) {
		Wp::restoreOptions( $snapshot );
	}
);

echo "WordPress " . get_bloginfo( 'version' ) . " | WooCommerce " . ( defined( 'WC_VERSION' ) ? WC_VERSION : '?' ) . "\n";
echo 'Site: ' . get_option( 'siteurl' ) . "\n";
echo 'OTP test email: ' . get_option( 'admin_email' ) . "\n";

$runner = new Runner( __DIR__ . '/cases', $filter, $verbose );
$failures = $runner->run();

Wp::restoreOptions( $snapshot );

// Flush the buffered report.
while ( ob_get_level() > 0 ) {
	ob_end_flush();
}

exit( $failures > 0 ? 1 : 0 );
