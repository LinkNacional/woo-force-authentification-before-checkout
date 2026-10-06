<?php

namespace WcfaTests\Cases;

// phpcs:disable WordPress.Security.EscapeOutput -- CLI test case; not shipped in the plugin release.

use Lkn\WcForceAuth\Includes\WcForceAuthCheckoutSettings;
use Lkn\WcForceAuth\Includes\WcForceAuthRedirectSettings;
use Lkn\WcForceAuth\Includes\WcForceAuthSettingsPage;
use WcfaTests\Lib\WpTestCase;

/**
 * Raised in place of wp_die() so AJAX handlers can be exercised in-process.
 */
class WpdfExitSignal extends \Exception {
}

/**
 * The Force Authentication settings screen and the shared redirect settings
 * screen: defaults, sanitization, the AJAX save round-trip and the
 * companion-plugin detection.
 */
class ForceAuthSettingsTest extends WpTestCase {

	/**
	 * Invoke a settings object's AJAX save and return the decoded JSON response.
	 *
	 * @param WcForceAuthSettingsPage $page  Settings page.
	 * @param array                   $input Key => value payload.
	 * @return array
	 */
	private function invokeAjaxSave( WcForceAuthSettingsPage $page, array $input ): array {
		// Become an admin first: the nonce is bound to the current user, and the
		// capability check must pass. Order matters.
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		wp_set_current_user( (int) ( $admins[0] ?? 1 ) );

		$_POST['settings']       = wp_json_encode( $input );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( $page->get_ajax_action() );

		$signal = static function () {
			return static function ( $message ) {
				throw new WpdfExitSignal( (string) $message );
			};
		};

		add_filter( 'wp_die_handler', $signal );
		add_filter( 'wp_die_ajax_handler', $signal );
		// wp_send_json() calls die() directly unless this is "ajax"; forcing it
		// makes wp_send_json* go through wp_die(), which we can intercept.
		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();

		try {
			$page->ajax_save_settings();
		} catch ( WpdfExitSignal $e ) {
			// Expected: wp_send_json_* terminates via wp_die().
		}

		$output = ob_get_clean();

		remove_all_filters( 'wp_die_handler' );
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_all_filters( 'wp_doing_ajax' );

		unset( $_POST['settings'], $_REQUEST['_ajax_nonce'] );

		return (array) json_decode( (string) $output, true );
	}

	public function test_force_defaults(): void {
		$defaults = WcForceAuthCheckoutSettings::defaults();

		$this->assertSame( 'yes', $defaults['enable'], 'enabled by default' );
		$this->assertNotEmpty( $defaults['message'], 'default notice message' );
		$this->assertFalse( isset( $defaults['redirect_after'] ), 'redirect options live on the shared Settings screen' );
	}

	public function test_redirect_defaults(): void {
		$defaults = WcForceAuthRedirectSettings::defaults();

		$this->assertSame( 'account', $defaults['login_url_mode'], 'login page defaults to My Account' );
		$this->assertSame( 'auto', $defaults['checkout_url_mode'], 'checkout page defaults to automatic' );
		$this->assertSame( 'checkout', $defaults['redirect_after'], 'redirect back to checkout' );
	}

	public function test_force_sanitize_option_allowlists(): void {
		$page = new WcForceAuthCheckoutSettings( 'x', '2' );
		$ref  = new \ReflectionMethod( $page, 'sanitize_option' );
		$ref->setAccessible( true );

		$cases = array(
			array( 'enable', 'no', 'no' ),
			array( 'enable', 'yes', 'yes' ),
			array( 'enable', 'garbage', 'yes' ),
			array( 'message', 'Hello <b>world</b>', 'Hello world' ),
		);

		foreach ( $cases as $case ) {
			list( $key, $input, $expected ) = $case;
			$this->assertSame( $expected, $ref->invoke( $page, $key, $input ), "sanitize {$key}(" . $input . ')' );
		}
	}

	public function test_redirect_sanitize_option_allowlists(): void {
		$page = new WcForceAuthRedirectSettings( 'x', '2' );
		$ref  = new \ReflectionMethod( $page, 'sanitize_option' );
		$ref->setAccessible( true );

		$cases = array(
			array( 'redirect_after', 'account', 'account' ),
			array( 'redirect_after', 'checkout', 'checkout' ),
			array( 'redirect_after', 'previous', 'previous' ),
			array( 'redirect_after', 'bogus', 'checkout' ),
			array( 'login_url_mode', 'custom', 'custom' ),
			array( 'login_url_mode', 'bogus', 'account' ),
			array( 'checkout_url_mode', 'custom', 'custom' ),
			array( 'checkout_url_mode', 'bogus', 'auto' ),
			array( 'login_url', 'https://x.test/login/', 'https://x.test/login/' ),
			array( 'redirect_custom', 'https://x.test/thank-you/', 'https://x.test/thank-you/' ),
		);

		foreach ( $cases as $case ) {
			list( $key, $input, $expected ) = $case;
			$this->assertSame( $expected, $ref->invoke( $page, $key, $input ), "sanitize {$key}(" . $input . ')' );
		}
	}

	public function test_force_ajax_save_persists_only_its_keys(): void {
		$page = new WcForceAuthCheckoutSettings( 'x', '2' );

		// Capture the OTP option before: the save must not touch it.
		$otp_length_before = get_option( 'wc_force_auth_otp_code_length', '__MISSING__' );

		$response = $this->invokeAjaxSave(
			$page,
			array(
				'wc_force_auth_enable'         => 'no',
				'wc_force_auth_message'        => 'Login primeiro!',
				// Redirect options belong to the shared Settings screen now — the
				// Force save must ignore them.
				'wc_force_auth_login_url'      => 'https://x.test/custom-login/',
				'wc_force_auth_redirect_after' => 'account',
				// Not ours — must be ignored:
				'wc_force_auth_otp_code_length' => '8',
				'random_option'                => 'hacked',
			)
		);

		$this->assertTrue( ! empty( $response['success'] ), 'save reports success' );

		$this->assertSame( 'no', get_option( 'wc_force_auth_enable' ), 'enable saved' );
		$this->assertSame( 'Login primeiro!', get_option( 'wc_force_auth_message' ), 'message saved' );

		// Cross-prefix + unrelated keys must be untouched.
		$this->assertSame(
			$otp_length_before,
			get_option( 'wc_force_auth_otp_code_length', '__MISSING__' ),
			'otp key untouched by the force save'
		);
		$this->assertFalse( get_option( 'random_option', false ), 'unknown key ignored' );
	}

	public function test_redirect_ajax_save_persists_redirect_keys(): void {
		$page = new WcForceAuthRedirectSettings( 'x', '2' );

		$response = $this->invokeAjaxSave(
			$page,
			array(
				'wc_force_auth_login_url'      => 'https://x.test/custom-login/',
				'wc_force_auth_redirect_after' => 'account',
				// The Force screen's keys must be ignored here:
				'wc_force_auth_enable'         => 'no',
			)
		);

		$this->assertTrue( ! empty( $response['success'] ), 'save reports success' );

		$this->assertSame( 'https://x.test/custom-login/', get_option( 'wc_force_auth_login_url' ), 'login url saved' );
		$this->assertSame( 'account', get_option( 'wc_force_auth_redirect_after' ), 'redirect_after saved' );
	}

	public function test_invoice_detection_matches_fraud_slug_folder_only(): void {
		$page = new WcForceAuthCheckoutSettings( 'x', '2' );
		$ref  = new \ReflectionMethod( $page, 'is_invoice_plugin_installed' );
		$ref->setAccessible( true );

		$expected = file_exists( WP_PLUGIN_DIR . '/invoice-payment-for-woocommerce/wc-invoice-payment.php' );
		$this->assertSame( $expected, $ref->invoke( $page ), 'detection checks the wp.org slug folder only' );
	}
}
