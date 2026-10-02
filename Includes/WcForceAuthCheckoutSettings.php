<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page for the plugin's main feature: forcing authentication
 * before checkout.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthCheckoutSettings extends WcForceAuthSettingsPage {

	/**
	 * Admin page slug (also the top-level menu slug).
	 */
	public const PAGE_SLUG = 'wc-force-auth';

	/**
	 * AJAX action / nonce action name.
	 */
	public const AJAX_ACTION = 'wc_force_auth_save_force_settings';

	/**
	 * Option-name prefix.
	 */
	public const OPTION_PREFIX = 'wc_force_auth_';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name The plugin name.
	 * @param string $version     The plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		parent::__construct( $plugin_name, $version );
	}

	/**
	 * Admin page slug.
	 *
	 * @return string
	 */
	public function get_page_slug(): string {
		return self::PAGE_SLUG;
	}

	/**
	 * AJAX action name.
	 *
	 * @return string
	 */
	public function get_ajax_action(): string {
		return self::AJAX_ACTION;
	}

	/**
	 * Option-name prefix.
	 *
	 * @return string
	 */
	public function get_option_prefix(): string {
		return self::OPTION_PREFIX;
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	public function get_page_title(): string {
		return __( 'Force Authentification', 'woo-force-authentification-before-checkout' );
	}

	/**
	 * Default values for every option.
	 *
	 * @return array<string,string>
	 */
	public static function defaults(): array {
		return array(
			'enable'         => 'yes',
			'message'        => __( 'Please log in or register to complete your purchase.', 'woo-force-authentification-before-checkout' ),
			'login_url'      => '',
			'checkout_url'   => '',
			'redirect_after' => 'checkout',
		);
	}

	/**
	 * Default values for every option (base contract).
	 *
	 * @return array
	 */
	public function get_defaults(): array {
		return self::defaults();
	}

	/**
	 * Fields consumed by the layout template.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		$p = self::OPTION_PREFIX;

		return array(
			'general_title' => array(
				'title'    => __( 'General', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'general_section',
				'block_id' => 'general',
			),
			$p . 'enable' => array(
				'title'             => __( 'Force authentication', 'woo-force-authentification-before-checkout' ),
				'type'              => 'checkbox',
				'id'                => $p . 'enable',
				'default'           => 'yes',
				'label'             => __( 'Require customers to log in or register before checkout.', 'woo-force-authentification-before-checkout' ),
				'description'       => __( 'When enabled, guests who reach the checkout are redirected to the account page to authenticate first.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Force authentication', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Turns the "login before checkout" requirement on or off.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Uncheck to let guests buy without an account. When checked, guests are sent to the account page before they can complete the purchase.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'message' => array(
				'title'             => __( 'Notice message', 'woo-force-authentification-before-checkout' ),
				'type'              => 'textarea',
				'id'                => $p . 'message',
				'default'           => __( 'Please log in or register to complete your purchase.', 'woo-force-authentification-before-checkout' ),
				'description'       => __( 'Message shown on the account page when a guest is redirected from the checkout.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Notice message', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Text displayed to the redirected customer.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Shown as a WooCommerce notice on the account page. If left empty, the default message is used.', 'woo-force-authentification-before-checkout' ),
			),

			'urls_title' => array(
				'title'    => __( 'Pages', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'urls_section',
				'block_id' => 'urls',
			),
			$p . 'login_url' => array(
				'title'             => __( 'Login page URL', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'login_url',
				'default'           => '',
				'description'       => __( 'Page customers are sent to in order to authenticate.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Login page URL', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Where the guest is redirected to log in.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Leave empty to use the WooCommerce "My account" page. Useful when you use a custom login page.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'checkout_url' => array(
				'title'             => __( 'Checkout page URL', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'checkout_url',
				'default'           => '',
				'description'       => __( 'Page the customer is sent back to after authenticating.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Checkout page URL', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Where the logged-in customer returns to.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Leave empty to use the WooCommerce checkout page. Useful when you use a custom checkout page.', 'woo-force-authentification-before-checkout' ),
			),

			'redirect_title' => array(
				'title'    => __( 'After login', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'redirect_section',
				'block_id' => 'redirect',
			),
			$p . 'redirect_after' => array(
				'title'             => __( 'Destination after login', 'woo-force-authentification-before-checkout' ),
				'type'              => 'select',
				'id'                => $p . 'redirect_after',
				'options'           => array(
					'checkout' => __( 'Back to the checkout', 'woo-force-authentification-before-checkout' ),
					'account'  => __( 'My account dashboard', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'checkout',
				'description'       => __( 'Where the customer lands right after authenticating.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Destination after login', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Landing page once the customer is authenticated.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( '"Back to the checkout" sends the customer to the checkout to finish the purchase. "My account dashboard" sends them to their account area instead.', 'woo-force-authentification-before-checkout' ),
			),
		);
	}

	/**
	 * Sanitize a single setting value according to its key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	protected function sanitize_option( $key, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		switch ( $key ) {
			case 'enable':
				return in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'yes';

			case 'message':
				return sanitize_textarea_field( $value );

			case 'login_url':
			case 'checkout_url':
				return esc_url_raw( $value );

			case 'redirect_after':
				return in_array( $value, array( 'checkout', 'account' ), true ) ? $value : 'checkout';

			default:
				return sanitize_text_field( $value );
		}
	}
}
