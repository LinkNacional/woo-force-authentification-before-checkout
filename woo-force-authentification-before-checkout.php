<?php
/*
Plugin Name: Force Authentification Before Checkout for WooCommerce
Description: Force customer to log in or register before checkout
Version: 1.4.6
Author: Luiz Bills
Author URI: https://luizpb.com/

Requires Plugins: woocommerce

License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Text Domain: wc-force-auth
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

		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );
		add_action( 'admin_notices', [ $this, 'add_donation_notice' ] );

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
		return apply_filters( 'wc_force_auth_message', __( 'Please log in or register to complete your purchase.', 'wc-force-auth' ) );
	}

	public function add_wc_notice () {
		if ( ! is_user_logged_in() && is_account_page() && $this->has_query_param() ) {
			wc_add_notice( $this->get_alert_message(), 'notice' );
		}
	}

	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wc-force-auth', false, dirname( plugin_basename( self::FILE ) ) . '/languages/' );
	}

	public function add_admin_notice () {
		?>
		<div class="notice notice-error">
			<p>
				<?php echo esc_html__( 'You need install and activate the WooCommerce plugin.', 'wc-force-auth' ) ?>
			</p>
		</div>
		<?php
	}

	public function add_donation_notice () {
		global $pagenow;
		$plugin_data = \get_plugin_data( __FILE__ );
		$plugin_name = $plugin_data['Name'];
		$prefix = 'wc_force_auth_';
		$cookie_name = $prefix . 'donation_notice_dismissed';

		if ( ! in_array( $pagenow, [ 'plugins.php', 'update-core.php' ] ) ) return;
		if ( isset( $_COOKIE[ $cookie_name ] ) ) return;

		//$notice_dismissed = (int) get_option( $prefix . 'donation_notice_dismissed' );
		$cookie_expires = time() + 6 * MONTH_IN_SECONDS;
		$cookie_expires *= 1000; // because javascript use milliseconds
		?>
		<div id="<?php echo esc_attr( $prefix ) ?>donation_notice" class="notice notice-info is-dismissible">
			<p>
				<?php printf(
					esc_html__( 'Thanks for using the %s plugin! Consider making a donation to help keep this plugin always up to date.', 'wc-force-auth' ),
					"<strong>$plugin_name</strong>"
				); ?>
			</p>
			<p>
				<a href="https://www.paypal.com/donate?hosted_button_id=29U8C2YV4BBQC&source=url" class="button button-primary">
					<?php echo esc_html__( 'Donate', 'wc-force-auth' ); ?>
				</a>
				<a href="https://wordpress.org/support/plugin/woo-force-authentification-before-checkout/reviews/#new-post" class="button">
					<?php echo esc_html__( 'Make a review', 'wc-force-auth' ); ?>
				</a>
			</p>
		</div>
		<script>
			window.jQuery(function ($) {
				const dismiss_selector = '#<?php echo $prefix ?>donation_notice .notice-dismiss';
				$(document).on('click', dismiss_selector, function (evt) {
					const date = new Date(); date.setTime(<?php echo $cookie_expires ?>);
        			const expires = "; expires=" + date.toUTCString();
					const cookie = "<?php echo $cookie_name ?>=1" + expires + "; path=<?php echo admin_url(); ?>; samesite; secure";
					document.cookie = cookie;
				});
			})
		</script>
		<?php
	}

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public static function activation () {
		$prefix = 'wc_force_auth_';
		delete_option( $prefix . 'donation_notice_dismissed' );
	}

	public static function deactivation () {
		$prefix = 'wc_force_auth_';
		$cookie_name = $prefix . 'donation_notice_dismissed';
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			unset( $_COOKIE[ $cookie_name ] );
			setcookie( $cookie_name, '', -1 );
		}
	}
}

WC_Force_Auth_Before_Checkout::get_instance();

register_activation_hook( __FILE__, [ WC_Force_Auth_Before_Checkout::class, 'activation' ] );
register_deactivation_hook( __FILE__, [ WC_Force_Auth_Before_Checkout::class, 'deactivation' ] );