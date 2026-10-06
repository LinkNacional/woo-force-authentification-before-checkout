<?php
/**
 * OTP access code email — dynamic WooCommerce content block.
 *
 * This is the body injected into the WooCommerce (block) email editor as the
 * default template. It contains only the dynamic content; the header, footer
 * and overall styling come from the WooCommerce email theme.
 *
 * @var string $email_message      Intro message (may contain basic HTML).
 * @var string $otp_code           The one-time password code.
 * @var string $email_footer_note  Closing note (may contain basic HTML).
 * @var int    $expiration_minutes Code expiration in minutes.
 *
 * @package Lkn\WcForceAuth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the including method, not globals.
?>
<p style="text-align:center;font-size:16px;line-height:1.6;margin:0 0 24px;">
	<?php echo wp_kses_post( $email_message ); ?>
</p>

<div style="text-align:center;margin:0 0 8px;">
	<div style="display:inline-block;background-color:#f6f9fc;border:2px solid #e9edf2;border-radius:10px;padding:18px 30px;">
		<span style="font-size:32px;font-weight:700;letter-spacing:6px;color:#121519;font-family:'Courier New',monospace;"><?php echo esc_html( $otp_code ); ?></span>
	</div>
</div>

<p style="text-align:center;font-size:12px;color:#9aa3af;margin:0 0 24px;">
	<?php esc_html_e( 'Tip: tap or click the code once to select all of it, then copy.', 'woo-force-authentification-before-checkout' ); ?>
</p>

<p style="font-size:14px;line-height:1.6;margin:0 0 16px;">
	<strong><?php esc_html_e( 'Instructions:', 'woo-force-authentification-before-checkout' ); ?></strong><br>
	<?php
	printf(
		/* translators: %d: number of minutes before the code expires. */
		esc_html__( 'This code expires in %d minute(s).', 'woo-force-authentification-before-checkout' ),
		(int) $expiration_minutes
	);
	?><br>
	<?php esc_html_e( 'Do not share this code with anyone.', 'woo-force-authentification-before-checkout' ); ?>
</p>

<p style="font-size:14px;line-height:1.6;color:#6b7280;margin:0;">
	<?php echo wp_kses_post( $email_footer_note ); ?>
</p>
