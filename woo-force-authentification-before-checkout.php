<?php
/*
Plugin Name: Force Authentification Before Checkout for WooCommerce
Description: Force customer to log in or register before checkout
Version: 1.5.0
Author: Link Nacional
Author URI: https://linknacional.com.br/

Requires Plugins: woocommerce

License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Text Domain: woo-force-authentification-before-checkout
Domain Path: /languages
*/

if ( ! defined( 'WPINC' ) ) die();

class WC_Force_Auth_Before_Checkout {

	const FILE = __FILE__;
	const URL_ARG = 'redirect_to_checkout';

	protected static $_instance = null;

	protected function __construct () {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	protected function is_woocommerce_installed () {
		return function_exists( 'WC' );
	}

	protected function has_query_param () {
		// REASON: read-only existence check of a redirect marker; no state is changed, so a nonce is not applicable.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ self::URL_ARG ] );
	}

	protected function get_login_page_url () {
		return apply_filters( 'wc_force_auth_login_page_url',
			get_permalink( get_option( 'woocommerce_myaccount_page_id' ) )
		);
	}

	protected function get_checkout_page_url () {
		return apply_filters( 'wc_force_auth_checkout_page_url', wc_get_checkout_url() );
	}

	public function init () {
		if ( ! $this->is_woocommerce_installed() ) {
			add_action( 'admin_notices', [ $this, 'add_admin_notice' ] );
			return;
		};

		add_action( 'admin_notices', [ $this, 'add_registration_disabled_notice' ] );

		add_action( 'template_redirect', [ $this, 'redirect_to_account_page' ] );
		add_action( 'wp_head', [ $this, 'add_wc_notice' ] );

		add_filter( 'woocommerce_registration_redirect', [ $this, 'redirect_to_checkout' ], 100 );
		add_filter( 'woocommerce_login_redirect', [ $this, 'redirect_to_checkout' ], 100 );
		add_action( 'wp_head', [ $this, 'redirect_to_checkout_via_html' ] );
	}

	public function redirect_to_account_page () {
		$condition = apply_filters(
			'wc_force_auth_redirect_to_account_page',
			is_checkout() && ! is_user_logged_in()
		);
		if( $condition ) {
			wp_safe_redirect( add_query_arg( self::URL_ARG, '', $this->get_login_page_url() ) );
			die;
		}
	}

	public function redirect_to_checkout_via_html () {
		if ( $this->has_query_param() && is_user_logged_in() ) {
			?>
			<meta
				http-equiv="Refresh"
				content="0; url='<?php echo esc_attr( $this->get_checkout_page_url() ); ?>'"
			/>
			<?php
			exit();
		}
	}

	public function redirect_to_checkout ( $redirect ) {
		if ( $this->has_query_param() ) {
			$redirect = $this->get_checkout_page_url();
		}
		return $redirect;
	}

	public function get_alert_message () {
		return apply_filters( 'wc_force_auth_message', __( 'Please log in or register to complete your purchase.', 'woo-force-authentification-before-checkout' ) );
	}

	public function add_wc_notice () {
		if ( ! is_user_logged_in() && is_account_page() && $this->has_query_param() ) {
			wc_add_notice( $this->get_alert_message(), 'notice' );
		}
	}

	public function add_admin_notice () {
		?>
		<div class="notice notice-error">
			<p>
				<?php echo esc_html__( 'You need install and activate the WooCommerce plugin.', 'woo-force-authentification-before-checkout' ) ?>
			</p>
		</div>
		<?php
	}

	protected function is_registration_enabled () {
		return 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' );
	}

	public function add_registration_disabled_notice () {
		if ( $this->is_registration_enabled() ) {
			return;
		}
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=account' );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php echo esc_html__( 'Force Authentification Before Checkout for WooCommerce:', 'woo-force-authentification-before-checkout' ); ?></strong>
				<?php
				printf(
					/* translators: %s: URL of the "Account & Privacy" settings page. */
					wp_kses_post( __( 'customer registration on the "My account" page is disabled. <a href="%s">Enable it</a> so customers can create an account before checkout.', 'woo-force-authentification-before-checkout' ) ),
					esc_url( $settings_url )
				); ?>
			</p>
		</div>
		<?php
	}

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
}

WC_Force_Auth_Before_Checkout::get_instance();
