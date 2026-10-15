<?php

namespace WcfaTests\Cases;

use Lkn\WcForceAuth\Includes\WcForceAuthOtp;
use Lkn\WcForceAuth\Includes\WcForceAuthRedirectSettings;
use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * Behaviour of the OTP module that is not tied to the REST flow or to the code
 * layout: mode resolution, the mode-aware default texts, the branding colors
 * and the native-registration blocking.
 */
class OtpBehaviorTest extends WpTestCase {

	/**
	 * Run a callback with WooCommerce registration toggled, restoring the
	 * original value afterwards (the plugin only snapshots its own options).
	 *
	 * @param string   $value    'yes'|'no'.
	 * @param callable $callback Assertions to run.
	 */
	private function withRegistration( string $value, callable $callback ): void {
		$original = get_option( 'woocommerce_enable_myaccount_registration', 'no' );
		update_option( 'woocommerce_enable_myaccount_registration', $value, false );

		try {
			$callback();
		} finally {
			update_option( 'woocommerce_enable_myaccount_registration', $original, false );
		}
	}

	private function otp(): WcForceAuthOtp {
		return new WcForceAuthOtp( 'x', '2' );
	}

	public function test_mode_resolution_and_helpers(): void {
		Wp::setOtpConfig( array( 'enable_type' => 'disabled' ) );
		$otp = $this->otp();
		$this->assertSame( 'disabled', $otp->get_mode(), 'disabled mode' );
		$this->assertFalse( $otp->is_enabled(), 'disabled -> not enabled' );

		Wp::setOtpConfig( array( 'enable_type' => 'login_only' ) );
		$otp = $this->otp();
		$this->assertTrue( $otp->is_enabled(), 'login_only -> enabled' );
		$this->assertTrue( $otp->is_login_only_mode(), 'login_only flag' );
		$this->assertFalse( $otp->is_full_mode(), 'login_only -> not full' );

		$this->withRegistration(
			'yes',
			function () {
				Wp::setOtpConfig( array( 'enable_type' => 'register_and_login' ) );
				$otp = $this->otp();
				$this->assertSame( 'register_and_login', $otp->get_mode(), 'full mode' );
				$this->assertTrue( $otp->is_full_mode(), 'full flag' );
			}
		);
	}

	public function test_full_mode_downgrades_when_wc_registration_is_disabled(): void {
		$this->withRegistration(
			'no',
			function () {
				Wp::setOtpConfig( array( 'enable_type' => 'register_and_login' ) );
				$otp = $this->otp();
				$this->assertSame( 'login_only', $otp->get_mode(), 'register_and_login downgraded to login_only' );
				$this->assertFalse( $otp->is_full_mode(), 'not full when WC registration off' );
			}
		);
	}

	public function test_invalid_mode_falls_back_to_disabled(): void {
		Wp::setOtpConfig( array( 'enable_type' => 'something-weird' ) );
		$this->assertSame( 'disabled', $this->otp()->get_mode(), 'unknown mode -> disabled' );
	}

	public function test_should_show_native_registration(): void {
		$this->withRegistration(
			'yes',
			function () {
				Wp::setOtpConfig( array( 'enable_type' => 'login_only' ) );
				$this->assertTrue( $this->otp()->should_show_native_registration(), 'login_only + WC registration -> show' );

				Wp::setOtpConfig( array( 'enable_type' => 'register_and_login' ) );
				$this->assertFalse( $this->otp()->should_show_native_registration(), 'full mode -> never shows native registration' );
			}
		);

		$this->withRegistration(
			'no',
			function () {
				Wp::setOtpConfig( array( 'enable_type' => 'login_only' ) );
				$this->assertFalse( $this->otp()->should_show_native_registration(), 'WC registration off -> hidden' );
			}
		);
	}

	public function test_login_subheading_default_is_mode_aware(): void {
		Wp::setOtpConfig( array( 'enable_type' => 'login_only' ) );
		$login_only = WcForceAuthOtp::default_for( 'login_subheading' );
		$this->assertSame( $login_only, WcForceAuthOtp::default_for( 'login_subheading' ), 'default_for is stable within a mode' );

		Wp::setOtpConfig( array( 'enable_type' => 'register_and_login' ) );
		$full = WcForceAuthOtp::default_for( 'login_subheading' );

		$this->assertFalse( $login_only === $full, 'the login subheading differs between modes' );
	}

	public function test_normalize_stored_value_keeps_custom_and_resets_defaults(): void {
		Wp::setOtpConfig( array( 'enable_type' => 'login_only' ) );

		// A value equal to a known default (e.g. the register-and-login wording)
		// must follow the current mode's default.
		$base = WcForceAuthOtp::defaults()['login_subheading'];
		$this->assertSame(
			WcForceAuthOtp::default_for( 'login_subheading' ),
			WcForceAuthOtp::normalize_stored_value( 'login_subheading', $base ),
			'known default -> mode-aware default'
		);

		// A real custom text is returned untouched.
		$this->assertSame(
			'Faca login com seu e-mail',
			WcForceAuthOtp::normalize_stored_value( 'login_subheading', 'Faca login com seu e-mail' ),
			'custom text preserved'
		);

		// An empty value falls back to the default.
		$this->assertSame(
			WcForceAuthOtp::default_for( 'login_heading' ),
			WcForceAuthOtp::normalize_stored_value( 'login_heading', '' ),
			'empty -> default'
		);
	}

	public function test_primary_color_validates_hex(): void {
		Wp::setOtpConfig( array( 'primary_color' => '#FF0000' ) );
		$this->assertSame( '#FF0000', $this->otp()->get_primary_color(), 'valid hex kept' );

		Wp::setOtpConfig( array( 'primary_color' => 'not-a-color' ) );
		$this->assertSame( '#22C1DC', $this->otp()->get_primary_color(), 'invalid hex -> default' );
	}

	public function test_contrast_helpers_pick_readable_colors(): void {
		$otp = $this->otp();

		// On a light surface the text must be dark; on a dark surface, light.
		$this->assertSame( '#121519', $otp->contrast_for( '#ffffff' ), 'light bg -> dark text' );
		$this->assertSame( '#f5f7fa', $otp->contrast_for( '#000000' ), 'dark bg -> light text' );
		$this->assertSame( '#6b7280', $otp->muted_for( '#ffffff' ), 'light bg -> muted grey' );
		$this->assertSame( '#c9cdd3', $otp->muted_for( '#000000' ), 'dark bg -> muted light grey' );
	}

	public function test_primary_contrast_and_ink(): void {
		Wp::setOtpConfig( array( 'primary_color' => '#000000' ) );
		$dark = $this->otp();
		$this->assertSame( '#ffffff', $dark->get_primary_contrast(), 'dark primary -> white text' );
		$this->assertSame( '#000000', $dark->get_primary_ink(), 'dark primary -> ink stays the same' );

		Wp::setOtpConfig( array( 'primary_color' => '#ffffff' ) );
		$light = $this->otp();
		$this->assertSame( '#111111', $light->get_primary_contrast(), 'light primary -> dark text' );
		$this->assertFalse( '#ffffff' === $light->get_primary_ink(), 'light primary -> ink is darkened' );
	}

	public function test_block_registration_only_in_full_mode(): void {
		Wp::setOtpConfig( array( 'enable_type' => 'login_only' ) );
		$errors = new \WP_Error();
		$this->otp()->block_registration( $errors, 'user', 'pass', 'x@wpengine.local' );
		$this->assertFalse( $errors->has_errors(), 'login_only does not block native registration' );

		$this->withRegistration(
			'yes',
			function () {
				Wp::setOtpConfig( array( 'enable_type' => 'register_and_login' ) );
				$errors = new \WP_Error();
				$this->otp()->block_registration( $errors, 'user', 'pass', 'x@wpengine.local' );
				$this->assertTrue( $errors->has_errors(), 'full mode blocks native registration' );
				$this->assertTrue( in_array( 'wcfa_registration_blocked', $errors->get_error_codes(), true ), 'specific error code added' );
			}
		);
	}

	public function test_redirect_url_uses_the_shared_configuration(): void {
		Wp::setForceConfig( array( 'redirect_after' => 'account' ) );
		$this->assertSame(
			WcForceAuthRedirectSettings::get_after_login_url(),
			$this->otp()->get_redirect_url(),
			'OTP post-login destination matches the shared Settings screen'
		);

		Wp::setForceConfig( array( 'checkout_url_mode' => 'custom', 'checkout_url' => 'https://x.test/checkout/', 'redirect_after' => 'checkout' ) );
		$this->assertSame( 'https://x.test/checkout/', $this->otp()->get_redirect_url(), 'OTP follows the shared checkout destination' );
	}
}
