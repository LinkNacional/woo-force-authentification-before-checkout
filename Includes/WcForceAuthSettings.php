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
	 * Notice shown above the save button, warning about email deliverability.
	 *
	 * @return array{title?: string, texts?: string[]}
	 */
	protected function get_save_notice(): array {
		return array(
			'title' => __( 'Email delivery', 'woo-force-authentification-before-checkout' ),
			'texts' => array(
				__( 'The one-time passwords are sent by email through your store\'s email system (WooCommerce). Make sure the store email address is correctly configured in WooCommerce → Settings → Emails.', 'woo-force-authentification-before-checkout' ),
				__( 'If your customers are not receiving the codes — or if your store is not sending emails at all — it usually is a mail delivery problem, not a plugin issue.', 'woo-force-authentification-before-checkout' ),
				__( 'In that case, install and configure an SMTP plugin (such as "WP Mail SMTP", "Fluent SMTP" or "Post SMTP") so WordPress can deliver emails reliably.', 'woo-force-authentification-before-checkout' ),
			),
		);
	}

	/**
	 * Settings fields consumed by the layout template.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		$p = WcForceAuthOtp::OPTION_PREFIX;

		$fields = array(
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
					'register_and_login' => 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' )
						? __( 'Register and login', 'woo-force-authentification-before-checkout' )
						/* translators: %s: the WooCommerce setting name. */
						: sprintf( __( 'Register and login (unavailable: enable “%s” in WooCommerce first)', 'woo-force-authentification-before-checkout' ), __( 'Allow customers to create an account', 'woocommerce' ) ),
				),
				'options_disabled'  => 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' ) ? array() : array( 'register_and_login' ),
				'default'           => 'disabled',
				'description'       => __( 'Turns email OTP (one-time password) authentication on and chooses how it behaves. While disabled, the default WooCommerce login and registration forms are used.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Authentication mode', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Defines whether customers sign in with an emailed code and whether new accounts can be created.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( '"Disabled": keeps the standard WooCommerce login/registration. "Login only": only customers that already have an account can sign in with a code (the native registration form stays available for new customers). "Register and login": the 2-in-1 flow — the customer types their email, and we log them in if the account exists or create a passwordless account with the same code if it does not.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'expiration_time' => array(
				'title'             => __( 'Code expiration in Minutes', 'woo-force-authentification-before-checkout' ),
				'type'              => 'number',
				'id'                => $p . 'expiration_time',
				'default'           => '10',
				'description'       => __( 'How long, in minutes, a code stays valid after it is sent to the customer.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Code expiration in Minutes', 'woo-force-authentification-before-checkout' ),
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
				'preview_link'      => true,
				'default'           => '#22C1DC',
				'description'       => __( 'Accent color applied to the buttons and highlights on the OTP screens.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Primary color', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Color of the "Login", "Resend" and "Verify & Continue" buttons.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Pick a color or type a hex value (for example #22C1DC). If left empty or invalid, the default #22C1DC is used.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'card_bg' => array(
				'title'             => __( 'Card background', 'woo-force-authentification-before-checkout' ),
				'type'              => 'color',
				'id'                => $p . 'card_bg',
				'preview_link'      => true,
				'default'           => '#ffffff',
				'description'       => __( 'Background color of the authentication card.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Card background', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Surface behind the whole form.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'The text color adapts automatically for legibility. Default: white.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'input_bg' => array(
				'title'             => __( 'Input background', 'woo-force-authentification-before-checkout' ),
				'type'              => 'color',
				'id'                => $p . 'input_bg',
				'preview_link'      => true,
				'default'           => '#ffffff',
				'description'       => __( 'Background color of the input fields and the code boxes.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Input background', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Surface of the e-mail / password / code fields.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'The typed text color adapts automatically for legibility. Default: white.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'brand_name_show' => array(
				'title'             => __( 'Display brand name', 'woo-force-authentification-before-checkout' ),
				'type'              => 'radio',
				'id'                => $p . 'brand_name_show',
				'options'           => array(
					'yes' => __( 'Show', 'woo-force-authentification-before-checkout' ),
					'no'  => __( 'Hide', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'yes',
				'description'       => __( 'Controls whether the brand name is shown on the authentication forms.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Display brand name', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Show or hide the brand name on the forms.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'When "Hide" is selected, the brand name is removed from the card and the email.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'brand_name' => array(
				'title'             => __( 'Brand name', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'brand_name',
				'preview_link'      => true,
				'default'           => '',
				'join'              => $p . 'brand_name_show',
				'dim_when'          => 'no',
				'block_title'       => __( 'Brand name', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Text displayed above the sign-in form.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Pre-filled with the site name (Settings → General).', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'tagline_show' => array(
				'title'             => __( 'Display tagline', 'woo-force-authentification-before-checkout' ),
				'type'              => 'radio',
				'id'                => $p . 'tagline_show',
				'options'           => array(
					'yes' => __( 'Show', 'woo-force-authentification-before-checkout' ),
					'no'  => __( 'Hide', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'no',
				'description'       => __( 'Controls whether the tagline is shown on the authentication forms.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Display tagline', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Show or hide the tagline on the forms.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'When "Hide" is selected, the tagline is removed from the card.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'brand_tagline' => array(
				'title'             => __( 'Tagline', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'brand_tagline',
				'preview_link'      => true,
				'default'           => '',
				'join'              => $p . 'tagline_show',
				'dim_when'          => 'no',
				'block_title'       => __( 'Tagline', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Secondary line shown under the brand name.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Pre-filled with the site tagline (Settings → General).', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'logo_show' => array(
				'title'             => __( 'Display logo', 'woo-force-authentification-before-checkout' ),
				'type'              => 'radio',
				'id'                => $p . 'logo_show',
				'options'           => array(
					'yes' => __( 'Show', 'woo-force-authentification-before-checkout' ),
					'no'  => __( 'Hide', 'woo-force-authentification-before-checkout' ),
				),
				'default'           => 'yes',
				'description'       => __( 'Controls whether the logo is shown on the authentication forms.', 'woo-force-authentification-before-checkout' ),
				'block_title'       => __( 'Display logo', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Show or hide the logo on the forms.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'When "Hide" is selected, the logo is removed from the card and the email.', 'woo-force-authentification-before-checkout' ),
			),
			$p . 'logo_url' => array(
				'title'             => __( 'Logo URL', 'woo-force-authentification-before-checkout' ),
				'type'              => 'text',
				'id'                => $p . 'logo_url',
				'preview_link'      => true,
				'default'           => '',
				'join'              => $p . 'logo_show',
				'dim_when'          => 'no',
				'block_title'       => __( 'Logo URL', 'woo-force-authentification-before-checkout' ),
				'block_sub_title'   => __( 'Logo displayed on the login and verification screens.', 'woo-force-authentification-before-checkout' ),
				'input_description' => __( 'Pre-filled with the site icon (Settings → General).', 'woo-force-authentification-before-checkout' ),
			),
			'texts_title' => array(
				'title'    => __( 'Texts', 'woo-force-authentification-before-checkout' ),
				'type'     => 'title',
				'id'       => $p . 'texts_section',
				'block_id' => 'texts',
			),
			$p . 'text_editor' => array(
				'title' => __( 'Texts', 'woo-force-authentification-before-checkout' ),
				'type'  => 'editor',
				'id'    => $p . 'text_editor',
			),
		);

		return $this->lock_mode_field( $fields, $p . 'enable_type' );
	}

	/**
	 * Show the authentication mode as "Disabled" and lock it while a page of the
	 * "LKN Forms - Login and Register" block is the store's login page.
	 *
	 * Only one login screen can own the store: when that block page is the login
	 * page, the OTP stays off (its configuration lives in the other plugin's
	 * block, with the LKN Forms settings). Removing the block page from that role
	 * gives the OTP back.
	 *
	 * @param array  $fields Settings fields.
	 * @param string $key    The authentication-mode field key.
	 * @return array
	 */
	private function lock_mode_field( array $fields, $key ) {
		if ( ! isset( $fields[ $key ] ) || ! WcForceAuthOtp::login_page_owned_by_block() ) {
			return $fields;
		}

		$fields[ $key ]['options_disabled'] = array_keys( (array) $fields[ $key ]['options'] );
		$fields[ $key ]['description']      = sprintf(
			/* translators: %s: LKN Forms settings screen link. */
			__( 'The login screen is currently rendered by a page of the "LKN Forms - Login and Register" block, so email OTP is off and one-time passwords are not sent. Change the block page in %s, or remove it from that role, to use OTP again.', 'woo-force-authentification-before-checkout' ),
			'<a href="' . esc_url( admin_url( 'options-general.php?page=lknform' ) ) . '">' . esc_html__( 'Settings > LKN Forms', 'woo-force-authentification-before-checkout' ) . '</a>'
		);

		return $fields;
	}

	/**
	 * Resolve a default value, honoring mode-aware texts (see
	 * WcForceAuthOtp::default_for()).
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	protected function default_value( $key ) {
		return WcForceAuthOtp::default_for( $key );
	}

	/**
	 * Normalize a stored value so a text still equal to a known default keeps
	 * following the current mode (see WcForceAuthOtp::normalize_stored_value()).
	 *
	 * Also reports the authentication mode as "disabled" while a page of the
	 * "LKN Forms - Login and Register" block is the store's login page: that page
	 * owns the login screen, so the OTP stays off (and its field is locked).
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Stored value.
	 * @return mixed
	 */
	protected function normalize_value( $key, $value ) {
		if ( 'enable_type' === $key && WcForceAuthOtp::login_page_owned_by_block() ) {
			// The block page owns the login screen: the OTP is off whatever is stored.
			return 'disabled';
		}

		return WcForceAuthOtp::normalize_stored_value( $key, $value );
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
				if ( WcForceAuthOtp::login_page_owned_by_block() ) {
					// Locked: never overwrite the mode the store chose. The screen
					// shows (and get_mode() reports) "disabled" while a block page
					// owns the login screen, so saving the other fields must not
					// destroy that choice.
					return WcForceAuthOtp::stored_mode();
				}

				if ( 'register_and_login' === $value && 'yes' !== get_option( 'woocommerce_enable_myaccount_registration', 'no' ) ) {
					// Registration is disabled in WooCommerce: never create accounts.
					return 'login_only';
				}

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

			case 'brand_name_show':
			case 'tagline_show':
			case 'logo_show':
				return 'no' === $value ? 'no' : 'yes';

			case 'card_bg':
			case 'input_bg':
				$color = sanitize_hex_color( $value );
				return $color ? $color : '#ffffff';

			case 'logo_url':
				return esc_url_raw( $value );

			case 'legal_text':
			case 'email_message':
			case 'email_footer_note':
				return wp_kses_post( $value );

			default:
				return sanitize_text_field( $value );
		}
	}
}
