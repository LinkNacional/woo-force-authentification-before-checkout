<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthActivator {

	/**
	 * Run activation routines.
	 */
	public static function activate(): void {
		// Nothing to provision yet: settings fall back to sensible defaults.
		flush_rewrite_rules();
	}
}
