<?php

namespace Lkn\WcForceAuth\PublicView;

use Lkn\WcForceAuth\Includes\WcForceAuthOtp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * Delegates the OTP asset loading to the OTP module, which decides whether the
 * current request needs the authentication UI.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthPublic {

	/**
	 * OTP module.
	 *
	 * @var WcForceAuthOtp
	 */
	private $otp;

	/**
	 * Constructor.
	 *
	 * @param WcForceAuthOtp $otp The OTP module.
	 */
	public function __construct( WcForceAuthOtp $otp ) {
		$this->otp = $otp;
	}

	/**
	 * Enqueue the public styles.
	 *
	 * Hook: wp_enqueue_scripts.
	 */
	public function enqueue_styles() {
		$this->otp->enqueue_styles();
	}

	/**
	 * Enqueue the public scripts.
	 *
	 * Hook: wp_enqueue_scripts.
	 */
	public function enqueue_scripts() {
		$this->otp->enqueue_scripts();
	}
}
