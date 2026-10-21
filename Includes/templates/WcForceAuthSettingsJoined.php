<?php
/**
 * Renders the joined (child) fields of a settings field.
 *
 * Expects the local variable `$joined_children` (array of child field defs).
 * Extracted from WcForceAuthSettingsLayout.php so it can be rendered as the
 * joined child block of a parent field (the `join` mechanism).
 *
 * @package Lkn\WcForceAuth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $joined_children ) || ! is_array( $joined_children ) ) {
	return;
}

$provider_groups = array();
foreach ( $joined_children as $wcfa_child_key => $wcfa_child_field ) {
	$provider_key = !empty( $wcfa_child_field['provider'] ) ? $wcfa_child_field['provider'] : '';
	$provider_groups[ $provider_key ][ $wcfa_child_key ] = $wcfa_child_field;
}
foreach ( $provider_groups as $wcfa_provider_key => $wcfa_group_children ) :
	$wcfa_wrap_provider_group = '' !== $wcfa_provider_key;
	if ( $wcfa_wrap_provider_group ) {
		echo '<div class="admin-layout-provider-group" data-captcha-provider="' . esc_attr( $wcfa_provider_key ) . '">';
	}
	foreach ( $wcfa_group_children as $child_key => $child_field ) :
		?>
	<div class="admin-layout-joined-child" data-wcfa-controlled-by="<?php echo esc_attr( !empty( $child_field['join'] ) ? $child_field['join'] : '' ); ?>" data-wcfa-field="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>"<?php
	if ( !empty( $child_field['show_when'] ) ) {
		echo ' data-wcfa-show-when="' . esc_attr( $child_field['show_when'] ) . '"';
	}
	if ( !empty( $child_field['dim_when'] ) ) {
		echo ' data-wcfa-dim-when="' . esc_attr( $child_field['dim_when'] ) . '"';
	}
	?>>
	<div class="admin-layout-joined-label-desc">
		<?php if ( !empty( $child_field['block_title'] ) ) : ?>
			<span class="admin-layout-label">
				<?php echo esc_html( $child_field['block_title'] ); ?>
			</span>
		<?php endif; ?>
		<?php
		$sub = !empty( $child_field['block_sub_title'] ) ? $child_field['block_sub_title'] : ( !empty( $child_field['description'] ) ? $child_field['description'] : '' );
		if ( $sub ) :
			?>
			<span class="admin-layout-description admin-layout-joined-description">
				<?php echo esc_html( $sub ); ?>
			</span>
		<?php endif; ?>
	</div>
	<div class="admin-layout-joined-component-bg">
		<hr class="admin-layout-hr">
		<div class="admin-layout-field-input-wrapper">
			<?php
			$wcfa_show_preview = ! empty( $child_field['preview_link'] ) && in_array( $child_field['type'], array( 'text', 'number', 'color' ), true );
			// `fill_with`: a button beside the input that fills it with a page URL
			// (e.g. the WooCommerce "My account" page), so nobody has to copy it.
			$wcfa_fill_url = ! empty( $child_field['fill_with'] ) && in_array( $child_field['type'], array( 'text', 'number' ), true ) && function_exists( 'wc_get_page_permalink' )
				? (string) wc_get_page_permalink( $child_field['fill_with'] )
				: '';
			$wcfa_input_row = $wcfa_show_preview || '' !== $wcfa_fill_url;
			if ( $wcfa_input_row ) {
				echo '<div class="admin-layout-input-row">';
			}

			switch ( $child_field['type'] ) {
				case 'text':
				case 'password':
				case 'number':
					?>
					<input type="<?php echo esc_attr( $child_field['type'] ); ?>" name="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" id="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" value="<?php echo esc_attr( get_option( $child_field['id'] ? $child_field['id'] : $child_key ) ); ?>" class="admin-layout-input" />
					<?php
					break;
				case 'color':
					?>
					<input type="color" name="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" id="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" value="<?php echo esc_attr( get_option( $child_field['id'] ? $child_field['id'] : $child_key ) ); ?>" class="admin-layout-input" />
					<?php
					break;
				case 'url':
					$url = !empty( $child_field['default'] ) ? $child_field['default'] : 'www.customlink.com';
					$label = !empty( $child_field['label'] ) ? $child_field['label'] : 'Link URL';
					echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $label ) . '</a>';
					break;
				case 'button':
					$button_class = 'admin-layout-button';
					if ( empty( $child_field['class'] ) ) {
						$button_class .= ' button button-primary';
					} else {
						$button_class .= ' ' . esc_attr( $child_field['class'] );
					}
					?>
					<button type="button" id="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" class="<?php echo esc_attr( $button_class ); ?>"><?php echo !empty( $child_field['label'] ) ? esc_html( $child_field['label'] ) : esc_html( $child_field['title'] ); ?></button>
					<?php
					break;
				case 'textarea':
					?>
					<textarea name="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" id="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" class="admin-layout-textarea"><?php echo esc_textarea( get_option( $child_field['id'] ? $child_field['id'] : $child_key ) ); ?></textarea>
					<?php
					break;
				case 'checkbox':
					?>
					<div class="admin-layout-checkbox-wrapper">
						<label class="admin-layout-checkbox-label">
							<input type="checkbox" name="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" id="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" class="admin-layout-checkbox" <?php checked( get_option( $child_field['id'] ?: $child_key, $child_field['default'] ?? '' ) === 'yes' ); ?> />
							<?php if ( !empty( $child_field['label'] ) ) : ?>
								<span class="admin-layout-checkbox-label-text"><?php echo wp_kses_post( $child_field['label'] ); ?></span>
							<?php endif; ?>
						</label>
					</div>
					<?php
					break;
				case 'radio':
					if ( !empty( $child_field['options'] ) && is_array( $child_field['options'] ) ) {
						$radio_uid = 0;
						foreach ( $child_field['options'] as $option_value => $option_label ) {
							$radio_uid++;
							?>
							<label class="admin-layout-radio-label">
								<input type="radio" name="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( (string) get_option( $child_field['id'] ?: $child_key, $child_field['default'] ?? '' ), (string) $option_value ); ?> id="<?php echo esc_attr( ( $child_field['id'] ? $child_field['id'] : $child_key ) . '_' . $radio_uid ); ?>" class="<?php echo !empty( $child_field['class'] ) ? esc_attr( $child_field['class'] ) : 'admin-layout-radio'; ?>" />
								<span class="admin-layout-radio-label-text"><?php echo esc_html( $option_label ); ?></span>
							</label>
							<?php
						}
					}
					break;
				case 'multicheck':
					if ( !empty( $child_field['options'] ) && is_array( $child_field['options'] ) ) {
						foreach ( $child_field['options'] as $option_key => $option_label ) {
							$option_id      = ( $child_field['id'] ?: $child_key ) . '_' . $option_key;
							$option_default = $child_field['options_checked'][ $option_key ] ?? $child_field['default'] ?? '';
							$option_desc    = $child_field['options_input_description'][ $option_key ] ?? '';
							?>
							<div class="admin-layout-checkbox-wrapper">
								<label class="admin-layout-checkbox-label">
									<input type="checkbox" name="<?php echo esc_attr( $option_id ); ?>" id="<?php echo esc_attr( $option_id ); ?>" class="admin-layout-checkbox" <?php checked( get_option( $option_id, $option_default ) === 'yes' ); ?> />
									<span class="admin-layout-checkbox-label-text"><?php echo wp_kses_post( $option_label ); ?></span>
								</label>
								<?php if ( !empty( $option_desc ) ) : ?>
									<div class="admin-layout-input-description"><?php echo esc_html( $option_desc ); ?></div>
								<?php endif; ?>
							</div>
							<?php
						}
					}
					break;
				case 'select':
					?>
					<div class="admin-layout-select-row">
						<select name="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" id="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" class="admin-layout-select">
							<?php foreach ( $child_field['options'] as $option_key => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) get_option( $child_field['id'] ?: $child_key, $child_field['default'] ?? '' ), (string) $option_key ); ?>><?php echo esc_html( $option_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php
					break;
				case 'file':
					?>
					<input type="file" name="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" id="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>" class="admin-layout-file" />
					<?php
					$file_path = get_option( $child_field['id'] ? $child_field['id'] : $child_key );
					if ( !empty( $file_path ) ) {
						$file_name = basename( $file_path );
						?>
						<div class="admin-layout-file-current">
							<span><?php esc_html_e( 'Last file uploaded:', 'woo-force-authentification-before-checkout' ); ?><strong><?php echo esc_html( $file_name ); ?></strong></span>
						</div>
						<?php
					}
					break;
			}

			if ( $wcfa_show_preview ) {
				?>
				<button type="button" class="admin-layout-preview-link wcfa-preview-open">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<span class="admin-layout-preview-link__text"><?php esc_html_e( 'Visualizar', 'woo-force-authentification-before-checkout' ); ?></span>
				</button>
				<?php
			}
			if ( '' !== $wcfa_fill_url ) {
				?>
				<button
					type="button"
					class="admin-layout-preview-link admin-layout-fill-link"
					data-wcfa-fill="<?php echo esc_url( $wcfa_fill_url ); ?>"
					data-wcfa-fill-target="<?php echo esc_attr( $child_field['id'] ? $child_field['id'] : $child_key ); ?>"
				>
					<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
					<span class="admin-layout-preview-link__text"><?php echo esc_html( ! empty( $child_field['fill_with_label'] ) ? $child_field['fill_with_label'] : __( 'Use the \'My account\' page', 'woo-force-authentification-before-checkout' ) ); ?></span>
				</button>
				<?php
			}
			if ( $wcfa_input_row ) {
				echo '</div>';
			}
			if ( !empty( $child_field['input_description'] ) ) :
				?>
				<div class="admin-layout-input-description"><?php echo esc_html( $child_field['input_description'] ); ?></div>
			<?php endif; ?>
			<?php if ( !empty( $child_field['input_tab_link'] ) ) : ?>
				<div class="admin-layout-input-tab-link"><?php echo wp_kses( $child_field['input_tab_link'], array( 'a' => array( 'href' => array(), 'data-goto-tab' => array() ) ) ); ?></div>
			<?php endif; ?>
			<?php if ( !empty( $child_field['input_warning'] ) ) : ?>
				<div class="admin-layout-input-warning"><?php echo wp_kses( $child_field['input_warning'], array( 'strong' => array(), 'a' => array( 'href' => array(), 'target' => array() ), 'br' => array() ) ); ?></div>
			<?php endif; ?>
		</div>
	</div>
	</div>
		<?php
	endforeach;
	if ( $wcfa_wrap_provider_group ) {
		echo '</div>';
	}
endforeach;
