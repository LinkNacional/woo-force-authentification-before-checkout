<?php

namespace Lkn\WcForceAuth\Admin;

use Lkn\WcForceAuth\Includes\WcForceAuthCheckoutSettings;
use Lkn\WcForceAuth\Includes\WcForceAuthSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's admin menu on the WordPress sidebar.
 *
 * The top-level item points at the plugin's main feature (forcing
 * authentication before checkout); hovering reveals the "Force
 * Authentification" and "OTP" submenus.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthMenu {

	/**
	 * Capability required to access the menu.
	 */
	private const CAPABILITY = 'manage_woocommerce';

	/**
	 * Force Authentication settings page instance.
	 *
	 * @var WcForceAuthCheckoutSettings
	 */
	private $force_settings;

	/**
	 * OTP settings page instance.
	 *
	 * @var WcForceAuthSettings
	 */
	private $otp_settings;

	/**
	 * Constructor.
	 *
	 * @param WcForceAuthCheckoutSettings $force_settings Force settings page.
	 * @param WcForceAuthSettings         $otp_settings   OTP settings page.
	 */
	public function __construct( WcForceAuthCheckoutSettings $force_settings, WcForceAuthSettings $otp_settings ) {
		$this->force_settings = $force_settings;
		$this->otp_settings   = $otp_settings;
	}

	/**
	 * Register the menu and its submenus.
	 *
	 * Hook: admin_menu.
	 */
	public function register(): void {
		$parent_title = __( 'Force Authentification', 'woo-force-authentification-before-checkout' );
		$parent_slug  = WcForceAuthCheckoutSettings::PAGE_SLUG;
		$force_cb     = array( $this->force_settings, 'render_page' );

		add_menu_page(
			$parent_title,
			$parent_title,
			self::CAPABILITY,
			$parent_slug,
			$force_cb,
			'dashicons-lock',
			56
		);

		// First submenu reuses the parent slug: clicking the top-level item lands
		// on the main (Force Authentication) settings page.
		add_submenu_page(
			$parent_slug,
			__( 'Force Authentification', 'woo-force-authentification-before-checkout' ),
			__( 'Force Authentification', 'woo-force-authentification-before-checkout' ),
			self::CAPABILITY,
			$parent_slug,
			$force_cb
		);

		// Second submenu: the OTP module settings.
		add_submenu_page(
			$parent_slug,
			__( 'Email OTP', 'woo-force-authentification-before-checkout' ),
			__( 'OTP', 'woo-force-authentification-before-checkout' ),
			self::CAPABILITY,
			WcForceAuthSettings::PAGE_SLUG,
			array( $this->otp_settings, 'render_page' )
		);
	}
}
