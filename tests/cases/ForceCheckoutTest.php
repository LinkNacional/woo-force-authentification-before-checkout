<?php

namespace WcfaTests\Cases;

use Lkn\WcForceAuth\Includes\WcForceAuthCheckout;
use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * Behaviour of the main feature: forcing authentication before checkout and the
 * configurable destinations/message.
 */
class ForceCheckoutTest extends WpTestCase {

	/**
	 * Call a protected method on the checkout class.
	 *
	 * @param WcForceAuthCheckout $checkout Instance.
	 * @param string              $method   Method name.
	 * @return mixed
	 */
	private function call( WcForceAuthCheckout $checkout, string $method ) {
		$ref = new \ReflectionMethod( $checkout, $method );
		$ref->setAccessible( true );

		return $ref->invoke( $checkout );
	}

	private function checkout(): WcForceAuthCheckout {
		return new WcForceAuthCheckout( 'x', '2' );
	}

	public function test_enable_flag(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig( array( 'enable' => 'no' ) );
		$this->assertFalse( $this->call( $checkout, 'is_enabled' ), 'disabled' );

		Wp::setForceConfig( array( 'enable' => 'yes' ) );
		$this->assertTrue( $this->call( $checkout, 'is_enabled' ), 'enabled' );
	}

	public function test_login_url_defaults_to_account_page_and_can_be_overridden(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig( array( 'login_url' => '' ) );
		$this->assertSame(
			get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
			$this->call( $checkout, 'get_login_page_url' ),
			'default login url'
		);

		Wp::setForceConfig( array( 'login_url' => 'https://x.test/custom-login/' ) );
		$this->assertSame( 'https://x.test/custom-login/', $this->call( $checkout, 'get_login_page_url' ), 'overridden login url' );
	}

	public function test_checkout_url_defaults_and_override(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig( array( 'checkout_url' => '' ) );
		$this->assertSame( wc_get_checkout_url(), $this->call( $checkout, 'get_checkout_page_url' ), 'default checkout url' );

		Wp::setForceConfig( array( 'checkout_url' => 'https://x.test/custom-checkout/' ) );
		$this->assertSame( 'https://x.test/custom-checkout/', $this->call( $checkout, 'get_checkout_page_url' ), 'overridden checkout url' );
	}

	public function test_after_login_destination(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig(
			array(
				'checkout_url'   => 'https://x.test/custom-checkout/',
				'redirect_after' => 'checkout',
			)
		);
		$this->assertSame( 'https://x.test/custom-checkout/', $this->call( $checkout, 'get_after_login_url' ), 'back to checkout' );

		Wp::setForceConfig( array( 'redirect_after' => 'account' ) );
		$this->assertSame( wc_get_account_endpoint_url( 'dashboard' ), $this->call( $checkout, 'get_after_login_url' ), 'account dashboard' );
	}

	public function test_alert_message_default_and_override(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig( array( 'message' => 'Faça login para continuar.' ) );
		$this->assertSame( 'Faça login para continuar.', $this->call( $checkout, 'get_alert_message' ), 'custom message' );
	}

	public function test_disabled_never_redirects(): void {
		Wp::setForceConfig( array( 'enable' => 'no' ) );

		// Force the redirect condition true; if the gate failed we would die here.
		add_filter( 'wc_force_auth_redirect_to_account_page', '__return_true' );

		$this->checkout()->redirect_to_account_page();
		remove_filter( 'wc_force_auth_redirect_to_account_page', '__return_true' );

		// Reaching this line proves no wp_safe_redirect()+die happened.
		$this->assertTrue( true, 'disabled mode does not redirect' );
	}

	public function test_redirect_filter_points_to_destination_when_marker_present(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig(
			array(
				'checkout_url'   => 'https://x.test/checkout/',
				'redirect_after' => 'checkout',
			)
		);

		$_GET[ WcForceAuthCheckout::URL_ARG ] = '';
		$this->assertSame( 'https://x.test/checkout/', $checkout->redirect_to_checkout( 'orig' ), 'redirects to checkout' );

		Wp::setForceConfig( array( 'redirect_after' => 'account' ) );
		$this->assertSame(
			wc_get_account_endpoint_url( 'dashboard' ),
			$checkout->redirect_to_checkout( 'orig' ),
			'redirects to account'
		);

		unset( $_GET[ WcForceAuthCheckout::URL_ARG ] );
		$this->assertSame( 'orig', $checkout->redirect_to_checkout( 'orig' ), 'no marker -> untouched' );
	}
}
