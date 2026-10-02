<?php

namespace WcfaTests\Lib;

use Lkn\WcForceAuth\Includes\WcForceAuthOtp;

/**
 * Base case for tests that touch WordPress state.
 *
 * Snapshots the plugin options before each test and restores them after, and
 * clears transient OTP state, so tests are isolated and never leave the real
 * site's configuration changed.
 */
abstract class WpTestCase extends TestCase {

	/** @var array<string,mixed> */
	private $snapshot = array();

	/** @var string[] Emails created by the test, cleaned up in tearDown. */
	private $createdEmails = array();

	public function setUp(): void {
		$this->snapshot = Wp::snapshotOptions();
		Wp::clearOtpState();
		wp_set_current_user( 0 );
	}

	public function tearDown(): void {
		Wp::restoreOptions( $this->snapshot );
		Wp::clearOtpState();
		wp_set_current_user( 0 );

		foreach ( $this->createdEmails as $email ) {
			Wp::deleteUserByEmail( $email );
		}

		$this->createdEmails = array();
	}

	/**
	 * Track an email so any user created for it is deleted after the test.
	 *
	 * @param string $email Email address.
	 * @return string The same email.
	 */
	protected function trackEmail( string $email ): string {
		$this->createdEmails[] = $email;

		return $email;
	}

	/**
	 * A unique subject so a test can find exactly the email it just triggered.
	 *
	 * @param string $tag Subject prefix.
	 * @return string The subject applied to the email_subject option.
	 */
	protected function useUniqueSubject( string $tag = 'WC-FA-TEST' ): string {
		$subject = $tag . ' ' . uniqid();
		update_option( WcForceAuthOtp::OPTION_PREFIX . 'email_subject', $subject, false );

		return $subject;
	}

	/**
	 * Call the /send-code REST endpoint with a valid nonce.
	 *
	 * @param string      $email Email address.
	 * @param string|null $nonce Override nonce (defaults to a valid one).
	 * @return \WP_REST_Response
	 */
	protected function sendCode( string $email, $nonce = null ): \WP_REST_Response {
		return Wp::rest(
			'/send-code',
			array(
				'email' => $email,
				'nonce' => null === $nonce ? Wp::otpNonce() : $nonce,
			)
		);
	}

	/**
	 * Call the /verify-code REST endpoint with a valid nonce.
	 *
	 * @param string $email Email address.
	 * @param string $code  Code.
	 * @return \WP_REST_Response
	 */
	protected function verifyCode( string $email, string $code ): \WP_REST_Response {
		return Wp::rest(
			'/verify-code',
			array(
				'email' => $email,
				'code'  => $code,
				'nonce' => Wp::otpNonce(),
			)
		);
	}

	/**
	 * Wait for the email with the given subject and extract its OTP code.
	 *
	 * @param string $subject Subject to match.
	 * @param int    $length  Expected number of digits.
	 * @return string|null
	 */
	protected function waitForEmailedCode( string $subject, int $length ): ?string {
		$code = null;

		Mailpit::waitFor(
			static function () use ( $subject, $length, &$code ) {
				$message = Mailpit::find( $subject );

				if ( null === $message ) {
					return false;
				}

				$code = Mailpit::code( $message, $length );

				return null !== $code;
			}
		);

		return $code;
	}
}
