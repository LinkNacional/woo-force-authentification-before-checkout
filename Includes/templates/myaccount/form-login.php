<?php
/**
 * My Account login/registration template — OTP replacement.
 *
 * This template is served via the `woocommerce_locate_template` filter so it
 * fully replaces WooCommerce's native form-login.php (and any theme override),
 * avoiding the fragile "inject markup then hide the native form with CSS/JS"
 * approach.
 *
 * In "Register and login" mode it renders the single 2-in-1 OTP flow (the code
 * creates the account when the email does not exist yet). In "Login only" mode
 * it renders the OTP login and keeps the native WooCommerce registration form
 * available, so new customers can still sign up the classic way.
 *
 * @package Lkn\WcForceAuth
 * @version 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the including method, not globals.

$wcfa_otp = \Lkn\WcForceAuth\Includes\WcForceAuthOtp::instance();

if ( ! $wcfa_otp instanceof \Lkn\WcForceAuth\Includes\WcForceAuthOtp ) {
	return;
}

do_action( 'woocommerce_before_customer_login_form' );

$wcfa_show_register = $wcfa_otp->should_show_native_registration();
?>
<div class="wcfa-account-auth<?php echo $wcfa_show_register ? ' wcfa-account-auth--split' : ''; ?>" style="--wcfa-primary: <?php echo esc_attr( $wcfa_otp->get_primary_color() ); ?>;">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped inside get_form_html().
	echo $wcfa_otp->get_form_html();
	?>

	<?php if ( $wcfa_show_register ) : ?>
		<div class="wcfa-account-register" id="customer_login">
			<h2 class="wcfa-register-title"><?php esc_html_e( 'Register', 'woo-force-authentification-before-checkout' ); ?></h2>

			<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?>>

				<?php do_action( 'woocommerce_register_form_start' ); ?>

				<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="reg_username"><?php esc_html_e( 'Username', 'woo-force-authentification-before-checkout' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woo-force-authentification-before-checkout' ); ?></span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>" required aria-required="true" />
					</p>

				<?php endif; ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_email"><?php esc_html_e( 'Email address', 'woo-force-authentification-before-checkout' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woo-force-authentification-before-checkout' ); ?></span></label>
					<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>" required aria-required="true" />
				</p>

				<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="reg_password"><?php esc_html_e( 'Password', 'woo-force-authentification-before-checkout' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woo-force-authentification-before-checkout' ); ?></span></label>
						<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
					</p>

				<?php else : ?>

					<p><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woo-force-authentification-before-checkout' ); ?></p>

				<?php endif; ?>

				<?php do_action( 'woocommerce_register_form' ); ?>

				<p class="woocommerce-form-row form-row">
					<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
					<button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woo-force-authentification-before-checkout' ); ?>"><?php esc_html_e( 'Register', 'woo-force-authentification-before-checkout' ); ?></button>
				</p>

				<?php do_action( 'woocommerce_register_form_end' ); ?>

			</form>
		</div>
	<?php endif; ?>
</div>
<?php
do_action( 'woocommerce_after_customer_login_form' );
