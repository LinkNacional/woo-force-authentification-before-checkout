<?php
/**
 * OTP email template.
 *
 * Available variables:
 *
 * @var string $otp_code           The one-time password code.
 * @var string $site_name          Site/brand name.
 * @var string $site_url           Site URL.
 * @var int    $expiration_minutes Code expiration in minutes.
 * @var string $primary_color      Brand accent color.
 *
 * @package Lkn\WcForceAuth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the including method, not globals.

$wcfa_color = isset( $primary_color ) && preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $primary_color )
	? $primary_color
	: '#22C1DC';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;">
	<div style="max-width:600px;margin:0 auto;background-color:#ffffff;">
		<div style="padding:40px 30px 20px;text-align:center;border-bottom:1px solid #e9edf2;">
			<h1 style="margin:0;font-size:22px;font-weight:600;color:#121519;">
				<?php echo esc_html( $site_name ); ?>
			</h1>
		</div>

		<div style="padding:40px 30px;">
			<h2 style="margin:0 0 16px;font-size:20px;font-weight:600;color:#121519;line-height:1.3;">
				<?php esc_html_e( 'Your access code', 'woo-force-authentification-before-checkout' ); ?>
			</h2>

			<p style="margin:0 0 28px;font-size:16px;line-height:1.6;color:#555555;">
				<?php esc_html_e( 'Use the code below to access your account:', 'woo-force-authentification-before-checkout' ); ?>
			</p>

			<div style="text-align:center;margin:0 0 12px;">
				<div style="display:inline-block;background-color:#f6f9fc;border:2px solid #e9edf2;border-radius:10px;padding:18px 30px;">
					<div style="font-size:32px;font-weight:700;letter-spacing:6px;color:#121519;font-family:'Courier New',monospace;-webkit-user-select:all;user-select:all;cursor:text;">
						<?php echo esc_html( $otp_code ); ?>
					</div>
				</div>
			</div>

			<p style="text-align:center;margin:0 0 32px;font-size:12px;color:#9aa3af;">
				<?php esc_html_e( 'Tip: tap or click the code once to select all of it, then copy.', 'woo-force-authentification-before-checkout' ); ?>
			</p>

			<div style="background-color:#f6f9fc;border-left:4px solid <?php echo esc_attr( $wcfa_color ); ?>;padding:18px 20px;margin:0 0 28px;border-radius:0 6px 6px 0;">
				<p style="margin:0;font-size:14px;color:#5b6472;line-height:1.6;">
					<strong style="color:#121519;"><?php esc_html_e( 'Instructions:', 'woo-force-authentification-before-checkout' ); ?></strong><br>
					<?php
					printf(
						/* translators: %d: number of minutes before the code expires. */
						esc_html__( 'This code expires in %d minute(s).', 'woo-force-authentification-before-checkout' ),
						(int) $expiration_minutes
					);
					?><br>
					<?php esc_html_e( 'Do not share this code with anyone.', 'woo-force-authentification-before-checkout' ); ?>
				</p>
			</div>

			<p style="margin:0;font-size:14px;line-height:1.6;color:#6b7280;">
				<?php esc_html_e( 'If you did not request this code, you can safely ignore this email.', 'woo-force-authentification-before-checkout' ); ?>
			</p>
		</div>

		<div style="background-color:#f6f9fc;padding:26px 30px;text-align:center;border-top:1px solid #e9edf2;">
			<p style="margin:0;font-size:12px;color:#9aa3af;">
				<a href="<?php echo esc_url( $site_url ); ?>" style="color:#6b7280;text-decoration:none;">
					<?php echo esc_html( $site_name ); ?>
				</a>
			</p>
		</div>
	</div>
</body>
</html>
