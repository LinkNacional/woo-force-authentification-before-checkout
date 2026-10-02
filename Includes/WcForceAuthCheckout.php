<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forces authentication before checkout: guests are redirected to the account
 * page, and after logging in/registering they are sent back to the checkout.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthCheckout {

	/**
	 * Query argument used as a "come back to checkout" marker.
	 */
	public const URL_ARG = 'redirect_to_checkout';

	/**
	 * The plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name The plugin name.
	 * @param string $version     The plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Read a Force Authentication option.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback default.
	 * @return mixed
	 */
	protected function option( $key, $default = '' ) {
		$defaults = WcForceAuthCheckoutSettings::defaults();

		if ( '' === $default && isset( $defaults[ $key ] ) ) {
			$default = $defaults[ $key ];
		}

		return get_option( WcForceAuthCheckoutSettings::OPTION_PREFIX . $key, $default );
	}

	/**
	 * Whether forcing authentication before checkout is enabled.
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		return 'yes' === $this->option( 'enable', 'yes' );
	}

	/**
	 * Whether the redirect marker is present in the current request.
	 *
	 * Read-only existence check of a redirect marker; no state changes.
	 *
	 * @return bool
	 */
	protected function has_query_param() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ self::URL_ARG ] );
	}

	/**
	 * URL of the page customers are redirected to when they must authenticate.
	 *
	 * @return string
	 */
	protected function get_login_page_url() {
		$url = (string) $this->option( 'login_url', '' );

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
	protected function get_checkout_page_url() {
		$url = (string) $this->option( 'checkout_url', '' );

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
	protected function get_after_login_url() {
		if ( 'account' === $this->option( 'redirect_after', 'checkout' ) ) {
			return wc_get_account_endpoint_url( 'dashboard' );
		}

		return $this->get_checkout_page_url();
	}

	/**
	 * Redirect guests away from the checkout to the account page.
	 *
	 * Hook: template_redirect.
	 */
	public function redirect_to_account_page() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$condition = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public filter kept for backwards compatibility.
			'wc_force_auth_redirect_to_account_page',
			is_checkout() && ! is_user_logged_in()
		);

		if ( $condition ) {
			wp_safe_redirect( add_query_arg( self::URL_ARG, '', $this->get_login_page_url() ) );
			die;
		}
	}

	/**
	 * Send authenticated customers to their destination when the marker is
	 * present.
	 *
	 * Hook: wp_head.
	 */
	public function redirect_to_checkout_via_html() {
		if ( $this->has_query_param() && is_user_logged_in() ) {
			?>
			<meta
				http-equiv="Refresh"
				content="0; url='<?php echo esc_attr( $this->get_after_login_url() ); ?>'"
			/>
			<?php
			exit();
		}
	}

	/**
	 * Point the WooCommerce login/registration redirect at the destination when
	 * the marker is present.
	 *
	 * @param string $redirect Original redirect URL.
	 * @return string
	 */
	public function redirect_to_checkout( $redirect ) {
		if ( $this->has_query_param() ) {
			$redirect = $this->get_after_login_url();
		}

		return $redirect;
	}

	/**
	 * Alert message shown on the account page when the customer is redirected.
	 *
	 * @return string
	 */
	public function get_alert_message() {
		$message = (string) $this->option( 'message', '' );

		if ( '' === $message ) {
			$message = __( 'Please log in or register to complete your purchase.', 'woo-force-authentification-before-checkout' );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public filter kept for backwards compatibility.
		return apply_filters( 'wc_force_auth_message', $message );
	}

	/**
	 * Add a WooCommerce notice on the account page when the customer is
	 * redirected from the checkout.
	 *
	 * Hook: wp_head.
	 */
	public function add_wc_notice() {
		if ( ! is_user_logged_in() && is_account_page() && $this->has_query_param() ) {
			wc_add_notice( $this->get_alert_message(), 'notice' );
		}
	}
}
