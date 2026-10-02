<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page for the OTP module.
 *
 * Renders the branded layout template and persists options through an AJAX
 * endpoint (mirrors the Link Nacional "layout + SweetAlert2" pattern).
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthSettings extends WcForceAuthSettingsPage {

	/**
	 * Admin page slug.
	 */
	public const PAGE_SLUG = 'wc-force-auth-otp';

	/**
	 * AJAX action / nonce action name.
	 */
	public const AJAX_ACTION = 'wc_force_auth_save_settings';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name The plugin name.
	 * @param string $version     The plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		parent::__construct( $plugin_name, $version );
	}

	/**
	 * Admin page slug.
	 *
	 * @return string
	 */
	public function get_page_slug(): string {
		return self::PAGE_SLUG;
	}

	/**
	 * AJAX action name.
	 *
	 * @return string
	 */
	public function get_ajax_action(): string {
		return self::AJAX_ACTION;
	}

	/**
	 * Option-name prefix.
	 *
	 * @return string
	 */
	public function get_option_prefix(): string {
		return WcForceAuthOtp::OPTION_PREFIX;
	}

	/**
	 * Default values for every OTP option.
	 *
	 * @return array
	 */
	public function get_defaults(): array {
		return WcForceAuthOtp::defaults();
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	public function get_page_title(): string {
		return __( 'Email OTP', 'woo-force-authentification-before-checkout' );
	}

	/**
	 * Settings fields consumed by the layout template.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		$p = WcForceAuthOtp::OPTION_PREFIX;

		return array(
			'general_title' => array(
				'title'    => __( 'General', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'general_section',
				'block_id' => 'general',
			),
			$p . 'enable_type' => array(
				'title'             => __( 'Authentication mode', 'woo-force-authentification-before-checkout' ),
				'type'              => 'select',
				'id'                => $p . 'enable_type',
				'options'           => array(
					'disabled'           => __( 'Disabled', 'woo-force-authentification-before-checkout' ),
					'login_only'         => __( 'Login only', 'woo-force-authentification-before-checkout' ),
					'register_and_login' => __( 'Register and login', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'disabled',
				'description'       => __( 'Turns email OTP (one-time password) authentication on and chooses how it behaves. While disabled, the default WooCommerce login and registration forms are used.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Authentication mode', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Defines whether customers sign in with an emailed code and whether new accounts can be created.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( '"Disabled": keeps the standard WooCommerce login/registration. "Login only": only customers that already have an account can sign in with a code (the native registration form stays available for new customers). "Register and login": the 2-in-1 flow — the customer types their email, and we log them in if the account exists or create a passwordless account with the same code if it does not.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'expiration_time' => array(
				'title'             => __( 'Code expiration', 'woo-force-authentification-before-checkout' ),
				'type'              => 'number',
				'id'                => $p . 'expiration_time',
				'default'           => '10',
				'description'       => __( 'How long, in minutes, a code stays valid after it is sent to the customer.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Code expiration', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Lifetime of each verification code.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Accepts 1 to 60 minutes. If left empty, 10 minutes is used. After this time the code stops working and the customer must request a new one.', 'woo-force-authentification-before-checkout' ),
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '60',
					'step' => '1',
				),
			),
			$p . 'resend_interval' => array(
				'title'             => __( 'Resend interval', 'woo-force-authentification-before-checkout' ),
				'type'              => 'number',
				'id'                => $p . 'resend_interval',
				'default'           => '30',
				'description'       => __( 'How long, in seconds, the customer must wait before requesting a new code.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Resend interval', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Countdown shown until the "Resend" button is enabled.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Accepts 0 to 600 seconds. If left empty, 30 seconds is used. This value also drives the countdown timer on the verification screen and prevents rapid repeated requests.', 'woo-force-authentification-before-checkout' ),
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '600',
					'step' => '1',
				),
			),
			$p . 'code_length' => array(
				'title'             => __( 'Code length', 'woo-force-authentification-before-checkout' ),
				'type'              => 'select',
				'id'                => $p . 'code_length',
				'options'           => array(
					'4' => __( '4 digits', 'woo-force-authentification-before-checkout' ),
					'6' => __( '6 digits', 'woo-force-authentification-before-checkout' ),
					'8' => __( '8 digits', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => '6',
				'description'       => __( 'How many digits the generated one-time password contains.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Code length', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Number of digits in the emailed code.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Choose 4, 6 or 8 digits. More digits are more secure but take longer to type. Default: 6.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'code_format' => array(
				'title'             => __( 'Code format', 'woo-force-authentification-before-checkout' ),
				'type'              => 'select',
				'id'                => $p . 'code_format',
				'options'           => array(
					'plain' => __( 'Plain (123456)', 'woo-force-authentification-before-checkout' ),
					'space' => __( 'Groups with space (123 456)', 'woo-force-authentification-before-checkout' ),
					'dash'  => __( 'Groups with dash (123-456)', 'woo-force-authentification-before-checkout' ),
					'pair'  => __( 'Pairs (12 34 56)', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'plain',
				'description'       => __( 'Visual grouping of the code field, matching common OTP layouts.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Code format', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'How the digits are grouped in the field.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'The separator is only visual: the code is sent without it. "Plain" keeps a single unbroken value (best for the browser one-time-code autofill).', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'code_input' => array(
				'title'             => __( 'Code input style', 'woo-force-authentification-before-checkout' ),
				'type'              => 'select',
				'id'                => $p . 'code_input',
				'options'           => array(
					'single'    => __( 'Single field', 'woo-force-authentification-before-checkout' ),
					'segmented' => __( 'One box per digit', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'single',
				'description'       => __( 'How the verification code is entered.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Code input style', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Single input, or a separate box for each digit.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( '"Single field": one input for the whole code (supports the "Code format" grouping). "One box per digit": a PIN-style row of boxes, one per digit (ignores the "Code format" grouping).', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'notification_style' => array(
				'title'             => __( 'Notification style', 'woo-force-authentification-before-checkout' ),
				'type'              => 'select',
				'id'                => $p . 'notification_style',
				'options'           => array(
					'floating' => __( 'Floating (toasts)', 'woo-force-authentification-before-checkout' ),
					'inline'   => __( 'Inline (inside the component)', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'floating',
				'description'       => __( 'Where the feedback messages (code sent, errors, confirmations) are shown.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Notification style', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Floating uses corner pop-ups; inline shows the messages inside the form.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( '"Floating": messages appear as floating pop-ups (top/bottom corner). "Inline": messages appear inside the form. The "code detected" alert always stays floating.', 'woo-force-authentification-before-checkout' ),
			),

			'appearance_title' => array(
				'title'    => __( 'Appearance', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'appearance_section',
				'block_id' => 'appearance',
			),
			$p . 'primary_color' => array(
				'title'             => __( 'Primary color', 'woo-force-authentification-before-checkout' ),
				'type'              => 'color',
				'id'                => $p . 'primary_color',
				'default'           => '#22C1DC',
				'description'       => __( 'Accent color applied to the buttons and highlights on the OTP screens.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Primary color', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Color of the "Login", "Resend" and "Verify & Continue" buttons.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Pick a color or type a hex value (for example #22C1DC). If left empty or invalid, the default #22C1DC is used.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'brand_name' => array(
				'title'             => __( 'Brand name', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'brand_name',
				'default'           => '',
				'description'       => __( 'Name shown at the top of the OTP screens when no logo is set.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Brand name', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Text displayed above the sign-in form.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Leave empty to use the site name defined in Settings → General.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'brand_tagline' => array(
				'title'             => __( 'Tagline', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'brand_tagline',
				'default'           => '',
				'description'       => __( 'Short uppercase line displayed under the brand name or logo.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Tagline', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Secondary line shown under the brand name.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Leave empty to use the site tagline defined in Settings → General.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'logo_url' => array(
				'title'             => __( 'Logo URL', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'logo_url',
				'default'           => '',
				'description'       => __( 'Image shown at the top of the OTP screens, replacing the brand name.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Logo URL', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Logo displayed on the login and verification screens.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Paste the full URL of an image. Leave empty to use the theme custom logo (Appearance → Customize → Site Identity).', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'login_heading' => array(
				'title'             => __( 'Login heading', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'login_heading',
				'default'           => __( 'Welcome back', 'woo-force-authentification-before-checkout' ),
				'description'       => __( 'Main heading of the first screen, where the customer enters their email.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Login heading', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Title shown at the top of the email step.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'If left empty, "Welcome back" is shown.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'login_subheading' => array(
				'title'             => __( 'Login subheading', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'login_subheading',
				'default'           => __( 'Enter your email to sign up or access your account', 'woo-force-authentification-before-checkout' ),
				'description'       => __( 'Explanatory text shown under the login heading, above the email field.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Login subheading', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Text shown under the login heading.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'If left empty, "Enter your email to sign up or access your account" is shown.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'verify_heading' => array(
				'title'             => __( 'Verification heading', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'verify_heading',
				'default'           => __( 'Verify your account', 'woo-force-authentification-before-checkout' ),
				'description'       => __( 'Main heading of the second screen, where the customer types the received code.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Verification heading', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Title shown at the top of the code step.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'If left empty, "Verify your account" is shown.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'verify_subheading' => array(
				'title'             => __( 'Verification subheading', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'verify_subheading',
				'default'           => __( 'Enter the verification code sent to your email', 'woo-force-authentification-before-checkout' ),
				'description'       => __( 'Explanatory text shown under the verification heading, above the code field.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Verification subheading', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Text shown under the verification heading.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'If left empty, "Enter the verification code sent to your email" is shown.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'otp_sent_message' => array(
				'title'             => __( 'OTP sent message', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'otp_sent_message',
				'default'           => __( 'A one-time password (OTP) has been sent to your registered email address.', 'woo-force-authentification-before-checkout' ),
				'description'       => __( 'Green confirmation box shown on the verification screen after the code is sent.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'OTP sent message', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Message confirming that the code was sent.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'If left empty, "A one-time password (OTP) has been sent to your registered email address." is shown.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'legal_text' => array(
				'title'             => __( 'Legal text', 'woo-force-authentification-before-checkout' ),
				'type'              => 'textarea',
				'id'                => $p . 'legal_text',
				'default'           => __( 'By clicking on "Login" you agree to our Privacy Policy and Terms of Service.', 'woo-force-authentification-before-checkout' ),
				'description'       => __( 'Small footer text shown under the form, usually about privacy policy and terms of service.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Legal text', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Footer text displayed below the form.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Basic HTML is allowed (for example links). If left empty, no footer text is shown.', 'woo-force-authentification-before-checkout' ),
			),

			'email_title' => array(
				'title'    => __( 'Email', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'email_section',
				'block_id' => 'email',
			),
			$p . 'email_subject' => array(
				'title'             => __( 'Email subject', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'email_subject',
				'default'           => __( 'Your access code', 'woo-force-authentification-before-checkout' ),
				/* translators: %s: placeholder token that is replaced by the code. */
				'description'       => __( 'Subject line of the email that delivers the code. Accepts a %s placeholder for the code.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Email subject', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Subject of the OTP email sent to the customer.', 'woo-force-authentification-before-checkout' ),
				/* translators: 1: placeholder token, 2: example of the subject line. */
				'input_description' => __( 'Use %1$s where the code should appear (for example "Your code: %2$s"). If left empty, "Your access code" is used.', 'woo-force-authentification-before-checkout' ),
			),

			'redirect_title' => array(
				'title'    => __( 'Redirect', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'redirect_section',
				'block_id' => 'redirect',
			),
			$p . 'redirect_url' => array(
				'title'             => __( 'Redirect after login', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'redirect_url',
				'default'           => '',
				'description'       => __( 'Page the customer is sent to right after the code is verified.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Redirect after login', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Destination after a successful login.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Enter a full URL (for example https://example.com/thank-you). Leave empty to send customers to their account dashboard.', 'woo-force-authentification-before-checkout' ),
			),
		);
	}

	/**
	 * Sanitize a single setting value according to its key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	protected function sanitize_option( $key, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		switch ( $key ) {
			case 'enable_type':
				return in_array( $value, array( 'disabled', 'login_only', 'register_and_login' ), true ) ? $value : 'disabled';

			case 'expiration_time':
				return (string) max( 1, min( 60, absint( $value ) ) );

			case 'resend_interval':
				return (string) max( 0, min( 600, absint( $value ) ) );

			case 'code_length':
				return in_array( (string) absint( $value ), array( '4', '6', '8' ), true ) ? (string) absint( $value ) : '6';

			case 'code_format':
				return in_array( $value, array( 'plain', 'space', 'dash', 'pair' ), true ) ? $value : 'plain';

			case 'code_input':
				return in_array( $value, array( 'single', 'segmented' ), true ) ? $value : 'single';

			case 'notification_style':
				return in_array( $value, array( 'floating', 'inline' ), true ) ? $value : 'floating';

			case 'primary_color':
				$color = sanitize_hex_color( $value );
				return $color ? $color : '#22C1DC';

			case 'logo_url':
			case 'redirect_url':
				return esc_url_raw( $value );

			case 'legal_text':
				return wp_kses_post( $value );

			default:
				return sanitize_text_field( $value );
		}
	}
}
