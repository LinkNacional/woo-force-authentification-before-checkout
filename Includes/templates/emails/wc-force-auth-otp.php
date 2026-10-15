<?php
/**
 * OTP access code email (WooCommerce HTML template).
 *
 * Rendered through WooCommerce's email system so the store's header, footer and
 * colors are reused. Available variables:
 *
 * @var string $email_heading      Heading shown above the code.
 * @var string $otp_code           The one-time password code.
 * @var string $email_message      Intro message (may contain basic HTML).
 * @var string $email_footer_note  Closing note (may contain basic HTML).
 * @var int    $expiration_minutes Code expiration in minutes.
 * @var \WC_Email $email           The email object.
 *
 * @package Lkn\WcForceAuth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the including method, not globals.

// Render the WooCommerce wrapper (outer table + footer) but WITHOUT its default
// heading — the heading is printed below the logo, centered, to match the
// component/preview. WooCommerce's own header (store name + heading bar) is
// hidden via the `woocommerce_email_styles` filter in WcForceAuthOtpEmail.
do_action( 'woocommerce_email_header', '', $email );
?>

<?php if ( ! empty( $logo_url ) || '' !== (string) $site_name ) : ?>
	<div style="text-align:center;margin:8px 0 20px;">
		<?php if ( ! empty( $logo_url ) ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="max-height:64px;width:auto;display:block;margin:0 auto;">
		<?php endif; ?>
		<?php if ( '' !== (string) $site_name ) : ?>
			<div style="margin-top:12px;font-size:22px;font-weight:700;color:#121519;line-height:1.3;"><?php echo esc_html( $site_name ); ?></div>
		<?php endif; ?>
	</div>
<?php endif; ?>

<h2 style="text-align:center;margin:0 0 12px;font-size:20px;font-weight:700;color:#121519;line-height:1.3;">
	<?php echo esc_html( $email_heading ); ?>
</h2>

<p style="text-align:center;margin:0 0 24px;font-size:16px;line-height:1.6;color:#555555;">
	<?php echo wp_kses_post( $email_message ); ?>
</p>

<div style="text-align:center;margin:0 0 24px;">
	<div style="display:inline-block;background-color:#f6f9fc;border:2px solid #e9edf2;border-radius:10px;padding:18px 30px;">
		<?php /* Keep the code flush against the tags: the wrapper uses `user-select:all` (and double-click selects the whole node), so surrounding whitespace would be copied along with the code. */ ?>
		<div style="font-size:32px;font-weight:700;letter-spacing:6px;color:#121519;font-family:'Courier New',monospace;-webkit-user-select:all;user-select:all;cursor:text;"><?php echo esc_html( $otp_code ); ?></div>
	</div>
</div>

<p style="text-align:center;margin:0 0 24px;font-size:12px;color:#9aa3af;">
	<?php esc_html_e( 'Tip: tap or click the code once to select all of it, then copy.', 'woo-force-authentification-before-checkout' ); ?>
</p>

<p style="margin:0 0 24px;font-size:14px;line-height:1.6;">
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

<p style="margin:0;font-size:14px;line-height:1.6;color:#6b7280;">
	<?php echo wp_kses_post( $email_footer_note ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
