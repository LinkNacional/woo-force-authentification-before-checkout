<?php

namespace Lkn\WcForceAuth\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-facing notices and helpers.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthAdmin {

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
	 * Notice shown when WooCommerce is not active.
	 *
	 * Hook: admin_notices.
	 */
	public function woocommerce_missing_notice() {
		if ( function_exists( 'WC' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php echo esc_html__( 'You need install and activate the WooCommerce plugin.', 'woo-force-authentification-before-checkout' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Notice shown when customer registration is disabled on the account page.
	 *
	 * Hook: admin_notices.
	 */
	public function registration_disabled_notice() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' ) ) {
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
				);
				?>
			</p>
		</div>
		<?php
	}
}
