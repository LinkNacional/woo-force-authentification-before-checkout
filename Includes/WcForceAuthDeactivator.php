<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthDeactivator {

	/**
	 * Run deactivation routines.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
