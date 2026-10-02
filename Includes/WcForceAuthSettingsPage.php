<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared base for the plugin's admin settings pages.
 *
 * Holds the common behavior (default options, layout rendering, asset
 * enqueuing and the AJAX save handler) so each concrete screen only declares
 * its own fields, defaults and sanitizer.
 *
 * @package Lkn\WcForceAuth
 */
abstract class WcForceAuthSettingsPage {

	/**
	 * The plugin name.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name The plugin name.
	 * @param string $version     The plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		$this->register_default_options();
	}

	/**
	 * The admin page slug for this screen.
	 *
	 * @return string
	 */
	abstract public function get_page_slug(): string;

	/**
	 * The AJAX action (and nonce action) name.
	 *
	 * @return string
	 */
	abstract public function get_ajax_action(): string;

	/**
	 * Option-name prefix for this screen's settings.
	 *
	 * @return string
	 */
	abstract public function get_option_prefix(): string;

	/**
	 * Default values for every option of this screen.
	 *
	 * @return array<string,string>
	 */
	abstract public function get_defaults(): array;

	/**
	 * Fields consumed by the layout template.
	 *
	 * @return array
	 */
	abstract public function get_fields(): array;

	/**
	 * Page title shown in the layout header.
	 *
	 * @return string
	 */
	abstract public function get_page_title(): string;

	/**
	 * Sanitize a single setting value according to its key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	abstract protected function sanitize_option( $key, $value );

	/**
	 * Provide defaults for every option so get_option() never returns false on
	 * a fresh install.
	 */
	private function register_default_options() {
		foreach ( $this->get_defaults() as $key => $value ) {
			$option_name = $this->get_option_prefix() . $key;

			add_filter(
				'default_option_' . $option_name,
				static function ( $default, $option, $passed_default ) use ( $value ) {
					if ( $passed_default && false !== $default ) {
						return $default;
					}

					return $value;
				},
				10,
				3
			);
		}
	}

	/**
	 * Render the settings page (menu callback).
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$this->enqueue_assets();

		$form_fields  = $this->get_fields();
		$method_title = $this->get_page_title();

		// Data for the sidebar promotional card.
		$wcfa_invoice_plugin_installed = $this->is_invoice_plugin_installed();
		$wcfa_install_url = wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=invoice-payment-for-woocommerce' ),
			'install-plugin_invoice-payment-for-woocommerce'
		);

		include WC_FORCE_AUTH_DIR . 'Includes/templates/WcForceAuthSettingsLayout.php';
	}

	/**
	 * Whether the "Invoice Payment for WooCommerce" companion plugin is
	 * installed.
	 *
	 * Mirrors the detection used by the sibling Link Nacional plugins (e.g.
	 * fraud-scam-detection-woocommerce): it checks the WordPress.org slug
	 * folder only (`invoice-payment-for-woocommerce/wc-invoice-payment.php`),
	 * which is where WordPress installs the plugin from the directory.
	 *
	 * @return bool
	 */
	private function is_invoice_plugin_installed() {
		return file_exists( trailingslashit( WP_PLUGIN_DIR ) . 'invoice-payment-for-woocommerce/wc-invoice-payment.php' );
	}

	/**
	 * Enqueue the admin CSS/JS for the settings page.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'wc-force-auth-sweetalert2',
			WC_FORCE_AUTH_URL . 'Includes/assets/sweetalert2/sweetalert2.min.css',
			array(),
			WcForceAuthOtp::SWEETALERT_VERSION
		);

		wp_enqueue_style(
			'wc-force-auth-admin-settings',
			WC_FORCE_AUTH_URL . 'Admin/css/wc-force-auth-admin-settings.css',
			array( 'wc-force-auth-sweetalert2' ),
			WC_FORCE_AUTH_VERSION
		);

		wp_enqueue_style(
			'wc-force-auth-admin-link-card',
			WC_FORCE_AUTH_URL . 'Admin/css/wc-force-auth-admin-setting-link-card.css',
			array( 'wc-force-auth-admin-settings' ),
			WC_FORCE_AUTH_VERSION
		);

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_script(
			'wc-force-auth-sweetalert2',
			WC_FORCE_AUTH_URL . 'Includes/assets/sweetalert2/sweetalert2.all.min.js',
			array(),
			WcForceAuthOtp::SWEETALERT_VERSION,
			true
		);

		wp_enqueue_script(
			'wc-force-auth-admin-save-fields',
			WC_FORCE_AUTH_URL . 'Admin/js/wc-force-auth-admin-save-fields.js',
			array( 'jquery', 'wc-force-auth-sweetalert2' ),
			WC_FORCE_AUTH_VERSION,
			true
		);

		wp_localize_script(
			'wc-force-auth-admin-save-fields',
			'wcForceAuthSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => $this->get_ajax_action(),
				'nonce'   => wp_create_nonce( $this->get_ajax_action() ),
				'prefix'  => $this->get_option_prefix(),
				'i18n'    => array(
					'saved'    => __( 'Settings saved successfully!', 'woo-force-authentification-before-checkout' ),
					'error'    => __( 'An error occurred while saving the settings.', 'woo-force-authentification-before-checkout' ),
					'security' => __( 'Security check failed. Please reload the page.', 'woo-force-authentification-before-checkout' ),
				),
			)
		);

		wp_enqueue_script(
			'wc-force-auth-admin-tabs',
			WC_FORCE_AUTH_URL . 'Admin/js/wc-force-auth-admin-tabs.js',
			array( 'jquery' ),
			WC_FORCE_AUTH_VERSION,
			true
		);
	}

	/**
	 * AJAX handler: persist this screen's settings.
	 *
	 * Hook: wp_ajax_<action>.
	 */
	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-force-authentification-before-checkout' ) ) );
		}

		check_ajax_referer( $this->get_ajax_action() );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded below; each value is sanitized per-field before persisting.
		$raw      = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
		$settings = json_decode( (string) $raw, true );

		if ( ! is_array( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings data.', 'woo-force-authentification-before-checkout' ) ) );
		}

		$allowed = array_keys( $this->get_defaults() );
		$prefix  = $this->get_option_prefix();

		foreach ( $settings as $name => $value ) {
			if ( ! is_string( $name ) || 0 !== strpos( $name, $prefix ) ) {
				continue;
			}

			$key = substr( $name, strlen( $prefix ) );

			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}

			update_option( $name, $this->sanitize_option( $key, $value ), false );
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully!', 'woo-force-authentification-before-checkout' ) ) );
	}
}
