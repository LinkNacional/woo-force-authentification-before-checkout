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

$wcfa_register_heading    = (string) $wcfa_otp->option( 'register_heading', __( 'Register', 'woo-force-authentification-before-checkout' ) );
$wcfa_register_subheading = (string) $wcfa_otp->option( 'register_subheading', __( 'Create your account with your email and a password.', 'woo-force-authentification-before-checkout' ) );
$wcfa_label_username      = (string) $wcfa_otp->option( 'register_label_username' );
$wcfa_label_email         = (string) $wcfa_otp->option( 'register_label_email' );
$wcfa_label_password      = (string) $wcfa_otp->option( 'register_label_password' );
$wcfa_ph_username         = (string) $wcfa_otp->option( 'register_placeholder_username' );
$wcfa_ph_email            = (string) $wcfa_otp->option( 'register_placeholder_email' );
$wcfa_ph_password         = (string) $wcfa_otp->option( 'register_placeholder_password' );
$wcfa_btn_text            = (string) $wcfa_otp->option( 'register_button_text' );
?>
<div class="wcfa-account-auth<?php echo $wcfa_show_register ? ' wcfa-account-auth--split' : ''; ?>" style="<?php echo $wcfa_otp->get_color_style(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside get_color_style(). ?>">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped inside get_form_html().
	echo $wcfa_otp->get_form_html();
	?>

	<?php if ( $wcfa_show_register ) : ?>
		<div class="wcfa-account-register" id="customer_login">
			<h2 class="wcfa-register-title"><?php echo esc_html( $wcfa_register_heading ); ?></h2>
			<?php if ( '' !== $wcfa_register_subheading ) : ?>
				<p class="wcfa-register-subheading"><?php echo esc_html( $wcfa_register_subheading ); ?></p>
			<?php endif; ?>

			<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?>>

				<?php do_action( 'woocommerce_register_form_start' ); ?>

				<div class="wcfa-register-fields">
					<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>

						<div class="wcfa-field">
							<label class="wcfa-label" for="reg_username">
								<?php echo esc_html( $wcfa_label_username ); ?>
								<span class="wcfa-required" aria-hidden="true">*</span>
								<span class="screen-reader-text"><?php esc_html_e( 'Required', 'woo-force-authentification-before-checkout' ); ?></span>
							</label>
							<div class="wcfa-input-wrap">
								<span class="wcfa-input-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
								</span>
								<input type="text" class="wcfa-input woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>" placeholder="<?php echo esc_attr( $wcfa_ph_username ); ?>" required aria-required="true" />
							</div>
						</div>

					<?php endif; ?>

					<div class="wcfa-field">
						<label class="wcfa-label" for="reg_email">
							<?php echo esc_html( $wcfa_label_email ); ?>
							<span class="wcfa-required" aria-hidden="true">*</span>
							<span class="screen-reader-text"><?php esc_html_e( 'Required', 'woo-force-authentification-before-checkout' ); ?></span>
						</label>
						<div class="wcfa-input-wrap" id="wcfa-reg-email-wrap">
							<span class="wcfa-input-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
							</span>
							<input type="email" class="wcfa-input woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>" placeholder="<?php echo esc_attr( $wcfa_ph_email ); ?>" required aria-required="true" />
						</div>
					</div>

					<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>

						<div class="wcfa-field">
							<label class="wcfa-label" for="reg_password">
								<?php echo esc_html( $wcfa_label_password ); ?>
								<span class="wcfa-required" aria-hidden="true">*</span>
								<span class="screen-reader-text"><?php esc_html_e( 'Required', 'woo-force-authentification-before-checkout' ); ?></span>
							</label>
							<div class="wcfa-password">
								<span class="wcfa-input-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
								</span>
								<input type="password" class="wcfa-input wcfa-input--boxed woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" placeholder="<?php echo esc_attr( $wcfa_ph_password ); ?>" required aria-required="true" />
							</div>
						</div>

					<?php else : ?>

						<p class="wcfa-register-password-hint"><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woo-force-authentification-before-checkout' ); ?></p>

					<?php endif; ?>
				</div>

				<?php do_action( 'woocommerce_register_form' ); ?>

				<div class="wcfa-register-submit">
					<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
					<button type="submit" class="wcfa-btn wcfa-btn--primary woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php echo esc_attr( $wcfa_btn_text ); ?>"><?php echo esc_html( $wcfa_btn_text ); ?></button>
				</div>

				<?php do_action( 'woocommerce_register_form_end' ); ?>

			</form>
		</div>
	<?php endif; ?>
</div>
<?php
do_action( 'woocommerce_after_customer_login_form' );
