<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page that centralizes the plugin's page-redirect flow.
 *
 * Both the Force Authentication module (redirecting guests to the account page
 * and sending them back to the checkout) and the OTP module (the post-login
 * destination) read their URLs from here. Keeping the redirect configuration in
 * one place avoids the two modules drifting apart and producing wrong redirects.
 *
 * The options keep the historical `wc_force_auth_` prefix so previously stored
 * Force Authentication values keep working.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthRedirectSettings extends WcForceAuthSettingsPage {

	/**
	 * Admin page slug (also the menu slug).
	 */
	public const PAGE_SLUG = 'wc-force-auth-settings';

	/**
	 * AJAX action / nonce action name.
	 */
	public const AJAX_ACTION = 'wc_force_auth_save_redirect_settings';

	/**
	 * Option-name prefix. Shared with the Force Authentication screen.
	 */
	public const OPTION_PREFIX = 'wc_force_auth_';

	/**
	 * Cookie that remembers the page the customer was on before logging in.
	 */
	public const RETURN_COOKIE = 'wcfa_return_url';

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
		return __( 'Settings', 'woo-force-authentification-before-checkout' );
	}

	/**
	 * Default values for every option.
	 *
	 * @return array<string,string>
	 */
	public static function defaults(): array {
		return array(
			'login_url_mode'    => 'account',
			'login_url'         => '',
			'checkout_url_mode' => 'auto',
			'checkout_url'      => '',
			'redirect_after'    => 'checkout',
			'redirect_custom'   => '',
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
			'urls_title' => array(
				'title'    => __( 'Pages', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'urls_section',
				'block_id' => 'urls',
			),
			$p . 'login_url_mode' => array(
				'title'             => __( 'Login page', 'woo-force-authentification-before-checkout' ),
				'type'              => 'select',
				'id'                => $p . 'login_url_mode',
				'options'           => array(
					'account' => __( 'WooCommerce My Account (automatic)', 'woo-force-authentification-before-checkout' ),
					'custom'  => __( 'Custom URL', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'account',
				'description'       => __( 'Page customers are sent to in order to authenticate.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Login page', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Where the guest is redirected to log in.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Choose "WooCommerce My Account" to use the account page set in WooCommerce, or "Custom URL" to point to your own login page.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'login_url' => array(
				'title'             => __( 'Login page URL', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'login_url',
				'default'           => '',
				'join'              => $p . 'login_url_mode',
				'show_when'         => 'custom',
				'block_title'       => __( 'Custom login URL', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Used only when "Custom URL" is selected above.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Full URL of your custom login page. Leave empty to fall back to the WooCommerce My Account page.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'checkout_url_mode' => array(
				'title'             => __( 'Checkout page', 'woo-force-authentification-before-checkout' ),
				'type'              => 'select',
				'id'                => $p . 'checkout_url_mode',
				'options'           => array(
					'auto'   => __( 'All checkout pages (automatic)', 'woo-force-authentification-before-checkout' ),
					'custom' => __( 'Custom URL', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'auto',
				'description'       => __( 'Page the customer is sent back to after authenticating.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Checkout page', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Where the logged-in customer returns to.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Choose "All checkout pages" to use the WooCommerce checkout page (works with the block and the shortcode checkout), or "Custom URL" for a specific page.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'checkout_url' => array(
				'title'             => __( 'Checkout page URL', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'checkout_url',
				'default'           => '',
				'join'              => $p . 'checkout_url_mode',
				'show_when'         => 'custom',
				'block_title'       => __( 'Custom checkout URL', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Used only when "Custom URL" is selected above.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Full URL of your custom checkout page. Leave empty to fall back to the WooCommerce checkout page.', 'woo-force-authentification-before-checkout' ),
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
					'previous' => __( 'Previous page (where the customer came from)', 'woo-force-authentification-before-checkout' ),
					'custom'   => __( 'Custom URL', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'checkout',
				'description'       => __( 'Where the customer lands right after authenticating (both the force-authentication and the OTP flows use this).', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Destination after login', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Landing page once the customer is authenticated.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( '"Back to the checkout" sends the customer to the checkout, "My account dashboard" to their account area, "Previous page" back to the page they were on before logging in (e.g. the product they were viewing), and "Custom URL" to a page you choose below.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'redirect_custom' => array(
				'title'             => __( 'Custom destination URL', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'redirect_custom',
				'default'           => '',
				'join'              => $p . 'redirect_after',
				'show_when'         => 'custom',
				'block_title'       => __( 'Custom destination URL', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Used only when "Custom URL" is selected above.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Full URL the customer is sent to after login. Leave empty to fall back to the account dashboard.', 'woo-force-authentification-before-checkout' ),
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
			case 'login_url':
			case 'checkout_url':
			case 'redirect_custom':
				return esc_url_raw( $value );

			case 'login_url_mode':
				return in_array( $value, array( 'account', 'custom' ), true ) ? $value : 'account';

			case 'checkout_url_mode':
				return in_array( $value, array( 'auto', 'custom' ), true ) ? $value : 'auto';

			case 'redirect_after':
				return in_array( $value, array( 'checkout', 'account', 'previous', 'custom' ), true ) ? $value : 'checkout';

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Read one of the shared redirect options.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback default.
	 * @return mixed
	 */
	public static function option( $key, $default = '' ) {
		$defaults = self::defaults();

		if ( '' === $default && isset( $defaults[ $key ] ) ) {
			$default = $defaults[ $key ];
		}

		return get_option( self::OPTION_PREFIX . $key, $default );
	}

	/**
	 * URL of the page customers are redirected to when they must authenticate.
	 *
	 * @return string
	 */
	public static function get_login_page_url() {
		$url = '';

		if ( 'custom' === self::option( 'login_url_mode', 'account' ) ) {
			$url = trim( (string) self::option( 'login_url', '' ) );
		}

		if ( '' === $url ) {
			$url = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public filter kept for backwards compatibility.
		return apply_filters( 'wc_force_auth_login_page_url', $url );
	}

	/**
	 * URL of the checkout page.
	 *
	 * @return string
	 */
	public static function get_checkout_page_url() {
		$url = '';

		if ( 'custom' === self::option( 'checkout_url_mode', 'auto' ) ) {
			$url = trim( (string) self::option( 'checkout_url', '' ) );
		}

		if ( '' === $url ) {
			$url = wc_get_checkout_url();
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public filter kept for backwards compatibility.
		return apply_filters( 'wc_force_auth_checkout_page_url', $url );
	}

	/**
	 * Where the customer lands right after authenticating.
	 *
	 * @return string
	 */
	public static function get_after_login_url() {
		switch ( self::option( 'redirect_after', 'checkout' ) ) {
			case 'account':
				$url = self::get_account_url();
				break;

			case 'previous':
				$previous = self::get_return_url();

				$url = '' !== $previous ? $previous : self::get_account_url();
				break;

			case 'custom':
				$custom = trim( (string) self::option( 'redirect_custom', '' ) );

				$url = '' !== $custom ? esc_url_raw( $custom ) : self::get_account_url();
				break;

			case 'checkout':
			default:
				$url = self::get_checkout_page_url();
				break;
		}

		/**
		 * Filters the URL the customer is sent to right after authenticating.
		 *
		 * Lets integrations render the OTP UI outside the account page (e.g. a
		 * login block on another page) and still honour their own destination.
		 *
		 * @param string $url Destination URL.
		 */
		return (string) apply_filters( 'wc_force_auth_after_login_url', $url );
	}

	/**
	 * URL of the customer's account dashboard.
	 *
	 * @return string
	 */
	protected static function get_account_url() {
		return function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : home_url();
	}

	/**
	 * Remember the page the customer was on so it can be used as the post-login
	 * destination ("Previous page").
	 *
	 * @param string $url Absolute URL to remember.
	 */
	public static function store_return_url( $url ) {
		$url = esc_url_raw( (string) $url );

		if ( '' === $url || headers_sent() ) {
			return;
		}

		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';

		setcookie( self::RETURN_COOKIE, $url, time() + 2 * HOUR_IN_SECONDS, $path, $domain, is_ssl(), false );
		$_COOKIE[ self::RETURN_COOKIE ] = $url;
	}

	/**
	 * The remembered "previous page", when it is a valid same-site URL.
	 *
	 * Only same-host URLs are trusted, so a tampered cookie cannot turn the
	 * redirect into an open redirect.
	 *
	 * @return string
	 */
	public static function get_return_url() {
		if ( empty( $_COOKIE[ self::RETURN_COOKIE ] ) ) {
			return '';
		}

		$url = esc_url_raw( wp_unslash( (string) $_COOKIE[ self::RETURN_COOKIE ] ) );

		if ( '' === $url ) {
			return '';
		}

		if ( wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return '';
		}

		return $url;
	}
}
