<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email OTP authentication: sends one-time codes, verifies them and logs the
 * customer in (creating the account on the fly when in "register and login"
 * mode).
 *
 * Migrated from the "Invoice Payment for WooCommerce" plugin, now with a
 * dedicated option namespace (`wc_force_auth_otp_*`) and hashed code storage.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthOtp {

	/**
	 * Option prefix for every OTP setting/code.
	 */
	public const OPTION_PREFIX = 'wc_force_auth_otp_';

	/**
	 * REST namespace.
	 */
	public const REST_NAMESPACE = 'wc-force-auth/v1';

	/**
	 * Bundled SweetAlert2 version.
	 */
	public const SWEETALERT_VERSION = '11.0.0';

	/**
	 * Maximum number of verification attempts per code.
	 */
	public const MAX_ATTEMPTS = 5;

	/**
	 * Maximum number of codes sent from a single IP within the throttle window.
	 */
	public const MAX_SENDS_PER_IP = 10;

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
	 * The most recently created instance (used by the account template).
	 *
	 * @var WcForceAuthOtp|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name The plugin name.
	 * @param string $version     The plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		self::$instance = $this;
	}

	/**
	 * Retrieve the OTP module instance (used by the account template).
	 *
	 * @return WcForceAuthOtp|null
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Default values for every OTP setting.
	 *
	 * @return array<string,string>
	 */
	public static function defaults(): array {
		return array(
			// Behaviour.
			'enable_type'       => 'disabled',
			'expiration_time'   => '10',
			'resend_interval'   => '30',
			'code_length'       => '6',
			'code_format'       => 'plain',
			'code_input'        => 'single',
			'notification_style' => 'floating',
			// Appearance.
			'primary_color'     => '#22C1DC',
			'brand_name'        => '',
			'brand_tagline'     => '',
			'logo_url'          => '',
			'login_heading'     => __( 'Welcome back', 'woo-force-authentification-before-checkout' ),
			'login_subheading'  => __( 'Enter your email to sign up or access your account', 'woo-force-authentification-before-checkout' ),
			'verify_heading'    => __( 'Verify your account', 'woo-force-authentification-before-checkout' ),
			'verify_subheading' => __( 'Enter the verification code sent to your email', 'woo-force-authentification-before-checkout' ),
			'otp_sent_message'  => __( 'A one-time password (OTP) has been sent to your registered email address.', 'woo-force-authentification-before-checkout' ),
			'legal_text'        => __( 'By clicking on "Login" you agree to our Privacy Policy and Terms of Service.', 'woo-force-authentification-before-checkout' ),
			// Email.
			'email_subject'     => __( 'Your access code', 'woo-force-authentification-before-checkout' ),
			// Redirect.
			'redirect_url'      => '',
		);
	}

	/**
	 * Legacy (Invoice Payment) option keys, used as a migration fallback.
	 *
	 * @return array<string,string>
	 */
	private static function legacy_map(): array {
		return array(
			'enable_type'     => 'lkn_wcip_otp_email_enable_type',
			'expiration_time' => 'lkn_wcip_otp_email_expiration_time',
		);
	}

	/**
	 * Read an OTP setting, falling back to legacy values when present.
	 *
	 * @param string $key     Setting key (without the prefix).
	 * @param mixed  $default Fallback default.
	 * @return mixed
	 */
	public function option( $key, $default = '' ) {
		$defaults = self::defaults();

		if ( '' === $default && isset( $defaults[ $key ] ) ) {
			$default = $defaults[ $key ];
		}

		$value = get_option( self::OPTION_PREFIX . $key, null );

		if ( null === $value || '' === $value ) {
			$legacy = self::legacy_map();

			if ( isset( $legacy[ $key ] ) ) {
				$legacy_value = get_option( $legacy[ $key ], null );

				if ( null !== $legacy_value && '' !== $legacy_value ) {
					return $legacy_value;
				}
			}

			return $default;
		}

		return $value;
	}

	/**
	 * Current authentication mode.
	 *
	 * @return string disabled|login_only|register_and_login
	 */
	public function get_mode() {
		$mode = (string) $this->option( 'enable_type', 'disabled' );

		if ( ! in_array( $mode, array( 'disabled', 'login_only', 'register_and_login' ), true ) ) {
			$mode = 'disabled';
		}

		return $mode;
	}

	/**
	 * Whether OTP authentication is active.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'disabled' !== $this->get_mode();
	}

	/**
	 * Whether the "register and login" (passwordless) mode is active.
	 *
	 * @return bool
	 */
	public function is_full_mode() {
		return 'register_and_login' === $this->get_mode();
	}

	/**
	 * Whether the "login only" mode is active.
	 *
	 * @return bool
	 */
	public function is_login_only_mode() {
		return 'login_only' === $this->get_mode();
	}

	/**
	 * Number of digits in the generated code.
	 *
	 * @return int
	 */
	public function get_code_length() {
		$length = (int) $this->option( 'code_length', 6 );

		return in_array( $length, array( 4, 6, 8 ), true ) ? $length : 6;
	}

	/**
	 * How the code is entered: a single field or one box per digit.
	 *
	 * @return string single|segmented
	 */
	public function get_code_input() {
		$input = (string) $this->option( 'code_input', 'single' );

		return in_array( $input, array( 'single', 'segmented' ), true ) ? $input : 'single';
	}

	/**
	 * Whether the segmented (one box per digit) input is active.
	 *
	 * @return bool
	 */
	public function is_segmented_input() {
		return 'segmented' === $this->get_code_input();
	}

	/**
	 * Visual format used for the code field: plain, groups with space, groups
	 * with dash, or pairs. Purely presentational — the separator is stripped
	 * before the code is sent.
	 *
	 * @return string plain|space|dash|pair
	 */
	public function get_code_format() {
		$format = (string) $this->option( 'code_format', 'plain' );

		return in_array( $format, array( 'plain', 'space', 'dash', 'pair' ), true ) ? $format : 'plain';
	}

	/**
	 * Group sizes used to lay out the code (e.g. [3,3] for 6 digits grouped).
	 *
	 * @return int[]
	 */
	private function get_code_group_sizes() {
		$length = $this->get_code_length();
		$format = $this->get_code_format();

		if ( 'plain' === $format ) {
			return array( $length );
		}

		if ( 'pair' === $format ) {
			$groups = array();

			for ( $i = 0; $i < $length; $i += 2 ) {
				$groups[] = min( 2, $length - $i );
			}

			return $groups;
		}

		// "space"/"dash": split the code into two halves (3+3, 4+4, ...).
		$first  = (int) ceil( $length / 2 );
		$second = $length - $first;

		return $second > 0 ? array( $first, $second ) : array( $first );
	}

	/**
	 * Separator character for the chosen format.
	 *
	 * @return string
	 */
	private function get_code_separator() {
		return 'dash' === $this->get_code_format() ? '-' : ' ';
	}

	/**
	 * Placeholder for the code field, matching the chosen format.
	 *
	 * @return string
	 */
	public function get_code_placeholder() {
		$length = $this->get_code_length();

		if ( 'plain' === $this->get_code_format() ) {
			return str_repeat( '•', $length );
		}

		$parts = array();

		foreach ( $this->get_code_group_sizes() as $size ) {
			$parts[] = str_repeat( '•', $size );
		}

		return implode( $this->get_code_separator(), $parts );
	}

	/**
	 * Maxlength for the code field, accounting for the separator characters.
	 *
	 * @return int
	 */
	public function get_code_maxlength() {
		$length = $this->get_code_length();

		if ( 'plain' === $this->get_code_format() ) {
			return $length;
		}

		return $length + ( count( $this->get_code_group_sizes() ) - 1 );
	}

	/**
	 * Code expiration in minutes.
	 *
	 * @return int
	 */
	public function get_expiration_minutes() {
		return max( 1, min( 60, (int) $this->option( 'expiration_time', 10 ) ) );
	}

	/**
	 * Minimum seconds between two code requests for the same email.
	 *
	 * @return int
	 */
	public function get_resend_interval() {
		return max( 0, min( 600, (int) $this->option( 'resend_interval', 30 ) ) );
	}

	/**
	 * Where feedback notifications are shown: floating (toasts) or inline.
	 *
	 * @return string floating|inline
	 */
	public function get_notification_style() {
		$style = (string) $this->option( 'notification_style', 'floating' );

		return in_array( $style, array( 'floating', 'inline' ), true ) ? $style : 'floating';
	}

	/**
	 * Register the REST endpoints.
	 *
	 * Hook: rest_api_init.
	 */
	public function register_endpoints() {
		$args = array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_send_code' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'email' => array(
					'required'          => true,
					'validate_callback' => static function ( $param ) {
						return is_email( $param );
					},
				),
			),
		);

		register_rest_route( self::REST_NAMESPACE, '/send-code', $args );

		$args['callback']      = array( $this, 'rest_verify_code' );
		$args['args']['code']  = array(
			'required'          => true,
			'validate_callback' => static function ( $param ) {
				return (bool) preg_match( '/^\d{4,8}$/', (string) $param );
			},
		);

		register_rest_route( self::REST_NAMESPACE, '/verify-code', $args );

		register_rest_route(
			self::REST_NAMESPACE,
			'/report-code',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_report_code' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'email' => array(
						'required'          => true,
						'validate_callback' => static function ( $param ) {
							return is_email( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * REST callback: email the site administrators when a customer reports that
	 * the verification code did not arrive.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function rest_report_code( $request ) {
		if ( ! $this->ip_allowed() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Too many requests. Please try again later.', 'woo-force-authentification-before-checkout' ),
				),
				429
			);
		}

		$email   = sanitize_email( $request->get_param( 'email' ) );
		$contact = sanitize_text_field( (string) $request->get_param( 'contact' ) );
		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

		$recipients = $this->get_admin_emails();

		if ( empty( $recipients ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Could not send the report. Please try again later.', 'woo-force-authentification-before-checkout' ),
				),
				500
			);
		}

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] A customer did not receive the OTP code', 'woo-force-authentification-before-checkout' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body = implode(
			"\n",
			array(
				__( 'A customer reported that they did not receive the one-time password (OTP) code.', 'woo-force-authentification-before-checkout' ),
				'',
				sprintf(
					/* translators: %s: customer email. */
					__( 'Customer email: %s', 'woo-force-authentification-before-checkout' ),
					$email
				),
				sprintf(
					/* translators: %s: contact provided by the customer. */
					__( 'Contact: %s', 'woo-force-authentification-before-checkout' ),
					$contact ? $contact : '—'
				),
				'',
				__( 'Message:', 'woo-force-authentification-before-checkout' ),
				$message,
				'',
				sprintf(
					/* translators: %s: site URL. */
					__( 'Sent from: %s', 'woo-force-authentification-before-checkout' ),
					home_url()
				),
			)
		);

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		$sent = wp_mail( $recipients, $subject, $body, $headers );

		if ( ! $sent ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Could not send the report. Please try again later.', 'woo-force-authentification-before-checkout' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Thank you! Your report was sent and our team will look into it.', 'woo-force-authentification-before-checkout' ),
			),
			200
		);
	}

	/**
	 * Email addresses of every WordPress administrator.
	 *
	 * @return string[]
	 */
	private function get_admin_emails() {
		$users = get_users(
			array(
				'role'   => 'administrator',
				'fields' => array( 'user_email' ),
			)
		);

		$emails = array();

		foreach ( $users as $user ) {
			$email = is_object( $user ) ? $user->user_email : $user;

			if ( is_email( $email ) ) {
				$emails[] = $email;
			}
		}

		// Fallback to the site admin email when no administrator was found.
		if ( empty( $emails ) ) {
			$admin_email = get_option( 'admin_email' );

			if ( is_email( $admin_email ) ) {
				$emails[] = $admin_email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Verify the request nonce (CSRF protection).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\WP_Error
	 */
	public function check_permission( $request ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'wcfa_otp_disabled', __( 'OTP authentication is not enabled.', 'woo-force-authentification-before-checkout' ), array( 'status' => 403 ) );
		}

		$nonce = $request->get_param( 'nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wcfa_otp_nonce' ) ) {
			return new \WP_Error( 'wcfa_bad_nonce', __( 'Security check failed. Please reload the page.', 'woo-force-authentification-before-checkout' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * REST callback: send (or resend) a one-time code.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function rest_send_code( $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( ! $this->ip_allowed() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'wcfa_ip_limited',
					'message' => __( 'Too many requests. Please try again later.', 'woo-force-authentification-before-checkout' ),
				),
				429
			);
		}

		$this->cleanup_expired_codes();

		if ( $this->is_login_only_mode() && ! email_exists( $email ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Email not registered.', 'woo-force-authentification-before-checkout' ),
				),
				400
			);
		}

		$existing = $this->get_stored_code( $email );

		if ( $existing ) {
			$interval = $this->get_resend_interval();
			$wait     = ( $interval - ( time() - (int) $existing['last_sent'] ) );

			if ( $wait > 0 ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'code'    => 'wcfa_rate_limited',
						'message' => sprintf(
							/* translators: %d: seconds to wait before requesting a new code. */
							__( 'Please wait %d seconds before requesting a new code.', 'woo-force-authentification-before-checkout' ),
							$wait
						),
						'resend_in' => $wait,
					),
					429
				);
			}

			$code = $this->generate_code();
			$this->store_code( $email, $code, $existing['created_at'] );
		} else {
			$code = $this->generate_code();
			$this->store_code( $email, $code );
		}

		if ( ! $this->send_email( $email, $code ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Could not send the code. Please try again.', 'woo-force-authentification-before-checkout' ),
				),
				500
			);
		}

		$expires_at = time() + ( $this->get_expiration_minutes() * 60 );

		return new \WP_REST_Response(
			array(
				'success'       => true,
				'message'       => __( 'A one-time password (OTP) has been sent to your email.', 'woo-force-authentification-before-checkout' ),
				'email'         => $email,
				'expires_at'    => $expires_at,
				// Formatted with the site timezone/locale (wp_date), not the browser clock.
				'expires_label' => wp_date( (string) get_option( 'time_format', 'H:i' ), $expires_at ),
				'expires_in'    => $this->get_expiration_minutes() * 60,
				'resend_in'     => $this->get_resend_interval(),
			),
			200
		);
	}

	/**
	 * REST callback: verify a code and log the customer in.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function rest_verify_code( $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );
		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );

		$result = $this->process_login( $email, $code );

		$status = $result['success'] ? 200 : 400;

		if ( isset( $result['status'] ) ) {
			$status = (int) $result['status'];
		}

		return new \WP_REST_Response( $result, $status );
	}

	/**
	 * Best-effort client IP (uses REMOTE_ADDR; proxy headers are not trusted).
	 *
	 * @return string
	 */
	private function get_client_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Simple per-IP throttle for code requests (anti email-bombing).
	 *
	 * @return bool
	 */
	private function ip_allowed() {
		$ip = $this->get_client_ip();

		if ( '' === $ip ) {
			return true;
		}

		$key   = 'wcfa_otp_ip_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::MAX_SENDS_PER_IP ) {
			return false;
		}

		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Generate a numeric code of the configured length.
	 *
	 * @return string
	 */
	private function generate_code() {
		$length = $this->get_code_length();
		$max    = (int) str_repeat( '9', $length );

		return str_pad( (string) wp_rand( 0, $max ), $length, '0', STR_PAD_LEFT );
	}

	/**
	 * Hashed storage key for an email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function code_key( $email ) {
		// "auth_" intentionally does not share a prefix with any setting key
		// (e.g. otp_sent_message / code_length), so the cleanup LIKE can never
		// match a configuration option.
		return self::OPTION_PREFIX . 'auth_' . md5( strtolower( $email ) );
	}

	/**
	 * Persist a hashed code for the given email.
	 *
	 * @param string   $email      Email address.
	 * @param string   $code       Plain code.
	 * @param int|null $created_at Optional creation timestamp to preserve.
	 */
	private function store_code( $email, $code, $created_at = null ) {
		$now = time();

		$data = array(
			'code_hash'  => wp_hash( $code . '|' . strtolower( $email ) ),
			'email'      => strtolower( $email ),
			'expires_at' => $now + ( $this->get_expiration_minutes() * 60 ),
			'used'       => false,
			'attempts'   => 0,
			'last_sent'  => $now,
			'created_at' => null === $created_at ? $now : (int) $created_at,
		);

		update_option( $this->code_key( $email ), $data, false );
	}

	/**
	 * Retrieve a still-valid stored code entry.
	 *
	 * @param string $email Email address.
	 * @return array|false
	 */
	private function get_stored_code( $email ) {
		$data = get_option( $this->code_key( $email ) );

		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( ! empty( $data['used'] ) || (int) $data['expires_at'] < time() ) {
			delete_option( $this->code_key( $email ) );
			return false;
		}

		return $data;
	}

	/**
	 * Delete a stored code entry.
	 *
	 * @param string $email Email address.
	 */
	private function delete_code( $email ) {
		delete_option( $this->code_key( $email ) );
	}

	/**
	 * Remove expired/unused code entries.
	 */
	private function cleanup_expired_codes() {
		global $wpdb;

		// Only the transient OTP records use the "auth_" suffix. Settings keys
		// (code_length / code_format / code_input / otp_sent_message) must NEVER
		// match this LIKE, otherwise they would be deleted on every login.
		// esc_like() escapes the "_" wildcards in the prefix.
		$like = $wpdb->esc_like( self::OPTION_PREFIX . 'auth_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		foreach ( $names as $name ) {
			$data = get_option( $name );

			// Only ever delete our own structured OTP records.
			if ( ! is_array( $data ) || ! isset( $data['expires_at'] ) ) {
				continue;
			}

			if ( ! empty( $data['used'] ) || (int) $data['expires_at'] < time() ) {
				delete_option( $name );
			}
		}
	}

	/**
	 * Render and send the OTP email.
	 *
	 * @param string $email Email address.
	 * @param string $code  Plain code.
	 * @return bool
	 */
	private function send_email( $email, $code ) {
		$subject = sprintf(
			/* translators: %s: the one-time password code. */
			// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
			(string) $this->option( 'email_subject', __( 'Your access code', 'woo-force-authentification-before-checkout' ) ),
			$code
		);

		$template_data = array(
			'otp_code'           => $code,
			'site_name'          => $this->get_brand_name(),
			'site_url'           => home_url(),
			'expiration_minutes' => $this->get_expiration_minutes(),
			'primary_color'      => $this->get_primary_color(),
		);

		$message = $this->render_email_template( $template_data );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $email, $subject, $message, $headers );
	}

	/**
	 * Capture the OTP email template output.
	 *
	 * @param array $data Template variables.
	 * @return string
	 */
	private function render_email_template( $data ) {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data );

		ob_start();
		include WC_FORCE_AUTH_DIR . 'Includes/templates/otp-email-template.php';

		return (string) ob_get_clean();
	}

	/**
	 * Validate a code for an email.
	 *
	 * @param string $email Email address.
	 * @param string $code  Submitted code.
	 * @return bool
	 */
	public function verify_code( $email, $code ) {
		$data = $this->get_stored_code( $email );

		if ( ! $data ) {
			return false;
		}

		$data['attempts'] = (int) $data['attempts'] + 1;

		if ( $data['attempts'] > self::MAX_ATTEMPTS ) {
			$this->delete_code( $email );
			return false;
		}

		$expected = wp_hash( $code . '|' . strtolower( $email ) );

		if ( hash_equals( (string) $data['code_hash'], $expected ) ) {
			$this->delete_code( $email );
			return true;
		}

		update_option( $this->code_key( $email ), $data, false );

		return false;
	}

	/**
	 * Verify the code and log the user in (creating the account if needed).
	 *
	 * @param string $email Email address.
	 * @param string $code  Submitted code.
	 * @return array Result payload.
	 */
	public function process_login( $email, $code ) {
		$valid = $this->verify_code( $email, $code );

		if ( ! $valid ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or expired code.', 'woo-force-authentification-before-checkout' ),
			);
		}

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			if ( ! $this->is_full_mode() ) {
				return array(
					'success' => false,
					'message' => __( 'User not found.', 'woo-force-authentification-before-checkout' ),
				);
			}

			$user = $this->register_user_without_password( $email );

			if ( ! $user ) {
				return array(
					'success' => false,
					'message' => __( 'Could not create the account.', 'woo-force-authentification-before-checkout' ),
				);
			}
		}

		if ( ! $this->login_user( $user ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not log you in.', 'woo-force-authentification-before-checkout' ),
			);
		}

		return array(
			'success'  => true,
			'message'  => __( 'Login successful!', 'woo-force-authentification-before-checkout' ),
			'redirect' => $this->get_redirect_url(),
		);
	}

	/**
	 * Create a passwordless customer account.
	 *
	 * @param string $email Email address.
	 * @return \WP_User|false
	 */
	public function register_user_without_password( $email ) {
		if ( email_exists( $email ) ) {
			return get_user_by( 'email', $email );
		}

		$username = $this->generate_unique_username( $email );
		$password = wp_generate_password( 20 );

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return false;
		}

		$user = new \WP_User( $user_id );
		$user->set_role( 'customer' );

		if ( function_exists( 'WC' ) ) {
			$this->send_welcome_email( $user_id );
		}

		return $user;
	}

	/**
	 * Trigger WooCommerce's "new account" email for a freshly created user.
	 *
	 * @param int $user_id User ID.
	 */
	private function send_welcome_email( $user_id ) {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();

		if ( ! isset( $emails['WC_Email_Customer_New_Account'] ) ) {
			return;
		}

		$email = $emails['WC_Email_Customer_New_Account'];

		if ( ! $email->is_enabled() ) {
			return;
		}

		$user = get_userdata( $user_id );
		$key  = get_password_reset_key( $user );

		if ( ! is_wp_error( $key ) ) {
			$email->trigger( $user_id, $key );
		}
	}

	/**
	 * Build a unique username from an email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function generate_unique_username( $email ) {
		$base     = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ), true );
		$base     = $base ? $base : 'customer';
		$username = $base;
		$counter  = 1;

		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			++$counter;
		}

		return $username;
	}

	/**
	 * Log a user in and set the auth cookie.
	 *
	 * @param \WP_User $user User object.
	 * @return bool
	 */
	public function login_user( $user ) {
		if ( ! $user || is_wp_error( $user ) ) {
			return false;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		/**
		 * Fires after the customer is authenticated via OTP, matching the native
		 * wp_signon() flow so integrations (WooCommerce session/cart merge, etc.)
		 * run as expected.
		 */
		do_action( 'wp_login', $user->user_login, $user );

		return true;
	}

	/**
	 * URL the customer is sent to after a successful login.
	 *
	 * @return string
	 */
	public function get_redirect_url() {
		$configured = trim( (string) $this->option( 'redirect_url', '' ) );

		if ( '' !== $configured ) {
			return esc_url_raw( $configured );
		}

		return wc_get_account_endpoint_url( 'dashboard' );
	}

	/**
	 * Located-template filter.
	 *
	 * Serves the plugin's own `myaccount/form-login.php` so the OTP UI fully
	 * replaces the native WooCommerce login/registration forms — regardless of
	 * the active theme (including themes that ship their own form-login.php).
	 *
	 * Hook: woocommerce_locate_template.
	 *
	 * @param string $template      Template path resolved by WooCommerce.
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default templates path.
	 * @return string
	 */
	public function locate_form_login_template( $template, $template_name, $template_path, $default_path ) {
		if ( 'myaccount/form-login.php' !== $template_name || ! $this->is_enabled() || is_user_logged_in() ) {
			return $template;
		}

		$custom = WC_FORCE_AUTH_DIR . 'Includes/templates/myaccount/form-login.php';

		return is_file( $custom ) ? $custom : $template;
	}

	/**
	 * Public accessor for the authentication markup (used by the template).
	 *
	 * @return string
	 */
	public function get_form_html() {
		return $this->render_form();
	}

	/**
	 * Whether the native WooCommerce registration form should be shown
	 * alongside the OTP login (only in "Login only" mode).
	 *
	 * @return bool
	 */
	public function should_show_native_registration() {
		return $this->is_login_only_mode()
			&& 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' );
	}

	/**
	 * Build the OTP markup (login step + verification step).
	 *
	 * @return string
	 */
	private function render_form() {
		$mode       = $this->get_mode();
		$heading    = (string) $this->option( 'login_heading', __( 'Welcome back', 'woo-force-authentification-before-checkout' ) );
		$subheading = (string) $this->option( 'login_subheading', __( 'Enter your email to sign up or access your account', 'woo-force-authentification-before-checkout' ) );
		$v_heading  = (string) $this->option( 'verify_heading', __( 'Verify your account', 'woo-force-authentification-before-checkout' ) );
		$v_subhead  = (string) $this->option( 'verify_subheading', __( 'Enter the verification code sent to your email', 'woo-force-authentification-before-checkout' ) );
		$sent_msg   = (string) $this->option( 'otp_sent_message', __( 'A one-time password (OTP) has been sent to your registered email address.', 'woo-force-authentification-before-checkout' ) );
		$legal      = (string) $this->option( 'legal_text', '' );
		$logo       = $this->get_logo_url();
		$brand      = $this->get_brand_name();
		$tagline    = $this->get_tagline();
		$color      = $this->get_primary_color();
		$notify     = $this->get_notification_style();

		ob_start();
		?>
		<div class="wcfa-otp-wrapper" data-mode="<?php echo esc_attr( $mode ); ?>" data-notify="<?php echo esc_attr( $notify ); ?>" style="--wcfa-primary: <?php echo esc_attr( $color ); ?>;">
			<div class="wcfa-otp-card">
				<div class="wcfa-brand">
					<?php if ( $logo ) : ?>
						<img class="wcfa-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $brand ); ?>" />
					<?php else : ?>
						<span class="wcfa-brand-name"><?php echo esc_html( $brand ); ?></span>
					<?php endif; ?>
					<?php if ( $tagline ) : ?>
						<span class="wcfa-tagline"><?php echo esc_html( $tagline ); ?></span>
					<?php endif; ?>
				</div>

				<div class="wcfa-step wcfa-step--login">
					<h2 class="wcfa-heading"><?php echo esc_html( $heading ); ?></h2>
					<p class="wcfa-subheading"><?php echo esc_html( $subheading ); ?></p>

					<form id="wcfa-login-form" class="wcfa-form" novalidate>
						<label class="wcfa-label" for="wcfa_email">
							<?php esc_html_e( 'Email', 'woo-force-authentification-before-checkout' ); ?>
							<span class="wcfa-required">*</span>
						</label>
						<div class="wcfa-input-wrap" id="wcfa-email-wrap">
							<span class="wcfa-input-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
							</span>
							<input
								type="email"
								id="wcfa_email"
								name="email"
								class="wcfa-input"
								placeholder="<?php esc_attr_e( 'Enter your email', 'woo-force-authentification-before-checkout' ); ?>"
								required
							/>
						</div>

						<div class="wcfa-messages wcfa-step-messages" id="wcfa-login-message" role="status" aria-live="polite"></div>

						<button type="submit" class="wcfa-btn wcfa-btn--primary" id="wcfa-login-btn">
							<?php esc_html_e( 'Login', 'woo-force-authentification-before-checkout' ); ?>
						</button>
					</form>

					<?php if ( $legal ) : ?>
						<p class="wcfa-legal"><?php echo wp_kses_post( $legal ); ?></p>
					<?php endif; ?>
				</div>

				<div class="wcfa-step wcfa-step--verify" hidden>
					<div class="wcfa-badge" aria-hidden="true">
						<span class="wcfa-badge-lock">
							<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
						</span>
					</div>
					<h2 class="wcfa-heading"><?php echo esc_html( $v_heading ); ?></h2>
					<p class="wcfa-subheading">
						<?php echo esc_html( $v_subhead ); ?>
						<strong class="wcfa-email" id="wcfa-email-display"></strong>
					</p>

					<div class="wcfa-alert wcfa-alert--success"<?php echo 'inline' === $notify ? '' : ' hidden'; ?>>
						<span class="wcfa-alert-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
						</span>
						<span><?php echo esc_html( $sent_msg ); ?></span>
					</div>

					<form id="wcfa-verify-form" class="wcfa-form" novalidate>
						<label class="wcfa-label" for="<?php echo $this->is_segmented_input() ? 'wcfa_code_0' : 'wcfa_code'; ?>">
							<?php esc_html_e( 'Verification Code', 'woo-force-authentification-before-checkout' ); ?>
							<span class="wcfa-required">*</span>
						</label>
						<?php if ( $this->is_segmented_input() ) : ?>
							<div class="wcfa-otp-boxes" id="wcfa-otp-boxes"
								data-code-length="<?php echo esc_attr( (string) $this->get_code_length() ); ?>"
								data-code-format="<?php echo esc_attr( $this->get_code_format() ); ?>"
								role="group" aria-label="<?php esc_attr_e( 'Verification code', 'woo-force-authentification-before-checkout' ); ?>">
								<?php
								$wcfa_box_index   = 0;
								$wcfa_box_sizes   = $this->get_code_group_sizes();

								foreach ( $wcfa_box_sizes as $wcfa_group_index => $wcfa_group_size ) :
									if ( $wcfa_group_index > 0 ) {
										echo '<span class="wcfa-otp-sep" aria-hidden="true"></span>';
									}

									for ( $wcfa_i = 0; $wcfa_i < $wcfa_group_size; $wcfa_i++ ) :
										$wcfa_box_pos = $wcfa_box_index + 1;
										?>
										<input
											type="text"
											inputmode="numeric"
											autocomplete="<?php echo 0 === $wcfa_box_index ? 'one-time-code' : 'off'; ?>"
											id="wcfa_code_<?php echo esc_attr( (string) $wcfa_box_index ); ?>"
											class="wcfa-otp-box"
											maxlength="1"
											<?php echo 0 === $wcfa_box_index ? 'name="code"' : ''; ?>
											aria-label="<?php echo esc_attr( sprintf( /* translators: %d: digit position. */ __( 'Digit %d', 'woo-force-authentification-before-checkout' ), $wcfa_box_pos ) ); ?>"
											required
										/>
										<?php
										$wcfa_box_index++;
									endfor;
								endforeach;
								?>
							</div>
						<?php else : ?>
							<div class="wcfa-input-wrap wcfa-input-wrap--code">
								<span class="wcfa-input-icon" aria-hidden="true">
										<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
									</span>
								<input
									type="text"
									inputmode="numeric"
									autocomplete="one-time-code"
									id="wcfa_code"
									name="code"
									class="wcfa-input wcfa-input--code"
									maxlength="<?php echo esc_attr( (string) $this->get_code_maxlength() ); ?>"
									data-code-length="<?php echo esc_attr( (string) $this->get_code_length() ); ?>"
									data-code-format="<?php echo esc_attr( $this->get_code_format() ); ?>"
									placeholder="<?php echo esc_attr( $this->get_code_placeholder() ); ?>"
									required
								/>
							</div>
						<?php endif; ?>
						<div class="wcfa-messages wcfa-step-messages" id="wcfa-verify-message" role="status" aria-live="polite"></div>

						<p class="wcfa-expires" id="wcfa-expires" hidden></p>

						<button type="submit" class="wcfa-btn wcfa-btn--primary" id="wcfa-verify-btn" disabled>
							<?php esc_html_e( 'Verify & Continue', 'woo-force-authentification-before-checkout' ); ?>
						</button>

						<div class="wcfa-resend-row">
							<button type="button" class="wcfa-btn-resend wcfa-btn--muted" id="wcfa-resend" disabled>
								<?php esc_html_e( 'Resend code', 'woo-force-authentification-before-checkout' ); ?>
							</button>
						</div>

						<button type="button" class="wcfa-back" id="wcfa-back">
							<?php esc_html_e( 'Use another email', 'woo-force-authentification-before-checkout' ); ?>
						</button>
					</form>

					<button type="button" class="wcfa-report" id="wcfa-report">
						<span class="wcfa-report-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
						</span>
						<?php esc_html_e( "Didn't receive the code?", 'woo-force-authentification-before-checkout' ); ?>
					</button>
				</div>

				<div id="wcfa-messages" class="wcfa-messages" role="status" aria-live="polite"></div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Brand name used across the OTP screens.
	 *
	 * @return string
	 */
	public function get_brand_name() {
		$name = trim( (string) $this->option( 'brand_name', '' ) );

		return '' !== $name ? $name : get_bloginfo( 'name' );
	}

	/**
	 * Tagline shown under the brand.
	 *
	 * @return string
	 */
	public function get_tagline() {
		$tagline = trim( (string) $this->option( 'brand_tagline', '' ) );

		return '' !== $tagline ? $tagline : get_bloginfo( 'description' );
	}

	/**
	 * Logo shown on the OTP screens.
	 *
	 * @return string
	 */
	public function get_logo_url() {
		$logo = trim( (string) $this->option( 'logo_url', '' ) );

		if ( '' !== $logo ) {
			return $logo;
		}

		$custom_logo_id = get_theme_mod( 'custom_logo' );

		if ( $custom_logo_id ) {
			$url = wp_get_attachment_image_url( (int) $custom_logo_id, 'medium' );

			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Primary accent color.
	 *
	 * @return string
	 */
	public function get_primary_color() {
		$color = trim( (string) $this->option( 'primary_color', '#22C1DC' ) );

		if ( ! preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
			$color = '#22C1DC';
		}

		return $color;
	}

	/**
	 * Whether the OTP assets should be loaded on the current request.
	 *
	 * @return bool
	 */
	public function should_enqueue() {
		return $this->is_enabled() && function_exists( 'is_account_page' ) && is_account_page();
	}

	/**
	 * Enqueue the public OTP stylesheet.
	 *
	 * Hook: wp_enqueue_scripts.
	 */
	public function enqueue_styles() {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		wp_enqueue_style(
			'wc-force-auth-sweetalert2',
			WC_FORCE_AUTH_URL . 'Includes/assets/sweetalert2/sweetalert2.min.css',
			array(),
			self::SWEETALERT_VERSION
		);

		wp_enqueue_style(
			'wc-force-auth-otp',
			WC_FORCE_AUTH_URL . 'Public/css/wc-force-auth-otp.css',
			array( 'wc-force-auth-sweetalert2' ),
			WC_FORCE_AUTH_VERSION
		);
	}

	/**
	 * Enqueue the public OTP script and localize its data.
	 *
	 * Hook: wp_enqueue_scripts.
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		wp_enqueue_script(
			'wc-force-auth-sweetalert2',
			WC_FORCE_AUTH_URL . 'Includes/assets/sweetalert2/sweetalert2.all.min.js',
			array(),
			self::SWEETALERT_VERSION,
			true
		);

		wp_enqueue_script(
			'wc-force-auth-otp',
			WC_FORCE_AUTH_URL . 'Public/js/wc-force-auth-otp.js',
			array( 'wc-force-auth-sweetalert2' ),
			WC_FORCE_AUTH_VERSION,
			true
		);

		wp_localize_script(
			'wc-force-auth-otp',
			'wcForceAuthOtp',
			array(
				'restUrl'       => esc_url_raw( rest_url( self::REST_NAMESPACE ) ),
				'nonce'         => wp_create_nonce( 'wcfa_otp_nonce' ),
				'mode'          => $this->get_mode(),
				'codeLength'    => $this->get_code_length(),
				'codeFormat'    => $this->get_code_format(),
				'codeInput'     => $this->get_code_input(),
				'expiration'    => $this->get_expiration_minutes() * 60,
				'resendInterval' => $this->get_resend_interval(),
				'primaryColor'  => $this->get_primary_color(),
				'notificationStyle' => $this->get_notification_style(),
				'redirectToCheckout' => isset( $_GET[ WcForceAuthCheckout::URL_ARG ] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'checkoutUrl'   => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url(),
				'i18n'          => array(
					'sending'      => __( 'Sending code...', 'woo-force-authentification-before-checkout' ),
					'verifying'    => __( 'Verifying code...', 'woo-force-authentification-before-checkout' ),
					'connection'   => __( 'Connection error. Please try again.', 'woo-force-authentification-before-checkout' ),
					'invalidEmail' => __( 'Please enter a valid email address.', 'woo-force-authentification-before-checkout' ),
					'resendIn'     => __( 'You can resend in %ds', 'woo-force-authentification-before-checkout' ),
					'expiresAt'    => __( 'Code expires at %s', 'woo-force-authentification-before-checkout' ),
					'resendButton' => __( 'Resend code', 'woo-force-authentification-before-checkout' ),
					'confirmBack'  => __( 'Go back and change the email?', 'woo-force-authentification-before-checkout' ),
					'back'         => __( 'Use another email', 'woo-force-authentification-before-checkout' ),
					'errorTitle'   => __( 'Something went wrong', 'woo-force-authentification-before-checkout' ),
					'clipboardTitle' => __( 'Code detected', 'woo-force-authentification-before-checkout' ),
					'clipboardText'  => __( 'We found your access code in the clipboard.', 'woo-force-authentification-before-checkout' ),
					'useCode'      => __( 'Use code', 'woo-force-authentification-before-checkout' ),
					'reportTitle'  => __( 'Report a problem', 'woo-force-authentification-before-checkout' ),
					'reportEmail'  => __( 'Your email', 'woo-force-authentification-before-checkout' ),
					'reportContact' => __( 'Contact (optional)', 'woo-force-authentification-before-checkout' ),
					'reportMessage' => __( 'Message', 'woo-force-authentification-before-checkout' ),
					'reportSend'   => __( 'Send', 'woo-force-authentification-before-checkout' ),
					'reportPrefill' => __( 'I did not receive the verification code for {{email}}.', 'woo-force-authentification-before-checkout' ),
					'cancel'       => __( 'Cancel', 'woo-force-authentification-before-checkout' ),
				),
			)
		);
	}

	/**
	 * Block WooCommerce's native registration when the full mode is active.
	 *
	 * @param \WP_Error $errors   Validation errors.
	 * @param string    $username Submitted username.
	 * @param string    $password Submitted password.
	 * @param string    $email    Submitted email.
	 * @return \WP_Error
	 */
	public function block_registration( $errors, $username, $password, $email ) {
		if ( $this->is_full_mode() ) {
			$errors->add(
				'wcfa_registration_blocked',
				__( 'Traditional registration is disabled. Please use the email code to continue.', 'woo-force-authentification-before-checkout' )
			);
		}

		return $errors;
	}
}
