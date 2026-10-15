<?php

namespace Lkn\WcForceAuth\Admin;

use Lkn\WcForceAuth\Includes\WcForceAuthCheckoutSettings;
use Lkn\WcForceAuth\Includes\WcForceAuthRedirectSettings;
use Lkn\WcForceAuth\Includes\WcForceAuthSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's admin menu on the WordPress sidebar.
 *
 * The top-level item is labelled "Authentification" and hovering it reveals
 * the "Force" submenu (the main feature: forcing authentication before
 * checkout), the "OTP" submenu and the "Settings" submenu (the shared
 * redirect configuration).
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
	 * Shared redirect settings page instance.
	 *
	 * @var WcForceAuthRedirectSettings
	 */
	private $redirect_settings;

	/**
	 * Constructor.
	 *
	 * @param WcForceAuthCheckoutSettings $force_settings    Force settings page.
	 * @param WcForceAuthSettings         $otp_settings      OTP settings page.
	 * @param WcForceAuthRedirectSettings $redirect_settings Shared redirect settings page.
	 */
	public function __construct( WcForceAuthCheckoutSettings $force_settings, WcForceAuthSettings $otp_settings, WcForceAuthRedirectSettings $redirect_settings ) {
		$this->force_settings    = $force_settings;
		$this->otp_settings      = $otp_settings;
		$this->redirect_settings = $redirect_settings;
	}

	/**
	 * Register the menu and its submenus.
	 *
	 * Hook: admin_menu.
	 */
	public function register(): void {
		$parent_title = __( 'Authentification', 'woo-force-authentification-before-checkout' );
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
		// on the main (Force Authentication) settings page. Keeping the slug equal
		// to the parent also stops WordPress from auto-inserting the parent as an
		// extra first submenu entry.
		add_submenu_page(
			$parent_slug,
			__( 'Force Authentification', 'woo-force-authentification-before-checkout' ),
			__( 'Force', 'woo-force-authentification-before-checkout' ),
			self::CAPABILITY,
			$parent_slug,
			$force_cb
		);

		// Second submenu: the shared redirect configuration used by both the Force
		// Authentication and the OTP modules.
		add_submenu_page(
			$parent_slug,
			__( 'Settings', 'woo-force-authentification-before-checkout' ),
			__( 'Settings', 'woo-force-authentification-before-checkout' ),
			self::CAPABILITY,
			WcForceAuthRedirectSettings::PAGE_SLUG,
			array( $this->redirect_settings, 'render_page' )
		);

		// Third submenu: the OTP module settings.
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
