<?php

namespace WcfaTests\Cases;

use Lkn\WcForceAuth\Includes\WcForceAuthCheckout;
use Lkn\WcForceAuth\Includes\WcForceAuthRedirectSettings;
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

	public function test_login_url_mode_account_is_default(): void {
		$checkout = $this->checkout();

		// No page holds the login screen: the account page is the login page.
		Wp::setLoginPage( 0 );
		Wp::setForceConfig( array( 'login_url_mode' => 'account', 'login_url' => 'https://x.test/ignored/' ) );
		$this->assertSame(
			get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
			$this->call( $checkout, 'get_login_page_url' ),
			'account mode uses the account page'
		);
	}

	/**
	 * A page that took the login screen over is where the form is: guests are
	 * sent straight to it (one redirect, not "account page then that page").
	 */
	public function test_login_url_mode_account_follows_the_chosen_login_page(): void {
		$checkout = $this->checkout();
		$page_id  = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Test login page',
				'post_name'    => 'test-login-page',
				'post_content' => '<!-- wp:paragraph --><p><!-- wp:lknform/login-register /--></p><!-- /wp:paragraph -->',
			)
		);

		Wp::setLoginPage( (int) $page_id );
		Wp::setForceConfig( array( 'login_url_mode' => 'account', 'login_url' => '' ) );

		$this->assertSame(
			get_permalink( $page_id ),
			$this->call( $checkout, 'get_login_page_url' ),
			'a chosen login page wins over the account page'
		);

		// A custom URL still wins over both.
		Wp::setForceConfig( array( 'login_url_mode' => 'custom', 'login_url' => 'https://x.test/login/' ) );
		$this->assertSame( 'https://x.test/login/', $this->call( $checkout, 'get_login_page_url' ), 'custom url wins' );

		Wp::setLoginPage( 0 );
		wp_delete_post( (int) $page_id, true );
	}

	public function test_login_url_mode_custom_uses_the_url(): void {
		$checkout = $this->checkout();

		Wp::setLoginPage( 0 );
		Wp::setForceConfig( array( 'login_url_mode' => 'custom', 'login_url' => 'https://x.test/custom-login/' ) );
		$this->assertSame( 'https://x.test/custom-login/', $this->call( $checkout, 'get_login_page_url' ), 'custom login url' );

		// Custom mode with an empty URL falls back to the account page.
		Wp::setForceConfig( array( 'login_url_mode' => 'custom', 'login_url' => '' ) );
		$this->assertSame(
			get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
			$this->call( $checkout, 'get_login_page_url' ),
			'custom mode empty -> account page'
		);
	}

	public function test_checkout_url_mode_auto_is_default(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig( array( 'checkout_url_mode' => 'auto', 'checkout_url' => 'https://x.test/ignored/' ) );
		$this->assertSame( wc_get_checkout_url(), $this->call( $checkout, 'get_checkout_page_url' ), 'auto mode uses wc_get_checkout_url()' );
	}

	public function test_checkout_url_mode_custom_uses_the_url(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig( array( 'checkout_url_mode' => 'custom', 'checkout_url' => 'https://x.test/custom-checkout/' ) );
		$this->assertSame( 'https://x.test/custom-checkout/', $this->call( $checkout, 'get_checkout_page_url' ), 'custom checkout url' );

		Wp::setForceConfig( array( 'checkout_url_mode' => 'custom', 'checkout_url' => '' ) );
		$this->assertSame( wc_get_checkout_url(), $this->call( $checkout, 'get_checkout_page_url' ), 'custom mode empty -> wc_get_checkout_url()' );
	}

	public function test_after_login_destination(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig(
			array(
				'checkout_url_mode' => 'custom',
				'checkout_url'      => 'https://x.test/custom-checkout/',
				'redirect_after'    => 'checkout',
			)
		);
		$this->assertSame( 'https://x.test/custom-checkout/', $this->call( $checkout, 'get_after_login_url' ), 'back to checkout' );

		Wp::setForceConfig( array( 'redirect_after' => 'account' ) );
		$this->assertSame( wc_get_account_endpoint_url( 'dashboard' ), $this->call( $checkout, 'get_after_login_url' ), 'account dashboard' );

		Wp::setForceConfig( array( 'redirect_after' => 'custom', 'redirect_custom' => 'https://x.test/thank-you/' ) );
		$this->assertSame( 'https://x.test/thank-you/', $this->call( $checkout, 'get_after_login_url' ), 'custom destination' );

		Wp::setForceConfig( array( 'redirect_after' => 'custom', 'redirect_custom' => '' ) );
		$this->assertSame( wc_get_account_endpoint_url( 'dashboard' ), $this->call( $checkout, 'get_after_login_url' ), 'custom empty -> account' );
	}

	public function test_after_login_previous_page_uses_the_stored_url(): void {
		$checkout = $this->checkout();

		Wp::setForceConfig( array( 'redirect_after' => 'previous' ) );

		// No stored page yet -> falls back to the account dashboard.
		unset( $_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] );
		$this->assertSame(
			wc_get_account_endpoint_url( 'dashboard' ),
			$this->call( $checkout, 'get_after_login_url' ),
			'previous with nothing stored -> account dashboard'
		);

		// A same-site stored page is used as the destination.
		$_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] = home_url( '/produto/camisa/' );
		$this->assertSame(
			home_url( '/produto/camisa/' ),
			$this->call( $checkout, 'get_after_login_url' ),
			'previous returns the stored page'
		);

		// A foreign-host value is rejected (no open redirect).
		$_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] = 'https://evil.example.com/steal';
		$this->assertSame(
			wc_get_account_endpoint_url( 'dashboard' ),
			$this->call( $checkout, 'get_after_login_url' ),
			'foreign host is rejected -> account dashboard'
		);

		unset( $_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] );
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
				'checkout_url_mode' => 'custom',
				'checkout_url'      => 'https://x.test/checkout/',
				'redirect_after'    => 'checkout',
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
