<?php
/**
 * Visual text editor (preview + inline pencil) for the OTP settings page.
 *
 * Renders a faithful preview of the authentication screens (Login / Verify /
 * Register) and the e-mail, reusing the SAME classes as the front-end so the
 * preview matches the real result. Every editable text carries an inline
 * pencil; edits are persisted into hidden inputs (`wc_force_auth_otp_*`) that
 * the settings form already saves.
 *
 * @package Lkn\WcForceAuth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the including method, not globals.

$wcfa = \Lkn\WcForceAuth\Includes\WcForceAuthOtp::instance();

if ( ! $wcfa instanceof \Lkn\WcForceAuth\Includes\WcForceAuthOtp ) {
	return;
}

$wcfa_groups = \Lkn\WcForceAuth\Includes\WcForceAuthOtp::editable_text_groups();
$wcfa_color  = $wcfa->get_primary_color();
$wcfa_color_style = $wcfa->get_color_style();
$wcfa_brand  = $wcfa->get_brand_name();
$wcfa_tag    = $wcfa->get_tagline();
$wcfa_logo   = $wcfa->get_logo_url();

// WooCommerce registration settings the preview must obey.
$wcfa_wc_registration = 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' );
$wcfa_gen_username    = 'no' === get_option( 'woocommerce_registration_generate_username', 'yes' );
$wcfa_gen_password    = 'no' === get_option( 'woocommerce_registration_generate_password', 'no' );

/**
 * Option value getter.
 */
$wcfa_opt = static function ( $key ) use ( $wcfa ) {
	return (string) $wcfa->option( $key );
};

/**
 * Render the hidden input that carries a text's value (saved by the form).
 */
$wcfa_hidden = static function ( $key ) use ( $wcfa_opt ) {
	printf(
		'<input type="hidden" class="wcfa-et-input" data-et="%1$s" name="%2$s" value="%3$s">',
		esc_attr( $key ),
		esc_attr( \Lkn\WcForceAuth\Includes\WcForceAuthOtp::OPTION_PREFIX . $key ),
		esc_attr( $wcfa_opt( $key ) )
	);
};

/**
 * Inline pencil button bound to a text key.
 */
$wcfa_pencil = static function ( $key, $label, $extra = '' ) {
	return sprintf(
		'<button type="button" class="wcfa-et-pencil %1$s" data-et="%2$s" data-label="%3$s" aria-label="%4$s" title="%4$s"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button>',
		esc_attr( $extra ),
		esc_attr( $key ),
		esc_attr( $label ),
		/* translators: %s: the field name being edited. */
		esc_attr( sprintf( __( 'Edit: %s', 'woo-force-authentification-before-checkout' ), $label ) )
	);
};

/**
 * Editable text (span + pencil).
 */
$wcfa_text = static function ( $key, $label, $tag = 'span', $class = '' ) use ( $wcfa_opt, $wcfa_pencil ) {
	return sprintf(
		'<%1$s class="%2$s"><span class="wcfa-et-item"><span class="wcfa-et-text" data-et="%3$s">%4$s</span>%5$s</span></%1$s>',
		$tag,
		esc_attr( $class ),
		esc_attr( $key ),
		esc_html( $wcfa_opt( $key ) ),
		$wcfa_pencil( $key, $label )
	);
};

/**
 * Editable placeholder (input + pencil overlay).
 */
$wcfa_ph_input = static function ( $key, $label, $class, $type = 'text' ) use ( $wcfa_opt, $wcfa_pencil ) {
	return sprintf(
		'<input type="%1$s" class="%2$s" data-et-ph="%3$s" placeholder="%4$s" disabled tabindex="-1">%5$s',
		esc_attr( $type ),
		esc_attr( $class ),
		esc_attr( $key ),
		esc_attr( $wcfa_opt( $key ) ),
		$wcfa_pencil( $key, $label, 'wcfa-et-pencil--over' )
	);
};

/**
 * Verification code preview, mirroring the front-end input style chosen in the
 * OTP settings (single field vs one box per digit, code length and format).
 */
$wcfa_code_preview = static function () use ( $wcfa ) {
	if ( $wcfa->is_segmented_input() ) {
		$html = '<div class="wcfa-otp-boxes" role="group" aria-hidden="true">';
		foreach ( $wcfa->get_code_group_sizes() as $group_index => $group_size ) {
			if ( $group_index > 0 ) {
				$html .= '<span class="wcfa-otp-sep" aria-hidden="true"></span>';
			}
			for ( $i = 0; $i < $group_size; $i++ ) {
				$html .= '<input type="text" class="wcfa-otp-box" maxlength="1" disabled tabindex="-1">';
			}
		}
		return $html . '</div>';
	}

	return sprintf(
		'<div class="wcfa-input-wrap wcfa-input-wrap--code"><span class="wcfa-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span><input type="text" class="wcfa-input wcfa-input--code" maxlength="%s" placeholder="%s" disabled tabindex="-1"></div>',
		esc_attr( (string) $wcfa->get_code_maxlength() ),
		esc_attr( $wcfa->get_code_placeholder() )
	);
};

/**
 * Editable button text. The pencil sits OUTSIDE the <button> (nested buttons are
 * invalid HTML and the inner click is unreliable).
 */
$wcfa_btn = static function ( $key, $label, $class ) use ( $wcfa_opt, $wcfa_pencil ) {
	return sprintf(
		'<span class="wcfa-et-btn-wrap"><button type="button" class="%1$s" disabled tabindex="-1"><span class="wcfa-et-text" data-et="%2$s">%3$s</span></button>%4$s</span>',
		esc_attr( $class ),
		esc_attr( $key ),
		esc_html( $wcfa_opt( $key ) ),
		$wcfa_pencil( $key, $label )
	);
};
?>
<div class="wcfa-editor" style="<?php echo $wcfa_color_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
	<nav class="wcfa-editor__tabs" role="tablist">
		<?php $wcfa_first = true; ?>
		<?php foreach ( $wcfa_groups as $wcfa_gid => $wcfa_group ) : ?>
			<button type="button" class="wcfa-editor__tab<?php echo $wcfa_first ? ' is-active' : ''; ?>" data-goto="<?php echo esc_attr( $wcfa_gid ); ?>" role="tab">
				<?php echo esc_html( $wcfa_group['title'] ); ?>
			</button>
			<?php $wcfa_first = false; ?>
		<?php endforeach; ?>
	</nav>

	<div class="wcfa-editor__canvas">
		<!-- LOGIN -->
		<div class="wcfa-editor__panel is-active" data-panel="login">
			<div class="wcfa-otp-wrapper" style="<?php echo $wcfa_color_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<div class="wcfa-otp-card">
					<div class="wcfa-brand">
						<img class="wcfa-logo" src="<?php echo esc_url( $wcfa_logo ); ?>" alt=""<?php echo $wcfa_logo ? '' : ' style="display:none"'; ?>>
						<span class="wcfa-brand-name"<?php echo '' !== $wcfa_brand ? '' : ' style="display:none"'; ?>><?php echo esc_html( $wcfa_brand ); ?></span>
						<span class="wcfa-tagline"<?php echo '' !== $wcfa_tag ? '' : ' style="display:none"'; ?>><?php echo esc_html( $wcfa_tag ); ?></span>
					</div>
					<div class="wcfa-step wcfa-step--login">
						<?php echo $wcfa_text( 'login_heading', __( 'Heading', 'woo-force-authentification-before-checkout' ), 'h2', 'wcfa-heading' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $wcfa_text( 'login_subheading', __( 'Subheading', 'woo-force-authentification-before-checkout' ), 'p', 'wcfa-subheading' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

						<div class="wcfa-form">
							<label class="wcfa-label"><?php echo $wcfa_text( 'login_label_email', __( 'Email label', 'woo-force-authentification-before-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
							<div class="wcfa-input-wrap">
								<span class="wcfa-input-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
								</span>
								<?php echo $wcfa_ph_input( 'login_placeholder_email', __( 'Email placeholder', 'woo-force-authentification-before-checkout' ), 'wcfa-input', 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<?php echo $wcfa_btn( 'login_button_text', __( 'Button', 'woo-force-authentification-before-checkout' ), 'wcfa-btn wcfa-btn--primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>

						<?php if ( '' !== trim( $wcfa_opt( 'legal_text' ) ) ) : ?>
							<?php echo $wcfa_text( 'legal_text', __( 'Legal text', 'woo-force-authentification-before-checkout' ), 'p', 'wcfa-legal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- VERIFY -->
		<div class="wcfa-editor__panel" data-panel="verify">
			<div class="wcfa-otp-wrapper" style="<?php echo $wcfa_color_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<div class="wcfa-otp-card">
					<div class="wcfa-step wcfa-step--verify">
						<div class="wcfa-badge" aria-hidden="true">
							<span class="wcfa-badge-lock">
								<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
							</span>
						</div>
						<?php echo $wcfa_text( 'verify_heading', __( 'Heading', 'woo-force-authentification-before-checkout' ), 'h2', 'wcfa-heading' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $wcfa_text( 'verify_subheading', __( 'Subheading', 'woo-force-authentification-before-checkout' ), 'p', 'wcfa-subheading' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

						<div class="wcfa-form">
							<label class="wcfa-label"><?php echo $wcfa_text( 'verify_label_code', __( 'Code label', 'woo-force-authentification-before-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
							<div class="wcfa-code-preview"><?php echo $wcfa_code_preview(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>							<?php echo $wcfa_btn( 'verify_button_text', __( 'Verify button', 'woo-force-authentification-before-checkout' ), 'wcfa-btn wcfa-btn--primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div class="wcfa-resend-row">
								<?php echo $wcfa_btn( 'verify_button_resend', __( 'Resend button', 'woo-force-authentification-before-checkout' ), 'wcfa-btn-resend' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<?php echo $wcfa_btn( 'verify_button_back', __( 'Back button', 'woo-force-authentification-before-checkout' ), 'wcfa-back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- REGISTER -->
		<div class="wcfa-editor__panel" data-panel="register">
			<?php if ( ! $wcfa_wc_registration ) : ?>
				<div class="wcfa-editor__notice">
					<?php esc_html_e( 'Registration is disabled in WooCommerce (Settings → Accounts and Privacy). Enable "Allow customers to create an account on the My Account page" to use the register texts.', 'woo-force-authentification-before-checkout' ); ?>
				</div>
			<?php endif; ?>
			<div class="wcfa-account-auth" style="<?php echo $wcfa_color_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<div class="wcfa-account-register">
					<?php echo $wcfa_text( 'register_heading', __( 'Heading', 'woo-force-authentification-before-checkout' ), 'h2', 'wcfa-register-title' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $wcfa_text( 'register_subheading', __( 'Subheading', 'woo-force-authentification-before-checkout' ), 'p', 'wcfa-register-subheading' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<div class="woocommerce-form-register">
						<div class="wcfa-register-fields">
							<?php if ( $wcfa_gen_username ) : ?>
								<div class="wcfa-field">
									<label class="wcfa-label"><?php echo $wcfa_text( 'register_label_username', __( 'Username label', 'woo-force-authentification-before-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
									<div class="wcfa-input-wrap">
										<span class="wcfa-input-icon" aria-hidden="true">
											<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
										</span>
										<?php echo $wcfa_ph_input( 'register_placeholder_username', __( 'Username placeholder', 'woo-force-authentification-before-checkout' ), 'wcfa-input' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								</div>
							<?php endif; ?>
							<div class="wcfa-field">
								<label class="wcfa-label"><?php echo $wcfa_text( 'register_label_email', __( 'Email label', 'woo-force-authentification-before-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
								<div class="wcfa-input-wrap">
									<span class="wcfa-input-icon" aria-hidden="true">
										<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
									</span>
									<?php echo $wcfa_ph_input( 'register_placeholder_email', __( 'Email placeholder', 'woo-force-authentification-before-checkout' ), 'wcfa-input', 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							</div>
							<?php if ( $wcfa_gen_password ) : ?>
								<div class="wcfa-field">
									<label class="wcfa-label"><?php echo $wcfa_text( 'register_label_password', __( 'Password label', 'woo-force-authentification-before-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
									<div class="wcfa-password">
										<span class="wcfa-input-icon" aria-hidden="true">
											<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
										</span>
										<?php echo $wcfa_ph_input( 'register_placeholder_password', __( 'Password placeholder', 'woo-force-authentification-before-checkout' ), 'wcfa-input wcfa-input--boxed' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								</div>
							<?php else : ?>
								<p class="wcfa-register-password-hint"><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woo-force-authentification-before-checkout' ); ?></p>
							<?php endif; ?>
						</div>

						<div class="wcfa-register-submit">
							<?php echo $wcfa_btn( 'register_button_text', __( 'Button', 'woo-force-authentification-before-checkout' ), 'wcfa-btn wcfa-btn--primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- EMAIL -->
		<div class="wcfa-editor__panel" data-panel="email">
			<div class="wcfa-mail">
				<div class="wcfa-mail__subject">
					<span class="wcfa-mail__label"><?php esc_html_e( 'Subject:', 'woo-force-authentification-before-checkout' ); ?></span>
					<?php echo $wcfa_text( 'email_subject', __( 'Subject', 'woo-force-authentification-before-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="wcfa-mail__body">
					<img class="wcfa-mail__logo" src="<?php echo esc_url( $wcfa_logo ); ?>" alt=""<?php echo $wcfa_logo ? '' : ' style="display:none"'; ?>>
					<div class="wcfa-mail__brand"<?php echo '' !== $wcfa_brand ? '' : ' style="display:none"'; ?>><?php echo esc_html( $wcfa_brand ); ?></div>
					<?php echo $wcfa_text( 'email_heading', __( 'Heading', 'woo-force-authentification-before-checkout' ), 'h2', 'wcfa-mail__heading' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $wcfa_text( 'email_message', __( 'Message', 'woo-force-authentification-before-checkout' ), 'p', 'wcfa-mail__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="wcfa-mail__code"><?php echo esc_html( substr( '12345678', 0, max( 1, min( 8, (int) $wcfa->get_code_length() ) ) ) ); ?></div>
					<?php echo $wcfa_text( 'email_footer_note', __( 'Footer note', 'woo-force-authentification-before-checkout' ), 'p', 'wcfa-mail__text wcfa-mail__text--muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
	</div>

	<p class="wcfa-editor__hint">
		<?php esc_html_e( 'Click the pencil next to any text to edit it. The value is saved together with the other settings.', 'woo-force-authentification-before-checkout' ); ?>
	</p>

	<?php foreach ( $wcfa_groups as $wcfa_group ) : ?>
		<?php foreach ( $wcfa_group['texts'] as $wcfa_text_def ) : ?>
			<?php $wcfa_hidden( $wcfa_text_def['key'] ); ?>
		<?php endforeach; ?>
	<?php endforeach; ?>
</div>
