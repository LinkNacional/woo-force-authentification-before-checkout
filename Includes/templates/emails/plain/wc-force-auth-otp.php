<?php
/**
 * OTP access code email (WooCommerce plain-text template).
 *
 * @var string $email_heading      Heading shown above the code.
 * @var string $otp_code           The one-time password code.
 * @var string $email_message      Intro message.
 * @var string $email_footer_note  Closing note.
 * @var int    $expiration_minutes Code expiration in minutes.
 *
 * @package Lkn\WcForceAuth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the including method, not globals.

echo esc_html( wp_strip_all_tags( (string) $email_heading ) ) . "\n\n";
echo esc_html( wp_strip_all_tags( (string) $email_message ) ) . "\n\n";
echo esc_html( (string) $otp_code ) . "\n\n";
printf(
	/* translators: %d: number of minutes before the code expires. */
	esc_html__( 'This code expires in %d minute(s). Do not share it with anyone.', 'woo-force-authentification-before-checkout' ),
	(int) $expiration_minutes
);
echo "\n\n";
echo esc_html( wp_strip_all_tags( (string) $email_footer_note ) ) . "\n";
