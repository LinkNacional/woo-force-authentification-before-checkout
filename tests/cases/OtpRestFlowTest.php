<?php

namespace WcfaTests\Cases;

use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * The OTP REST endpoints: permission, happy path, rate limiting and the code
 * verification flow.
 */
class OtpRestFlowTest extends WpTestCase {

	public function test_disabled_mode_blocks_endpoints(): void {
		Wp::setOtpConfig( array( 'enable_type' => 'disabled' ) );

		$response = $this->sendCode( 'someone@wpengine.local' );

		$this->assertSame( 403, $response->get_status(), 'disabled mode -> 403' );
	}

	public function test_missing_nonce_is_forbidden(): void {
		Wp::setOtpConfig( array( 'enable_type' => 'register_and_login' ) );

		$response = $this->sendCode( 'someone@wpengine.local', 'not-a-valid-nonce' );

		$this->assertSame( 403, $response->get_status(), 'bad nonce -> 403' );
	}

	public function test_login_only_rejects_unregistered_email(): void {
		Wp::setOtpConfig( array( 'enable_type' => 'login_only' ) );

		$unknown  = 'wcfa-unknown-' . uniqid() . '@wpengine.local';
		$response = $this->sendCode( $unknown );

		$this->assertSame( 400, $response->get_status(), 'unknown email in login_only -> 400' );
		$this->assertContains( 'not registered', strtolower( (string) $response->get_data()['message'] ), 'message explains it' );
	}

	public function test_send_returns_expiration_and_resend_metadata(): void {
		Wp::setOtpConfig(
			array(
				'enable_type'      => 'register_and_login',
				'expiration_time'  => '10',
				'resend_interval'  => '30',
			)
		);

		$data = $this->sendCode( $this->trackEmail( 'wcfa-meta-' . uniqid() . '@wpengine.local' ) )->get_data();

		$this->assertTrue( ! empty( $data['success'] ), 'success flag' );
		$this->assertSame( 600, (int) $data['expires_in'], 'expires_in = 10 min' );
		$this->assertSame( 30, (int) $data['resend_in'], 'resend_in = 30s' );
		$this->assertNotEmpty( $data['expires_label'], 'expires_label present' );
	}

	public function test_second_send_is_rate_limited(): void {
		$email = $this->trackEmail( 'wcfa-rate-' . uniqid() . '@wpengine.local' );

		Wp::setOtpConfig(
			array(
				'enable_type'     => 'register_and_login',
				'resend_interval' => '60',
			)
		);

		$this->assertSame( 200, $this->sendCode( $email )->get_status(), 'first send ok' );

		$second = $this->sendCode( $email );
		$this->assertSame( 429, $second->get_status(), 'second send rate limited' );

		$data = $second->get_data();
		$this->assertArrayHasKey( 'resend_in', $data, 'rate limit reports resend_in' );
		$this->assertGreaterThan( 0, (int) $data['resend_in'], 'resend_in is positive' );
	}

	public function test_wrong_code_is_rejected_and_right_code_accepted(): void {
		$email   = $this->trackEmail( 'wcfa-verify-' . uniqid() . '@wpengine.local' );
		$subject = $this->useUniqueSubject();

		Wp::setOtpConfig(
			array(
				'enable_type'   => 'register_and_login',
				'code_length'   => '6',
				'email_subject' => $subject,
			)
		);

		$this->assertSame( 200, $this->sendCode( $email )->get_status(), 'send ok' );

		$code = $this->waitForEmailedCode( $subject, 6 );
		$this->assertNotEmpty( $code, 'got the real code' );

		// Build a guaranteed-wrong code (flip the last digit).
		$wrong = substr( (string) $code, 0, 5 ) . ( ( (int) $code[5] + 1 ) % 10 );

		$bad = $this->verifyCode( $email, (string) $wrong );
		$this->assertSame( 400, $bad->get_status(), 'wrong code -> 400' );
		$this->assertFalse( ! empty( $bad->get_data()['success'] ), 'wrong code not successful' );

		$ok = $this->verifyCode( $email, (string) $code );
		$this->assertSame( 200, $ok->get_status(), 'right code -> 200' );
		$this->assertTrue( ! empty( $ok->get_data()['success'] ), 'right code success' );
	}

	public function test_code_is_single_use(): void {
		$email   = $this->trackEmail( 'wcfa-reuse-' . uniqid() . '@wpengine.local' );
		$subject = $this->useUniqueSubject();

		Wp::setOtpConfig(
			array(
				'enable_type'   => 'register_and_login',
				'code_length'   => '6',
				'email_subject' => $subject,
			)
		);

		$this->assertSame( 200, $this->sendCode( $email )->get_status(), 'send ok' );
		$code = $this->waitForEmailedCode( $subject, 6 );
		$this->assertNotEmpty( $code, 'got the code' );

		$this->assertSame( 200, $this->verifyCode( $email, (string) $code )->get_status(), 'first use ok' );

		// A fresh send starts a new code; the old one must no longer work.
		wp_set_current_user( 0 );
		Wp::clearOtpState();
		$this->assertSame( 200, $this->sendCode( $email )->get_status(), 're-send ok' );

		$reuse = $this->verifyCode( $email, (string) $code );
		$this->assertSame( 400, $reuse->get_status(), 'the previous code is invalid after a resend' );
	}
}
