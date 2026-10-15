<?php
/**
 * "Link Nacional" support card shown in the settings page sidebar.
 *
 * Available variables:
 *
 * @var string $backgrounds_right URL of the right background image.
 * @var string $backgrounds_left  URL of the left background image.
 * @var string $logo              URL of the Link Nacional logo.
 * @var string $whatsapp          URL of the WhatsApp icon.
 * @var string $telegram          URL of the Telegram icon.
 * @var string $versions          Version label (plugin + WooCommerce).
 *
 * @package Lkn\WcForceAuth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="linkn-link-settings-card" style="background-image: url('<?php echo esc_url( $backgrounds_right ); ?>'), url('<?php echo esc_url( $backgrounds_left ); ?>');">
	<div class="linkn-link-logo">
		<div>
			<img src="<?php echo esc_url( $logo ); ?>" alt="<?php esc_attr_e( 'Link Nacional', 'woo-force-authentification-before-checkout' ); ?>">
		</div>
		<p><?php echo esc_html( $versions ); ?></p>
	</div>
	<div class="linkn-link-content">
		<div class="linkn-link-links">
			<div>
				<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://wordpress.org/plugins/woo-force-authentification-before-checkout/' ); ?>">
					<b>&bull;</b><?php esc_html_e( 'Documentation', 'woo-force-authentification-before-checkout' ); ?>
				</a>
				<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://www.linknacional.com.br/wordpress/' ); ?>">
					<b>&bull;</b><?php esc_html_e( 'Hosting', 'woo-force-authentification-before-checkout' ); ?>
				</a>
			</div>
			<div>
				<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://www.linknacional.com.br/wordpress/plugins/' ); ?>">
					<b>&bull;</b><?php esc_html_e( 'WP Plugin', 'woo-force-authentification-before-checkout' ); ?>
				</a>
				<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://www.linknacional.com.br/wordpress/suporte/' ); ?>">
					<b>&bull;</b><?php esc_html_e( 'WP Support', 'woo-force-authentification-before-checkout' ); ?>
				</a>
			</div>
		</div>
		<div class="linkn-support-links">
			<div class="linkn-stars-div">
				<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/woo-force-authentification-before-checkout/reviews/' ); ?>">
					<p><?php esc_html_e( 'Rate the plugin', 'woo-force-authentification-before-checkout' ); ?></p>
					<div class="linkn-stars">
						<span class="dashicons dashicons-star-filled linkn-stars-icon"></span>
						<span class="dashicons dashicons-star-filled linkn-stars-icon"></span>
						<span class="dashicons dashicons-star-filled linkn-stars-icon"></span>
						<span class="dashicons dashicons-star-filled linkn-stars-icon"></span>
						<span class="dashicons dashicons-star-filled linkn-stars-icon"></span>
					</div>
				</a>
			</div>
			<div class="linkn-contact-links">
				<a href="<?php echo esc_url( 'https://chat.whatsapp.com/C6S3my9Adr818hbeJphPBm' ); ?>" target="_blank" rel="noopener noreferrer">
					<img src="<?php echo esc_url( $whatsapp ); ?>" alt="<?php esc_attr_e( 'WhatsApp', 'woo-force-authentification-before-checkout' ); ?>" class="linkn-contact-icon">
				</a>
				<a href="<?php echo esc_url( 'https://t.me/wpprobr' ); ?>" target="_blank" rel="noopener noreferrer">
					<img src="<?php echo esc_url( $telegram ); ?>" alt="<?php esc_attr_e( 'Telegram', 'woo-force-authentification-before-checkout' ); ?>" class="linkn-contact-icon">
				</a>
			</div>
		</div>
	</div>
</div>
