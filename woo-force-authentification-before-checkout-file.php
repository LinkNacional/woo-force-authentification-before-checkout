<?php

/**
 * Plugin bootstrap: constants, PSR-4 autoloader, activation/deactivation and run.
 *
 * @package Lkn\WcForceAuth
 */

use Lkn\WcForceAuth\Includes\WcForceAuth;
use Lkn\WcForceAuth\Includes\WcForceAuthActivator;
use Lkn\WcForceAuth\Includes\WcForceAuthDeactivator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'WC_FORCE_AUTH_VERSION' ) ) {
	define( 'WC_FORCE_AUTH_VERSION', '1.6.0' );
}

if ( ! defined( 'WC_FORCE_AUTH_FILE' ) ) {
	define( 'WC_FORCE_AUTH_FILE', __DIR__ . '/woo-force-authentification-before-checkout.php' );
}

if ( ! defined( 'WC_FORCE_AUTH_DIR' ) ) {
	define( 'WC_FORCE_AUTH_DIR', plugin_dir_path( WC_FORCE_AUTH_FILE ) );
}

if ( ! defined( 'WC_FORCE_AUTH_URL' ) ) {
	define( 'WC_FORCE_AUTH_URL', plugin_dir_url( WC_FORCE_AUTH_FILE ) );
}

if ( ! defined( 'WC_FORCE_AUTH_BASENAME' ) ) {
	define( 'WC_FORCE_AUTH_BASENAME', plugin_basename( WC_FORCE_AUTH_FILE ) );
}

/**
 * Lightweight PSR-4 autoloader for the Lkn\WcForceAuth namespace.
 *
 * Keeps the plugin distributable without a Composer build step. The mapping
 * mirrors composer.json:
 *   Lkn\WcForceAuth\Includes\  -> Includes/
 *   Lkn\WcForceAuth\Admin\     -> Admin/
 *   Lkn\WcForceAuth\PublicView\ -> Public/
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Lkn\\WcForceAuth\\';
		$length = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class, $length ) ) {
			return;
		}

		$relative = substr( $class, $length );

		$map = array(
			'Includes\\'   => 'Includes/',
			'Admin\\'      => 'Admin/',
			'PublicView\\' => 'Public/',
		);

		foreach ( $map as $namespace => $dir ) {
			if ( 0 === strncmp( $namespace, $relative, strlen( $namespace ) ) ) {
				$file = __DIR__ . '/' . $dir . str_replace( '\\', '/', substr( $relative, strlen( $namespace ) ) ) . '.php';

				if ( is_file( $file ) ) {
					require_once $file;
				}

				return;
			}
		}
	}
);

register_activation_hook( WC_FORCE_AUTH_FILE, array( WcForceAuthActivator::class, 'activate' ) );
register_deactivation_hook( WC_FORCE_AUTH_FILE, array( WcForceAuthDeactivator::class, 'deactivate' ) );

/**
 * Begins execution of the plugin.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Bootstrap entry point, defined in the global namespace before the autoloaded classes are used.
function run_wc_force_auth(): void {
	$plugin = new WcForceAuth();
	$plugin->run();
}
run_wc_force_auth();
