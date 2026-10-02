<?php

namespace WcfaTests\Lib;

use Lkn\WcForceAuth\Includes\WcForceAuthCheckoutSettings;
use Lkn\WcForceAuth\Includes\WcForceAuthOtp;
use Lkn\WcForceAuth\Includes\WcForceAuthSettings;

/**
 * WordPress-aware helpers shared by the test cases.
 */
class Wp {

	/**
	 * The OTP module instance created during bootstrap.
	 *
	 * @return WcForceAuthOtp|null
	 */
	public static function otp(): ?WcForceAuthOtp {
		return WcForceAuthOtp::instance();
	}

	/**
	 * Every option name owned by the plugin (OTP + Force + stored codes).
	 *
	 * @return string[]
	 */
	public static function pluginOptionNames(): array {
		$names = array();

		foreach ( array_keys( WcForceAuthOtp::defaults() ) as $key ) {
			$names[] = WcForceAuthOtp::OPTION_PREFIX . $key;
		}

		foreach ( array_keys( WcForceAuthCheckoutSettings::defaults() ) as $key ) {
			$names[] = WcForceAuthCheckoutSettings::OPTION_PREFIX . $key;
		}

		return $names;
	}

	/**
	 * Snapshot the plugin's options so they can be restored after the tests.
	 *
	 * @return array<string,mixed>
	 */
	public static function snapshotOptions(): array {
		$snapshot = array();

		foreach ( self::pluginOptionNames() as $name ) {
			$snapshot[ $name ] = get_option( $name, '__WC_FA_MISSING__' );
		}

		return $snapshot;
	}

	/**
	 * Restore a snapshot taken by snapshotOptions().
	 *
	 * @param array<string,mixed> $snapshot Values to restore.
	 */
	public static function restoreOptions( array $snapshot ): void {
		foreach ( $snapshot as $name => $value ) {
			if ( '__WC_FA_MISSING__' === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value, false );
			}
		}
	}

	/**
	 * Apply OTP options (any subset); missing keys keep their defaults.
	 *
	 * @param array<string,string> $overrides Key => value.
	 */
	public static function setOtpConfig( array $overrides ): void {
		$config = array_merge( WcForceAuthOtp::defaults(), $overrides );

		foreach ( $config as $key => $value ) {
			update_option( WcForceAuthOtp::OPTION_PREFIX . $key, (string) $value, false );
		}
	}

	/**
	 * Apply Force Authentication options.
	 *
	 * @param array<string,string> $overrides Key => value.
	 */
	public static function setForceConfig( array $overrides ): void {
		$config = array_merge( WcForceAuthCheckoutSettings::defaults(), $overrides );

		foreach ( $config as $key => $value ) {
			update_option( WcForceAuthCheckoutSettings::OPTION_PREFIX . $key, (string) $value, false );
		}
	}

	/**
	 * Remove transient OTP state that could leak between tests: any stored code
	 * and the per-IP request throttle.
	 */
	public static function clearOtpState(): void {
		global $wpdb;

		$like = $wpdb->esc_like( WcForceAuthOtp::OPTION_PREFIX . 'auth_' ) . '%';
		$names = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);

		foreach ( $names as $name ) {
			delete_option( $name );
		}

		delete_transient( 'wcfa_otp_ip_' . md5( self::clientIp() ) );
	}

	/**
	 * Best-effort client IP used by ip_allowed() on CLI runs.
	 *
	 * @return string
	 */
	public static function clientIp(): string {
		return ! empty( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	}

	/**
	 * A fresh, valid OTP REST nonce for the current session.
	 *
	 * @return string
	 */
	public static function otpNonce(): string {
		return wp_create_nonce( 'wcfa_otp_nonce' );
	}

	/**
	 * Dispatch a request through the plugin's REST namespace.
	 *
	 * @param string $route  Route after the namespace (e.g. '/send-code').
	 * @param array  $params Body params.
	 * @return \WP_REST_Response
	 */
	public static function rest( string $route, array $params ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/' . WcForceAuthOtp::REST_NAMESPACE . $route );
		$request->set_header( 'Content-Type', 'application/json' );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * The stored (hashed) code record for an email, if any.
	 *
	 * @param string $email Email address.
	 * @return array|null
	 */
	public static function storedCode( string $email ): ?array {
		$key = WcForceAuthOtp::OPTION_PREFIX . 'auth_' . md5( strtolower( $email ) );
		$row = get_option( $key );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Render the OTP form markup using the current options.
	 *
	 * @return string
	 */
	public static function renderOtpForm(): string {
		$otp = self::otp();

		return null === $otp ? '' : $otp->get_form_html();
	}

	/**
	 * Create a throwaway customer account, or return the existing user id.
	 *
	 * @param string $email Email address.
	 * @return int User ID (0 on failure).
	 */
	public static function ensureUser( string $email ): int {
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			return (int) $user->ID;
		}

		$username = 'wcfa_' . substr( md5( $email . microtime( true ) ), 0, 10 );
		$user_id  = wp_create_user( $username, wp_generate_password( 20 ), $email );

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		$u = new \WP_User( $user_id );
		$u->set_role( 'customer' );

		return (int) $user_id;
	}

	/**
	 * Delete a user created by ensureUser() (best-effort cleanup).
	 *
	 * @param string $email Email address.
	 */
	public static function deleteUserByEmail( string $email ): void {
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$user = get_user_by( 'email', $email );

		if ( $user ) {
			wp_delete_user( (int) $user->ID );
		}
	}
}
