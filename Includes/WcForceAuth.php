<?php

namespace Lkn\WcForceAuth\Includes;

use Lkn\WcForceAuth\Admin\WcForceAuthAdmin;
use Lkn\WcForceAuth\Admin\WcForceAuthMenu;
use Lkn\WcForceAuth\PublicView\WcForceAuthPublic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * Bootstraps dependencies and registers the admin and public hooks.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuth {

	/**
	 * The loader that maintains and registers all hooks.
	 *
	 * @var WcForceAuthLoader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->version     = defined( 'WC_FORCE_AUTH_VERSION' ) ? WC_FORCE_AUTH_VERSION : '1.0.0';
		$this->plugin_name = 'woo-force-authentification-before-checkout';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the dependencies and create the loader.
	 */
	private function load_dependencies() {
		$this->loader = new WcForceAuthLoader();
	}

	/**
	 * Register all admin hooks.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new WcForceAuthAdmin( $this->plugin_name, $this->version );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'woocommerce_missing_notice' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'registration_disabled_notice' );

		$force_settings = new WcForceAuthCheckoutSettings( $this->plugin_name, $this->version );
		$this->loader->add_action( 'wp_ajax_' . WcForceAuthCheckoutSettings::AJAX_ACTION, $force_settings, 'ajax_save_settings' );

		$settings = new WcForceAuthSettings( $this->plugin_name, $this->version );
		$this->loader->add_action( 'wp_ajax_' . WcForceAuthSettings::AJAX_ACTION, $settings, 'ajax_save_settings' );

		$menu = new WcForceAuthMenu( $force_settings, $settings );
		$this->loader->add_action( 'admin_menu', $menu, 'register' );

		$this->loader->add_filter( 'plugin_action_links_' . WC_FORCE_AUTH_BASENAME, $this, 'add_settings_link' );
	}

	/**
	 * Register all public-facing hooks.
	 */
	private function define_public_hooks() {
		// Force authentication before checkout (original behaviour, kept intact).
		$checkout = new WcForceAuthCheckout( $this->plugin_name, $this->version );
		$this->loader->add_action( 'template_redirect', $checkout, 'redirect_to_account_page' );
		$this->loader->add_action( 'wp_head', $checkout, 'add_wc_notice' );
		$this->loader->add_filter( 'woocommerce_registration_redirect', $checkout, 'redirect_to_checkout', 100 );
		$this->loader->add_filter( 'woocommerce_login_redirect', $checkout, 'redirect_to_checkout', 100 );
		$this->loader->add_action( 'wp_head', $checkout, 'redirect_to_checkout_via_html' );

		// Email OTP authentication.
		$otp = new WcForceAuthOtp( $this->plugin_name, $this->version );
		$this->loader->add_action( 'rest_api_init', $otp, 'register_endpoints' );

		// Public assets (OTP styles/scripts on the account page).
		$plugin_public = new WcForceAuthPublic( $otp );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Fully replace the account login/registration forms with the OTP UI by
		// serving our own myaccount/form-login.php template. The callback itself
		// checks whether OTP is enabled and whether the customer is logged out.
		$this->loader->add_filter( 'woocommerce_locate_template', $otp, 'locate_form_login_template', 99, 4 );

		// Safety net: keep WooCommerce's native registration blocked when the
		// passwordless (register and login) mode owns the account creation.
		$this->loader->add_filter( 'woocommerce_process_registration_errors', $otp, 'block_registration', 10, 4 );
	}

	/**
	 * Add a "Settings" link on the plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'admin.php?page=' . WcForceAuthCheckoutSettings::PAGE_SLUG );

		$links['settings'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Settings', 'woo-force-authentification-before-checkout' )
		);

		return $links;
	}

	/**
	 * Run the loader to execute all of the registered hooks.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Retrieve the plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
