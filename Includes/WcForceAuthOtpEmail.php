<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The class only makes sense when WooCommerce (WC_Email) is available. Loading
// the file without it is a no-op so the plugin keeps working on a store without
// WooCommerce active.
if ( ! class_exists( '\WC_Email' ) ) {
	return;
}

/**
 * WooCommerce email that delivers the one-time password (OTP) access code.
 *
 * Registering the OTP email as a real `WC_Email` makes it show up in the
 * WooCommerce email listings/preview and lets the store reuse WooCommerce's own
 * email template (header, footer, colors). The content (subject, heading,
 * message and footer note) is still driven by the plugin's own OTP settings.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthOtpEmail extends \WC_Email {

	/**
	 * The plain code included in the current email.
	 *
	 * @var string
	 */
	public $otp_code = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wc_force_auth_otp';
		$this->customer_email = true;
		$this->email_group    = 'accounts';
		$this->title          = __( 'OTP access code', 'woo-force-authentification-before-checkout' );
		$this->description    = __( 'One-time password (OTP) code sent to customers so they can sign in to their account.', 'woo-force-authentification-before-checkout' );

		$this->template_html  = 'emails/wc-force-auth-otp.php';
		$this->template_plain = 'emails/plain/wc-force-auth-otp.php';
		$this->template_block = 'emails/block/wc-force-auth-otp.php';
		$this->template_base  = WC_FORCE_AUTH_DIR . 'Includes/templates/';

		$this->placeholders = array(
			'{site_title}' => $this->get_blogname(),
			'{code}'       => '',
		);

		parent::__construct();

		if ( '' === (string) $this->email_type ) {
			$this->email_type = 'html';
		}

		// Hide WooCommerce's default header (store name) + heading bar for this
		// email only; the plugin prints the logo + a centered heading instead.
		add_filter( 'woocommerce_email_styles', array( $this, 'filter_email_styles' ), 10, 2 );
	}

	/**
	 * Append the rules that hide WooCommerce's own header for our email. Runs
	 * during inline styling (send path), so the hide is inlined — robust across
	 * email clients that ignore <style> blocks.
	 *
	 * @param string    $css   Inline CSS.
	 * @param \WC_Email $email Email being styled.
	 * @return string
	 */
	public function filter_email_styles( $css, $email = null ) {
		if ( $email instanceof \WC_Email && 'wc_force_auth_otp' === $email->id ) {
			$css .= "\n#template_header_image, #template_header { display: none !important; }";
		}

		return $css;
	}

	/**
	 * Retrieve the OTP module instance.
	 *
	 * @return WcForceAuthOtp|null
	 */
	private function otp() {
		return WcForceAuthOtp::instance();
	}

	/**
	 * Read an OTP setting (the single source of truth for the email content).
	 *
	 * @param string $key     Setting key (without the prefix).
	 * @param string $default Fallback default.
	 * @return string
	 */
	private function otp_option( $key, $default = '' ) {
		$otp = $this->otp();

		return $otp ? (string) $otp->option( $key, $default ) : (string) $default;
	}

	/**
	 * Replace the plugin's supported tokens in an email string.
	 *
	 * @param string $text Email text.
	 * @return string
	 */
	private function tokens( $text ) {
		$otp = $this->otp();

		if ( $otp ) {
			return $otp->replace_email_tokens( $text, $this->otp_code );
		}

		return str_replace( array( '{code}', '{site_name}', '{expiration}' ), array( $this->otp_code, get_bloginfo( 'name' ), '' ), (string) $text );
	}

	/**
	 * Default subject (overridable through the plugin's OTP setting).
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return $this->otp_option( 'email_subject', __( 'Your access code: {code}', 'woo-force-authentification-before-checkout' ) );
	}

	/**
	 * Default heading (overridable through the plugin's OTP setting).
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return $this->otp_option( 'email_heading', __( 'Your access code', 'woo-force-authentification-before-checkout' ) );
	}

	/**
	 * Subject, using the plugin's OTP setting as the source of truth.
	 *
	 * Supports the plugin tokens ({code}, {site_name}, {expiration}) and the
	 * legacy %s placeholder for the code.
	 *
	 * @return string
	 */
	public function get_subject() {
		$subject = $this->tokens( $this->get_default_subject() );

		if ( false !== strpos( $subject, '%s' ) ) {
			$subject = sprintf( $subject, $this->otp_code );
		}

		return apply_filters( 'woocommerce_email_subject_' . $this->id, $subject, $this->object, $this );
	}

	/**
	 * Heading, using the plugin's OTP setting as the source of truth.
	 *
	 * @return string
	 */
	public function get_heading() {
		$heading = $this->tokens( $this->get_default_heading() );

		return apply_filters( 'woocommerce_email_heading_' . $this->id, $heading, $this->object, $this );
	}

	/**
	 * Trigger the email for a given recipient and OTP code.
	 *
	 * @param string $email Recipient email address.
	 * @param string $code  The one-time password code.
	 * @return bool Whether wp_mail() reported success.
	 */
	public function trigger( $email, $code = '' ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$this->setup_locale();

		$this->otp_code                         = (string) $code;
		$this->recipient                        = $email;
		$this->placeholders['{code}']           = (string) $code;
		$this->placeholders['{otp_code}']       = (string) $code;

		$sent = false;

		if ( $this->get_recipient() ) {
			$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();

		return $sent;
	}

	/**
	 * Template variables shared by every rendering path.
	 *
	 * @return array
	 */
	private function template_args() {
		$otp = $this->otp();

		return array(
			'email_heading'      => $this->get_heading(),
			'otp_code'           => $this->otp_code,
			'email_message'      => $this->tokens( $this->otp_option( 'email_message', __( 'Use the code below to access your account:', 'woo-force-authentification-before-checkout' ) ) ),
			'email_footer_note'  => $this->tokens( $this->otp_option( 'email_footer_note', __( 'If you did not request this code, you can safely ignore this email.', 'woo-force-authentification-before-checkout' ) ) ),
			'expiration_minutes' => $otp ? $otp->get_expiration_minutes() : 10,
			// Both follow the source of the code: an integration that drives the
			// flow with its own screens (the block) sends a simple e-mail, site
			// name only. The store's own login screen keeps its logo.
			'logo_url'           => $otp ? $otp->get_email_logo_url() : '',
			'site_name'          => $otp ? $otp->get_email_site_name() : get_bloginfo( 'name' ),
			'email'              => $this,
		);
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args(), '', $this->template_base );
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args(), '', $this->template_base );
	}

	/**
	 * Content used by the block email editor as the initial template.
	 *
	 * @return string
	 */
	public function get_block_editor_email_template_content() {
		return wc_get_template_html( $this->template_block, $this->template_args(), '', $this->template_base );
	}
}
