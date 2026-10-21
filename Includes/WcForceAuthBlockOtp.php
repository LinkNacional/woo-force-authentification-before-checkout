<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backs the code flow of the "LKN Forms - Login and Register" block.
 *
 * The block draws its own screens (they are saved in the page, with the page's
 * texts, colors and code rules) and drives them with its own script, which posts
 * to this plugin's endpoints. This plugin stays the owner of the flow itself: it
 * generates the code, e-mails it, validates it and signs the customer in.
 *
 * Two things cross between the plugins, and only two:
 *
 *  - the per-page settings the block stores (`otp` attribute), which this plugin
 *    takes as the *context* of the flow — the code rules and the e-mail subject
 *    of that page, instead of the store-wide ones;
 *  - the payload the block needs at render time (endpoints, nonce, signed
 *    context), handed out by `block_flow()`.
 *
 * Nothing of this plugin's markup is used in place of the block any more: the
 * page renders what it contains.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthBlockOtp {

	/**
	 * Block whose login form is replaced.
	 */
	public const BLOCK_NAME = 'lknform/login-register';

	/**
	 * The OTP implementation.
	 *
	 * @var WcForceAuthOtp
	 */
	protected $otp;

	/**
	 * Guard against rendering the template from inside itself.
	 *
	 * @var bool
	 */
	private static $rendering = false;

	/**
	 * Constructor.
	 *
	 * @param WcForceAuthOtp $otp The OTP implementation.
	 */
	public function __construct( WcForceAuthOtp $otp ) {
		$this->otp = $otp;
	}

	/**
	 * Block attributes that hold the text of a screen, and the setting each one
	 * fills in.
	 *
	 * The block edits its screens in the canvas (the texts are its elements), so
	 * the words arrive as block attributes; the screens are drawn here, from
	 * settings. This table is the bridge: it keeps the block free of any knowledge
	 * of these option names, and keeps this plugin free of the block's attribute
	 * names everywhere else.
	 *
	 * @return array<string,string> Attribute => setting key.
	 */
	public static function block_text_map() {
		return array(
			// Step 1 (the e-mail).
			'loginHeading'       => 'login_heading',
			'loginSubheading'    => 'login_subheading',
			'loginUserLabel'     => 'login_label_email',
			'loginUserPlaceholder' => 'login_placeholder_email',
			'loginButtonText'    => 'login_button_text',
			// Step 2 (the code).
			'otpVerifyHeading'   => 'verify_heading',
			'otpSentText'        => 'verify_subheading',
			'otpCodeLabel'       => 'verify_label_code',
			'otpCodePlaceholder' => 'verify_placeholder_code',
			'otpVerifyText'      => 'verify_button_text',
			'otpResendText'      => 'verify_button_resend',
			'otpBackText'        => 'verify_button_back',
			'otpReportText'      => 'verify_button_report',
		);
	}

	/**
	 * The settings a block carries, its own copy plus the texts it edits.
	 *
	 * @param array $block Parsed block.
	 * @return array<string,string>
	 */
	protected function settings_of_block( $block ) {
		$own = $this->settings_of( $block );

		if ( null === $own ) {
			return array();
		}

		$settings = $own['settings'];

		foreach ( self::block_text_map() as $attribute => $key ) {
			if ( isset( $block['attrs'][ $attribute ] ) && is_string( $block['attrs'][ $attribute ] ) && '' !== $block['attrs'][ $attribute ] ) {
				$settings[ $key ] = $block['attrs'][ $attribute ];
			}
		}

		return $settings;
	}

	/**
	 * The OTP settings a block carries, if it carries any.
	 *
	 * The block stores them with the page (`otp` attribute), which is what makes
	 * two login pages independent: each one asks for its own code, with its own
	 * rules and branding. A block saved before this existed has no such attribute
	 * and keeps following the store-wide settings.
	 *
	 * @param array $block Parsed block.
	 * @return array{enabled:bool,mode:string,settings:array<string,mixed>}|null Null when the block has no settings of its own.
	 */
	public function settings_of( $block ) {
		if ( ! is_array( $block ) || ! isset( $block['attrs']['otp'] ) || ! is_array( $block['attrs']['otp'] ) ) {
			return null;
		}

		$otp = $block['attrs']['otp'];

		$mode = isset( $otp['mode'] ) ? (string) $otp['mode'] : 'register_and_login';

		if ( ! in_array( $mode, array( 'login_only', 'register_and_login' ), true ) ) {
			$mode = 'register_and_login';
		}

		return array(
			'enabled'  => ! empty( $otp['enabled'] ),
			'mode'     => $mode,
			'settings' => isset( $otp['settings'] ) && is_array( $otp['settings'] ) ? $otp['settings'] : array(),
		);
	}

	/**
	 * What a brand-new block starts with, when it is told to use the code flow.
	 *
	 * The block stores its own settings with the page, so it needs a starting
	 * point: this is a snapshot of the store-wide ones at the moment the feature
	 * is turned on. From then on the page owns them — editing the store settings
	 * later does not reach back into the page, which is the point of the feature.
	 *
	 * The block plugin asks for this only to copy it into its own attribute; it
	 * does not interpret any of it.
	 *
	 * @return array{mode:string,settings:array<string,string>}
	 */
	public static function page_defaults() {
		$otp = WcForceAuthOtp::instance();

		if ( ! $otp instanceof WcForceAuthOtp ) {
			return array(
				'mode'     => 'register_and_login',
				'settings' => array(),
			);
		}

		$settings = array();

		foreach ( WcForceAuthOtp::context_keys() as $key ) {
			$settings[ $key ] = (string) $otp->option( $key );
		}

		// The mode is the block's own choice (it is the "Show" field of the block),
		// so it is not part of the snapshot.
		unset( $settings['enable_type'] );

		// The screen texts are block attributes (edited in the canvas), never
		// copies kept in this attribute: only the settings live here.
		foreach ( self::block_text_map() as $key ) {
			unset( $settings[ $key ] );
		}

		return array(
			'mode'     => 'register_and_login',
			'settings' => $settings,
		);
	}

	/**
	 * What the block's own script needs to run this page's code flow.
	 *
	 * Called by the block plugin at render time (it fills a `data-` attribute of
	 * its markup): a nonce and a signed context cannot be stored in a post.
	 *
	 * The context is built from the block's own settings, so the code is sent and
	 * validated with that page's rules. With no settings of its own (an old
	 * block), the store-wide ones are reported instead — which is what the flow
	 * uses anyway.
	 *
	 * @param array $block Parsed block.
	 * @return array<string,mixed> Payload, or an empty array when the block does not want the code.
	 */
	public static function block_flow( $block ) {
		$otp = WcForceAuthOtp::instance();

		if ( ! $otp instanceof WcForceAuthOtp ) {
			return array();
		}

		$self = new self( $otp );
		$own  = $self->settings_of( $block );

		if ( null !== $own && ! $own['enabled'] ) {
			return array();
		}

		if ( null !== $own ) {
			$otp->use_context(
				array_merge( $self->settings_of_block( $block ), array( 'enable_type' => $own['mode'] ) )
			);
		}

		$flow = $otp->get_block_config();

		if ( null !== $own ) {
			// The context is what makes the server apply *this* page's rules, so it
			// travels with every request of the flow.
			$flow['context'] = $otp->context_token();
			$flow['mode']    = $own['mode'];

			$otp->clear_context();
		}

		return is_array( $flow ) ? $flow : array();
	}

}
