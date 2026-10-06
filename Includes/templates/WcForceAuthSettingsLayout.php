<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the including method, not globals.

if (!isset($form_fields) || !is_array($form_fields)) return;

// Agrupa campos por títulos (type 'title')
$blocks = [];
$current_block = null;
$first_block_id = null;
foreach ($form_fields as $key => $field) {
    if ($field['type'] === 'title') {
        $current_block = !empty($field['block_id']) ? $field['block_id'] : sanitize_title($field['title']);
        if ($first_block_id === null) {
            $first_block_id = $current_block;
        }
        $blocks[$current_block]['title'] = $field['title'];
        continue;
    }
    if ($current_block !== null) {
        $blocks[$current_block]['fields'][$key] = $field;
    }
}

// Indexa todos os campos para busca rápida de pai/filho
$field_index = [];
foreach ($form_fields as $key => $field) {
    $field_index[$key] = $field;
}

// Whether this screen ships the visual text editor (the "Texts" tab). When it
// does, appearance fields can open it in a preview modal.
$wcfa_has_editor = false;
foreach ($form_fields as $wcfa_field) {
    if (($wcfa_field['type'] ?? '') === 'editor') {
        $wcfa_has_editor = true;
        break;
    }
}
?>
<div class="admin-layout-page-wrapper">
    <div class="admin-layout-content">
        <div class="admin-layout-main">
            <div class="admin-layout-header">
                <h2 class="admin-layout-title"><?php echo esc_html($method_title); ?></h2>
            </div>
            <div class="admin-layout-top-menu-outer">
                <button type="button" class="admin-layout-nav-arrow admin-layout-nav-arrow--prev" aria-label="<?php esc_attr_e('Previous', 'woo-force-authentification-before-checkout'); ?>">&#8249;</button>
                <div class="admin-layout-top-menu-clip">
                    <nav class="admin-layout-top-menu">
                        <?php foreach ($blocks as $block_id => $block): ?>
                            <a href="#" id="nav-<?php echo esc_attr($block_id); ?>" class="admin-layout-title-link<?php echo $block_id === $first_block_id ? ' active' : ''; ?>" data-target="block-<?php echo esc_attr($block_id); ?>">
                                <?php echo esc_html($block['title'] ?? ucfirst($block_id)); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <button type="button" class="admin-layout-nav-arrow admin-layout-nav-arrow--next" aria-label="<?php esc_attr_e('Next', 'woo-force-authentification-before-checkout'); ?>">&#8250;</button>
            </div>
            <form id="wcfa-settings-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('woocommerce-options'); ?>
                <?php foreach ($blocks as $block_id => $block): ?>
                    <div class="admin-layout-block<?php echo $block_id === $first_block_id ? ' active' : ''; ?>" id="block-<?php echo esc_attr($block_id); ?>">
                        <?php
                        foreach (($block['fields'] ?? []) as $key => $field):
                            if (!empty($field['join'])) continue;

                            // Visual text editor: full-width, rendered on its own.
                            if (($field['type'] ?? '') === 'editor') {
                                include WC_FORCE_AUTH_DIR . 'Includes/templates/WcForceAuthTextEditor.php';
                                continue;
                            }

                            // Busca filhos deste campo
                            $children = [];
                            foreach (($block['fields'] ?? []) as $child_key => $child_field) {
                                if (!empty($child_field['join']) && $child_field['join'] === $key) {
                                    $children[$child_key] = $child_field;
                                }
                            }
                        ?>
                        <div class="admin-layout-field-parent-flex<?php echo !empty($field['hidden']) ? ' lkn-disabled-field' : ''; ?>">
                            <div class="admin-layout-field-label-desc">
                                <?php if (!empty($field['title']) && $field['type'] !== 'title'): ?>
                                    <span class="admin-layout-label">
                                        <?php echo esc_html($field['title']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($field['description'])): ?>
                                    <span class="admin-layout-description">
                                        <?php echo wp_kses_post($field['description']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-layout-field-component-bg">
                                <span class="admin-layout-label">
                                    <?php
                                    if (!empty($field['block_title'])) {
                                        echo esc_html($field['block_title']);
                                    } elseif (!empty($field['title']) && $field['type'] !== 'title') {
                                        echo esc_html($field['title']);
                                    }
                                    ?>
                                </span>
                                <?php
                                $sub = !empty($field['block_sub_title']) ? $field['block_sub_title'] : (!empty($field['description']) ? $field['description'] : '');
                                if ($sub):
                                ?>
                                    <span class="admin-layout-description">
                                        <?php echo wp_kses_post($sub); ?>
                                    </span>
                                <?php endif; ?>
                                <hr class="admin-layout-hr">
                                <div class="admin-layout-field-input-wrapper">
                                    <?php
                                    // Fields flagged with `preview_link` get a "Visualizar"
                                    // link beside the input (it drops below on narrow screens).
                                    $wcfa_show_preview = ! empty($field['preview_link']) && in_array($field['type'], array('text', 'number', 'color'), true);
                                    if ($wcfa_show_preview) {
                                        echo '<div class="admin-layout-input-row">';
                                    }

                                    // Renderiza o campo pai
                                    switch ($field['type']) {
                                        case 'text':
                                        case 'password':
                                        case 'number':
                                            ?>
                                            <input
                                                type="<?php echo esc_attr($field['type']); ?>"
                                                name="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                id="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                value="<?php echo esc_attr(get_option($field['id'] ? $field['id'] : $key)); ?>"
                                                class="admin-layout-input"
                                                <?php
                                                if (isset($field['custom_attributes']) && is_array($field['custom_attributes'])) {
                                                    foreach ($field['custom_attributes'] as $attr => $val) {
                                                        echo esc_attr($attr) . '="' . esc_attr($val) . '" ';
                                                    }
                                                }
                                                ?>
                                            />
                                            <?php
                                            break;
                                        case 'color':
                                            $color_val = get_option($field['id'] ? $field['id'] : $key);
                                            if (empty($color_val) || !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $color_val)) {
                                                $color_val = !empty($field['default']) ? $field['default'] : '#ffffff';
                                            }
                                            ?>
                                            <input
                                                type="color"
                                                name="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                id="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                value="<?php echo esc_attr($color_val); ?>"
                                                class="admin-layout-input"
                                            />
                                            <?php
                                            break;
                                        case 'url':
                                            $url = !empty($field['default']) ? $field['default'] : 'www.customlink.com';
                                            $label = !empty($field['label']) ? $field['label'] : 'Link URL';
                                            echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($label) . '</a>';
                                            break;
                                        case 'button':
                                            $button_class = 'admin-layout-button';
                                            if (empty($field['class'])) {
                                                $button_class .= ' button button-primary';
                                            } else {
                                                $button_class .= ' ' . esc_attr($field['class']);
                                            }
                                            ?>
                                            <button
                                                type="button"
                                                id="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                class="<?php echo esc_attr($button_class); ?>"
                                                <?php
                                                if (isset($field['custom_attributes']) && is_array($field['custom_attributes'])) {
                                                    foreach ($field['custom_attributes'] as $attr => $val) {
                                                        echo esc_attr($attr) . '="' . esc_attr($val) . '" ';
                                                    }
                                                }
                                                ?>
                                            ><?php echo !empty($field['label']) ? esc_html($field['label']) : esc_html($field['title']); ?></button>
                                            <?php
                                            break;
                                        case 'textarea':
                                            ?>
                                            <textarea
                                                name="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                id="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                class="admin-layout-textarea"
                                            ><?php echo esc_textarea(get_option($field['id'] ? $field['id'] : $key)); ?></textarea>
                                            <?php
                                            break;
                                        case 'checkbox':
                                            ?>
                                            <div class="admin-layout-checkbox-wrapper">
                                                <label class="admin-layout-checkbox-label">
                                                    <input
                                                        type="checkbox"
                                                        name="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                        id="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                        class="admin-layout-checkbox"
                                                        <?php checked(get_option($field['id'] ?: $key, $field['default'] ?? '') === 'yes'); ?>
                                                    />
                                                    <?php if (!empty($field['label'])): ?>
                                                        <span class="admin-layout-checkbox-label-text">
                                                            <?php echo wp_kses_post($field['label']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                            <?php
                                            break;
                                        case 'radio':
                                            if (!empty($field['options']) && is_array($field['options'])) {
                                                foreach ($field['options'] as $option_value => $option_label) {
                                                    ?>
                                                    <label class="admin-layout-radio-label">
                                                        <input
                                                            type="radio"
                                                            name="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                            value="<?php echo esc_attr($option_value); ?>"
                                                            <?php checked((string) get_option($field['id'] ?: $key, $field['default'] ?? ''), (string) $option_value); ?>
                                                            id="<?php echo esc_attr(($field['id'] ? $field['id'] : $key) . '_' . sanitize_title((string) $option_value)); ?>"
                                                            class="<?php echo !empty($field['class']) ? esc_attr($field['class']) : 'admin-layout-radio'; ?>"
                                                            <?php
                                                            if (isset($field['custom_attributes']) && is_array($field['custom_attributes'])) {
                                                                foreach ($field['custom_attributes'] as $attr => $val) {
                                                                    echo esc_attr($attr) . '="' . esc_attr($val) . '" ';
                                                                }
                                                            }
                                                            ?>
                                                        />
                                                        <span class="admin-layout-radio-label-text">
                                                            <?php echo esc_html($option_label); ?>
                                                        </span>
                                                    </label>
                                                    <?php
                                                }
                                            }
                                            break;
                                        case 'multicheck':
                                            if (!empty($field['options']) && is_array($field['options'])) {
                                                foreach ($field['options'] as $option_key => $option_label) {
                                                    $option_id      = ($field['id'] ?: $key) . '_' . $option_key;
                                                    $option_default = $field['options_checked'][ $option_key ] ?? $field['default'] ?? '';
                                                    $option_desc    = $field['options_input_description'][ $option_key ] ?? '';
                                                    ?>
                                                    <div class="admin-layout-checkbox-wrapper">
                                                        <label class="admin-layout-checkbox-label">
                                                            <input
                                                                type="checkbox"
                                                                name="<?php echo esc_attr($option_id); ?>"
                                                                id="<?php echo esc_attr($option_id); ?>"
                                                                class="admin-layout-checkbox"
                                                                <?php checked(get_option($option_id, $option_default) === 'yes'); ?>
                                                            />
                                                            <span class="admin-layout-checkbox-label-text">
                                                                <?php echo wp_kses_post($option_label); ?>
                                                            </span>
                                                        </label>
                                                        <?php if (!empty($option_desc)): ?>
                                                            <div class="admin-layout-input-description">
                                                                <?php echo esc_html($option_desc); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                            break;
                                        case 'select':
                                            ?>
                                            <div class="admin-layout-select-row">
                                                <select
                                                    name="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                    id="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                    class="admin-layout-select"
                                                >
                                                    <?php foreach ($field['options'] as $option_key => $option_label): ?>
                                                        <?php $option_disabled = !empty($field['options_disabled']) && in_array((string) $option_key, array_map('strval', $field['options_disabled']), true); ?>
                                                        <option value="<?php echo esc_attr($option_key); ?>" <?php selected((string) get_option($field['id'] ?: $key, $field['default'] ?? ''), (string) $option_key); ?> <?php disabled($option_disabled); ?>>
                                                            <?php echo esc_html($option_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <?php
                                            break;
                                        case 'file':
                                            ?>
                                            <input
                                                type="file"
                                                name="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                id="<?php echo esc_attr($field['id'] ? $field['id'] : $key); ?>"
                                                class="admin-layout-file"
                                            />
                                            <?php
                                            // Mostra apenas a mensagem e o nome do último arquivo salvo
                                            $file_path = get_option($field['id'] ? $field['id'] : $key);
                                            if (!empty($file_path)) {
                                                $file_name = basename($file_path);
                                                ?>
                                                <div class="admin-layout-file-current">
                                                    <span>
                                                        <?php esc_html_e('Last file uploaded:', 'woo-force-authentification-before-checkout'); ?>
                                                        <strong><?php echo esc_html($file_name); ?></strong>
                                                    </span>
                                                </div>
                                                <?php
                                            }
                                            break;
                                    }

                                    if ($wcfa_show_preview) {
                                        ?>
                                        <button type="button" class="admin-layout-preview-link wcfa-preview-open">
                                            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                            <span class="admin-layout-preview-link__text"><?php esc_html_e('Visualizar', 'woo-force-authentification-before-checkout'); ?></span>
                                        </button>
                                        <?php
                                        echo '</div>';
                                    }
                                    ?>
                                    <?php if (!empty($field['input_description'])): ?>
                                        <div class="admin-layout-input-description">
                                            <?php echo esc_html($field['input_description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($field['input_tab_link'])): ?>
                                        <div class="admin-layout-input-tab-link">
                                            <?php echo wp_kses($field['input_tab_link'], array('a' => array('href' => array(), 'data-goto-tab' => array()))); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($field['input_warning'])): ?>
                                        <div class="admin-layout-input-warning">
                                            <?php echo wp_kses($field['input_warning'], array('strong' => array(), 'a' => array('href' => array(), 'target' => array()), 'br' => array())); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $joined_children = $children;
                                include WC_FORCE_AUTH_DIR . 'Includes/templates/WcForceAuthSettingsJoined.php';
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($wcfa_save_notice)) : ?>
                    <div class="admin-layout-save-notice" role="note">
                        <span class="admin-layout-save-notice-icon dashicons dashicons-email" aria-hidden="true"></span>
                        <div class="admin-layout-save-notice-content">
                            <?php if (!empty($wcfa_save_notice['title'])) : ?>
                                <p class="admin-layout-save-notice-title"><?php echo esc_html($wcfa_save_notice['title']); ?></p>
                            <?php endif; ?>
                            <?php foreach (($wcfa_save_notice['texts'] ?? array()) as $wcfa_notice_text) : ?>
                                <p class="admin-layout-save-notice-text"><?php echo esc_html($wcfa_notice_text); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($wcfa_has_editor)) : ?>
                    <div class="wcfa-preview-modal" hidden aria-hidden="true">
                        <div class="wcfa-preview-modal__overlay" data-wcfa-preview-close></div>
                        <div class="wcfa-preview-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Preview', 'woo-force-authentification-before-checkout'); ?>">
                            <div class="wcfa-preview-modal__header">
                                <h3 class="wcfa-preview-modal__title"><?php esc_html_e('Preview', 'woo-force-authentification-before-checkout'); ?></h3>
                                <button type="button" class="wcfa-preview-modal__close" data-wcfa-preview-close aria-label="<?php esc_attr_e('Close', 'woo-force-authentification-before-checkout'); ?>">&times;</button>
                            </div>
                            <div class="wcfa-preview-modal__body"></div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="admin-layout-submit-wrapper">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save changes', 'woo-force-authentification-before-checkout'); ?>
                    </button>
                </div>
            </form>
        </div>
        <aside class="admin-layout-sidebar-bar">
            <div class="admin-layout-sidebar">
                <?php
                $wcfa_assets_url = WC_FORCE_AUTH_URL . 'Includes/assets/';

                $backgrounds_right = $wcfa_assets_url . 'icons/backgroundCardRight.svg';
                $backgrounds_left  = $wcfa_assets_url . 'icons/backgroundCardLeft.svg';
                $logo              = $wcfa_assets_url . 'images/linkNacionalLogo.webp';
                $whatsapp          = $wcfa_assets_url . 'icons/whatsapp.svg';
                $telegram          = $wcfa_assets_url . 'icons/telegram.svg';
                $versions          = sprintf(
                    /* translators: 1: plugin name and version, 2: WooCommerce version. */
                    __( '%1$s | WooCommerce v%2$s', 'woo-force-authentification-before-checkout' ),
                    'Force Authentification ' . WC_FORCE_AUTH_VERSION,
                    function_exists( 'WC' ) && isset( WC()->version ) ? WC()->version : '—'
                );

                include WC_FORCE_AUTH_DIR . 'Includes/templates/WcForceAuthAdminSettingLinkCard.php';
                ?>

                <div class="block-status-card block-status-card--success">
                    <div class="block-status-card-header">
                        <span class="dashicons dashicons-lock"></span>
                        <h4 class="block-status-card-title"><?php esc_html_e( 'NEW: Email OTP Authentication', 'woo-force-authentification-before-checkout' ); ?></h4>
                    </div>
                    <p class="block-status-card-description">
                        <?php esc_html_e( 'Let customers sign in and register with a one-time password sent to their email — no password required.', 'woo-force-authentification-before-checkout' ); ?>
                    </p>
                </div>

                <div class="block-status-card block-status-card--success">
                    <div class="block-status-card-header">
                        <span class="dashicons dashicons-layout"></span>
                        <h4 class="block-status-card-title"><?php esc_html_e( 'NEW: Modern Settings Interface', 'woo-force-authentification-before-checkout' ); ?></h4>
                    </div>
                    <p class="block-status-card-description">
                        <?php esc_html_e( 'A brand-new, clean and organized settings screen, built for a faster and simpler configuration.', 'woo-force-authentification-before-checkout' ); ?>
                    </p>
                </div>

                <div class="block-promotional-card">
                    <div class="block-promotional-card-bg"></div>
                    <div class="block-promotional-card-content">
                        <h3 class="block-promotional-card-title">
                            <?php esc_html_e( 'Plugin: Invoice Payment Link for WooCommerce', 'woo-force-authentification-before-checkout' ); ?>
                        </h3>
                        <p class="block-promotional-card-description">
                            <?php esc_html_e( 'The Invoice Payment Plugin is the complete solution for your business. With it, you can generate payment links, split purchases across multiple cards, set up recurring charges, apply discounts and fees, and create detailed quotes.', 'woo-force-authentification-before-checkout' ); ?>
                        </p>
                        <div class="block-promotional-card-buttons">
                            <a href="<?php echo esc_url( 'https://br.wordpress.org/plugins/invoice-payment-for-woocommerce/' ); ?>" target="_blank" rel="noopener noreferrer" class="block-promotional-card-btn block-promotional-card-btn-learn">
                                <?php esc_html_e( 'Learn more', 'woo-force-authentification-before-checkout' ); ?>
                            </a>
                            <?php if ( empty( $wcfa_invoice_plugin_installed ) ) : ?>
                                <a href="<?php echo esc_url( $wcfa_install_url ); ?>"
                                target="_blank"
                                class="block-promotional-card-btn block-promotional-card-btn-install">
                                    <?php esc_html_e( 'Install', 'woo-force-authentification-before-checkout' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
