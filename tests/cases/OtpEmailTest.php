<?php

namespace WcfaTests\Cases;

use WcfaTests\Lib\Mailpit;
use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * End-to-end email delivery: the code the plugin sends must really land in the
 * catcher (Mailpit) with the configured length, and it must actually verify.
 */
class OtpEmailTest extends WpTestCase {

	/**
	 * The address the user asked to use; Mailpit captures every outgoing mail.
	 */
	private const TEST_EMAIL = 'dev-email@wpengine.local';

	public function test_email_is_delivered_to_the_configured_address(): void {
		$subject = $this->useUniqueSubject();
		$before  = Mailpit::count();

		Wp::setOtpConfig(
			array(
				'enable_type'   => 'register_and_login',
				'code_length'   => '6',
				'email_subject' => $subject,
			)
		);

		$response = $this->sendCode( self::TEST_EMAIL );
		$this->assertSame( 200, $response->get_status(), 'send-code returns 200' );

		$received = Mailpit::waitFor( static fn() => Mailpit::count() > $before );
		$this->assertTrue( $received, 'Mailpit received a new message' );

		$message = Mailpit::find( $subject );
		$this->assertNotEmpty( $message, 'the OTP email is present in Mailpit' );

		$to = wp_list_pluck( $message['To'] ?? array(), 'Address' );
		$this->assertContains( self::TEST_EMAIL, implode( ',', $to ), 'email addressed to the test mailbox' );
		$this->assertSame( $subject, $message['Subject'], 'subject matches the configured one' );
	}

	public function test_email_body_contains_code_of_configured_length(): void {
		foreach ( array( '4', '6', '8' ) as $length ) {
			Wp::clearOtpState();
			$subject = $this->useUniqueSubject();

			Wp::setOtpConfig(
				array(
					'enable_type'   => 'register_and_login',
					'code_length'   => $length,
					'email_subject' => $subject,
				)
			);

			$response = $this->sendCode( self::TEST_EMAIL );
			$this->assertSame( 200, $response->get_status(), "send-code 200 (length {$length})" );

			$code = $this->waitForEmailedCode( $subject, (int) $length );
			$this->assertNotEmpty( $code, "email contains a {$length}-digit code" );
			$this->assertSame( (int) $length, strlen( (string) $code ), "extracted code has {$length} digits" );
		}
	}

	public function test_emailed_code_verifies_and_logs_in(): void {
		$email   = $this->trackEmail( 'wcfa-email-test-' . uniqid() . '@wpengine.local' );
		$subject = $this->useUniqueSubject();

		Wp::setOtpConfig(
			array(
				'enable_type'   => 'register_and_login',
				'code_length'   => '6',
				'code_format'   => 'dash',
				'email_subject' => $subject,
			)
		);

		$this->assertSame( 200, $this->sendCode( $email )->get_status(), 'send-code 200' );

		$code = $this->waitForEmailedCode( $subject, 6 );
		$this->assertNotEmpty( $code, 'got the code from the email' );

		$verify = $this->verifyCode( $email, (string) $code );
		$this->assertSame( 200, $verify->get_status(), 'verify-code 200 with the emailed code' );

		$data = $verify->get_data();
		$this->assertTrue( ! empty( $data['success'] ), 'verify reports success' );

		// The customer is now authenticated (a login_only code would fail here).
		$user = get_user_by( 'email', $email );
		$this->assertNotEmpty( $user, 'account exists after register+verify' );
		$this->assertSame( (int) $user->ID, get_current_user_id(), 'the customer is logged in' );
	}
}
