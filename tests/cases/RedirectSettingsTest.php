<?php

namespace WcfaTests\Cases;

use Lkn\WcForceAuth\Includes\WcForceAuthRedirectSettings;
use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * The shared "Settings" screen: the single source of truth for the redirect
 * flow (login page, checkout page and the post-login destination), plus the
 * "previous page" cookie helpers.
 */
class RedirectSettingsTest extends WpTestCase {

	public function test_screen_identity(): void {
		$page = new WcForceAuthRedirectSettings( 'x', '2' );

		$this->assertSame( 'wc-force-auth-settings', $page->get_page_slug(), 'page slug' );
		$this->assertSame( WcForceAuthRedirectSettings::AJAX_ACTION, $page->get_ajax_action(), 'ajax action' );
		$this->assertSame( 'wc_force_auth_', $page->get_option_prefix(), 'shared option prefix' );
		$this->assertNotEmpty( $page->get_page_title(), 'page title present' );
	}

	public function test_fields_cover_pages_and_after_login(): void {
		$page   = new WcForceAuthRedirectSettings( 'x', '2' );
		$fields = $page->get_fields();

		$ids = array();
		foreach ( $fields as $key => $field ) {
			$ids[] = $field['id'];
			$this->assertArrayHasKey( 'type', $field, "field {$key} declares a type" );
		}

		foreach ( array(
			'wc_force_auth_login_url_mode',
			'wc_force_auth_login_url',
			'wc_force_auth_checkout_url_mode',
			'wc_force_auth_checkout_url',
			'wc_force_auth_redirect_after',
			'wc_force_auth_redirect_custom',
		) as $expected ) {
			$this->assertTrue( in_array( $expected, $ids, true ), "field {$expected} exists" );
		}
	}

	public function test_login_page_url_modes(): void {
		$account = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );

		Wp::setForceConfig( array( 'login_url_mode' => 'account', 'login_url' => 'https://x.test/ignored/' ) );
		$this->assertSame( $account, WcForceAuthRedirectSettings::get_login_page_url(), 'account mode uses the account page' );

		Wp::setForceConfig( array( 'login_url_mode' => 'custom', 'login_url' => 'https://x.test/login/' ) );
		$this->assertSame( 'https://x.test/login/', WcForceAuthRedirectSettings::get_login_page_url(), 'custom url' );

		Wp::setForceConfig( array( 'login_url_mode' => 'custom', 'login_url' => '' ) );
		$this->assertSame( $account, WcForceAuthRedirectSettings::get_login_page_url(), 'custom empty -> account page' );
	}

	public function test_checkout_page_url_modes(): void {
		Wp::setForceConfig( array( 'checkout_url_mode' => 'auto', 'checkout_url' => 'https://x.test/ignored/' ) );
		$this->assertSame( wc_get_checkout_url(), WcForceAuthRedirectSettings::get_checkout_page_url(), 'auto mode' );

		Wp::setForceConfig( array( 'checkout_url_mode' => 'custom', 'checkout_url' => 'https://x.test/checkout/' ) );
		$this->assertSame( 'https://x.test/checkout/', WcForceAuthRedirectSettings::get_checkout_page_url(), 'custom url' );

		Wp::setForceConfig( array( 'checkout_url_mode' => 'custom', 'checkout_url' => '' ) );
		$this->assertSame( wc_get_checkout_url(), WcForceAuthRedirectSettings::get_checkout_page_url(), 'custom empty -> wc checkout' );
	}

	public function test_after_login_url_covers_every_destination(): void {
		$account = wc_get_account_endpoint_url( 'dashboard' );

		Wp::setForceConfig( array( 'checkout_url_mode' => 'custom', 'checkout_url' => 'https://x.test/checkout/', 'redirect_after' => 'checkout' ) );
		$this->assertSame( 'https://x.test/checkout/', WcForceAuthRedirectSettings::get_after_login_url(), 'checkout' );

		Wp::setForceConfig( array( 'redirect_after' => 'account' ) );
		$this->assertSame( $account, WcForceAuthRedirectSettings::get_after_login_url(), 'account' );

		Wp::setForceConfig( array( 'redirect_after' => 'custom', 'redirect_custom' => 'https://x.test/thanks/' ) );
		$this->assertSame( 'https://x.test/thanks/', WcForceAuthRedirectSettings::get_after_login_url(), 'custom' );

		Wp::setForceConfig( array( 'redirect_after' => 'custom', 'redirect_custom' => '' ) );
		$this->assertSame( $account, WcForceAuthRedirectSettings::get_after_login_url(), 'custom empty -> account' );

		// "previous" with nothing stored -> account dashboard.
		Wp::setForceConfig( array( 'redirect_after' => 'previous' ) );
		unset( $_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] );
		$this->assertSame( $account, WcForceAuthRedirectSettings::get_after_login_url(), 'previous empty -> account' );
	}

	public function test_return_url_cookie_is_stored_and_read(): void {
		unset( $_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] );

		$url = home_url( '/produto/camisa/' );
		WcForceAuthRedirectSettings::store_return_url( $url );

		$this->assertSame( $url, WcForceAuthRedirectSettings::get_return_url(), 'stored page is returned' );

		unset( $_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] );
	}

	public function test_return_url_rejects_foreign_hosts_and_empty(): void {
		$_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] = 'https://evil.example.com/steal';
		$this->assertSame( '', WcForceAuthRedirectSettings::get_return_url(), 'foreign host rejected' );

		$_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] = '/relative/path';
		$this->assertSame( '', WcForceAuthRedirectSettings::get_return_url(), 'relative path rejected' );

		$_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] = '';
		$this->assertSame( '', WcForceAuthRedirectSettings::get_return_url(), 'empty rejected' );

		unset( $_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] );
	}

	public function test_store_return_url_ignores_empty(): void {
		unset( $_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] );

		WcForceAuthRedirectSettings::store_return_url( '' );

		$this->assertFalse( isset( $_COOKIE[ WcForceAuthRedirectSettings::RETURN_COOKIE ] ), 'empty url is not stored' );
	}

	public function test_ajax_save_persists_the_shared_redirect_keys(): void {
		$page = new WcForceAuthRedirectSettings( 'x', '2' );

		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		wp_set_current_user( (int) ( $admins[0] ?? 1 ) );

		$_POST['settings']       = wp_json_encode(
			array(
				'wc_force_auth_login_url_mode' => 'custom',
				'wc_force_auth_login_url'      => 'https://x.test/login/',
				'wc_force_auth_redirect_after' => 'previous',
			)
		);
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( $page->get_ajax_action() );

		// wp_send_json() terminates via wp_die(), which would kill the CLI run;
		// intercept it so the handler can be exercised in-process.
		$signal = static function () {
			return static function ( $message ) {
				throw new \RuntimeException( (string) $message );
			};
		};

		add_filter( 'wp_die_handler', $signal );
		add_filter( 'wp_die_ajax_handler', $signal );
		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			$page->ajax_save_settings();
		} catch ( \RuntimeException $e ) {
			// Expected.
		}
		ob_end_clean();

		remove_all_filters( 'wp_die_handler' );
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_all_filters( 'wp_doing_ajax' );
		unset( $_POST['settings'], $_REQUEST['_ajax_nonce'] );

		$this->assertSame( 'custom', get_option( 'wc_force_auth_login_url_mode' ), 'login mode saved' );
		$this->assertSame( 'https://x.test/login/', get_option( 'wc_force_auth_login_url' ), 'login url saved' );
		$this->assertSame( 'previous', get_option( 'wc_force_auth_redirect_after' ), 'redirect_after saved' );
	}
}
