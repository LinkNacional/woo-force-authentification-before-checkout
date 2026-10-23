<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This plugin's controls in the editor of a page that holds the
 * "LKN Forms - Login and Register" block.
 *
 * The block is a plain login/register block and owns none of the code flow. This
 * plugin owns all of it: its screens take the place of the block's login form on
 * the front end (see WcForceAuthBlockOtp), its rules and branding are its own
 * settings, and the choice of which page is the store login page is its own
 * option too (see WcForceAuthLoginPage).
 *
 * The block offers two empty places and this plugin fills them: the page's code
 * settings (stored in the block's `otp` attribute, saved with the post) go in
 * `lknform-otp-panel`, and the store login page switch — a single option, saved
 * straight through AJAX — goes in `lknform-store-login-page`, right under the
 * block's "Show" field. Nothing else is added to the editor: no sidebar of this
 * plugin's own, since both places already live in the block's sidebar.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthBlockPanel {

	/**
	 * Editor script handle.
	 */
	public const SCRIPT = 'wc-force-auth-block-panel';

	/**
	 * Name of the object localized with the panel data.
	 */
	public const CONFIG = 'wcForceAuthBlockPanel';

	/**
	 * Block whose editor these controls show up in.
	 */
	public const BLOCK = 'lknform/login-register';

	/**
	 * Slot the block declares for the page's code settings, below its Layout panel.
	 */
	public const SLOT = 'lknform-otp-panel';

	/**
	 * Slot for the store login page switch, right under the block's "Show" field.
	 *
	 * It is a different slot on purpose: this choice is store-wide (one page at a
	 * time), while the code settings above belong to the page. Putting it where the
	 * block's own layout field is says that much.
	 */
	public const LOGIN_PAGE_SLOT = 'lknform-store-login-page';

	/**
	 * Slot right under the block's "Redirect after login" field.
	 *
	 * The block owns the field; suggesting the store's own address is a
	 * WooCommerce matter ("My account" is one of its pages), so the button that
	 * fills it comes from here — the block stays WordPress-only.
	 */
	public const REDIRECT_SLOT = 'lknform-redirect-after';

	/**
	 * OTP settings offered in the block's panel.
	 *
	 * They are this plugin's own options, saved through its action directly — the
	 * block has no say in any of them.
	 */
	public const FIELDS = array(
		'code_length',
		'code_format',
		'code_input',
		'expiration_time',
		'resend_interval',
		// Each switch comes before the field it turns on or off: "Display brand
		// name" over the name, "Display logo" over the logo URL.
		'brand_name_show',
		'brand_name',
		'logo_show',
		'logo_url',
		'email_subject',
	);

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * The OTP settings screen, reused for its field schema and its action.
	 *
	 * @var WcForceAuthSettings
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param string              $plugin_name Plugin name.
	 * @param string              $version     Plugin version.
	 * @param WcForceAuthSettings $settings    OTP settings screen.
	 */
	public function __construct( $plugin_name, $version, WcForceAuthSettings $settings ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->settings    = $settings;
	}

	/**
	 * Whether the login block these controls belong to is around.
	 *
	 * Without that plugin there is no block, so there is nowhere these controls
	 * would make sense. Detection goes through the block registry on purpose: the
	 * block is the contract between the two plugins, and its name does not change
	 * with that plugin's internal class names.
	 *
	 * @return bool
	 */
	public function block_is_active(): bool {
		return \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK );
	}

	/**
	 * Enqueue the editor script (it fills the block's slots).
	 *
	 * Hook: enqueue_block_editor_assets.
	 */
	public function enqueue(): void {
		if ( ! $this->block_is_active() ) {
			return;
		}

		// The Logo URL field picks the image from the media library, so the
		// editor needs the media frame (and its styles).
		wp_enqueue_media();

		wp_enqueue_style(
			self::SCRIPT,
			WC_FORCE_AUTH_URL . 'Admin/css/wc-force-auth-block-panel.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			self::SCRIPT,
			WC_FORCE_AUTH_URL . 'Admin/js/wc-force-auth-block-panel.js',
			// No `wp-edit-post` on purpose: this script only fills slots the block
			// declares (the earlier plugin-sidebar API is gone).
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'media' ),
			$this->version,
			true
		);

		wp_localize_script( self::SCRIPT, self::CONFIG, $this->config() );
	}

	/**
	 * The store's "My account" address, when there is one to suggest.
	 *
	 * Only a published page: filling the field with the address of a draft would
	 * send customers to a 404 after signing in. Empty string when WooCommerce is
	 * not around or the page does not exist.
	 *
	 * @return string
	 */
	protected function account_url(): string {
		$page_id = WcForceAuthLoginPage::account_page_id();

		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		$url = function_exists( 'wc_get_page_permalink' )
			? (string) wc_get_page_permalink( 'myaccount' )
			: (string) get_permalink( $page_id );

		return '' === $url ? '' : $url;
	}

	/**
	 * Everything the editor script needs to render itself.
	 *
	 * @return array<string,mixed>
	 */
	protected function config(): array {
		$login_page_id = WcForceAuthLoginPage::page_id();

		return array(
			// Where the controls are rendered: slots the block declares in its own
			// sidebar, each next to the block setting it belongs with (see the
			// block's `edit.js`).
			'slot'       => self::SLOT,
			'loginPageSlot' => self::LOGIN_PAGE_SLOT,
			'redirectSlot' => self::REDIRECT_SLOT,
			// The block whose attribute the redirect button writes to.
			'blockName'  => self::BLOCK,
			// The store's account address, offered under the block's "Redirect
			// after login" field. Empty when WooCommerce (or its page) is gone, and
			// then the button is not shown at all.
			'accountUrl' => $this->account_url(),
			'fields'     => $this->fields(),
			// Starting point a block copies into itself when the feature is turned
			// on. Only a snapshot: the page owns its copy from then on.
			'pageDefaults' => $this->page_defaults(),
			'canSave'    => current_user_can( 'manage_woocommerce' ),
			// Whether the code flow is on right now (it decides whether the block's
			// login form is being replaced on the front end).
			'mode'        => WcForceAuthOtp::instance()->get_mode(),
			// The store login page (see WcForceAuthLoginPage): which page holds the
			// role now, and where the switch saves it. A single option means a
			// single page: turning it on here turns it off everywhere else.
			'loginPage'  => array(
				'pageId'      => $login_page_id,
				'pageTitle'   => $login_page_id > 0 ? get_the_title( $login_page_id ) : '',
				'pageSlug'    => $login_page_id > 0 ? (string) get_post_field( 'post_name', $login_page_id ) : '',
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'action'      => WcForceAuthLoginPage::AJAX_ACTION,
				'nonce'       => wp_create_nonce( WcForceAuthLoginPage::AJAX_ACTION ),
				'woocommerce' => class_exists( 'WooCommerce' ),
			),
			'i18n'       => array(
				'panelTitle'    => __( 'Email code (OTP)', 'woo-force-authentification-before-checkout' ),
				'saving'        => __( 'Saving…', 'woo-force-authentification-before-checkout' ),
				'saved'         => __( 'Saved', 'woo-force-authentification-before-checkout' ),
				'error'         => __( 'Could not save the settings.', 'woo-force-authentification-before-checkout' ),
				'readOnly'      => __( 'Only the store manager can change these settings.', 'woo-force-authentification-before-checkout' ),
				'codeGroup'     => __( 'Code', 'woo-force-authentification-before-checkout' ),
				'logoLabel'     => __( 'Logo URL', 'woo-force-authentification-before-checkout' ),
				'chooseLogo'    => __( 'Choose from the media library', 'woo-force-authentification-before-checkout' ),
				'logoTitle'     => __( 'Select the logo', 'woo-force-authentification-before-checkout' ),
				'useLogo'       => __( 'Use this image', 'woo-force-authentification-before-checkout' ),
				'removeLogo'    => __( 'Remove', 'woo-force-authentification-before-checkout' ),
				'emailGroup'    => __( 'E-mail and branding', 'woo-force-authentification-before-checkout' ),
				'loginPageGroup'      => __( 'Store login page', 'woo-force-authentification-before-checkout' ),
				'loginPageLabel'      => __( 'Send guests from "My account" to this page', 'woo-force-authentification-before-checkout' ),
				'loginPageHelp'       => __( 'A visitor who opens "My account" while logged out is redirected here, so this page\'s block is the store\'s login and registration screen. Customers who are already logged in stay on "My account", with its sections and endpoints. One page at a time: turning this on turns it off on any other page. After signing in, the destination set in Authentification → Redirect applies.', 'woo-force-authentification-before-checkout' ),
				'loginPageUnpublished' => __( 'Publish this page first: an unpublished page cannot receive the guests of "My account".', 'woo-force-authentification-before-checkout' ),
				/* translators: %s: page title. */
				'loginPageOther'      => __( 'Guests are currently sent to "%s". Turning this on sends them here instead.', 'woo-force-authentification-before-checkout' ),
				'loginPageOtherGeneric' => __( 'Guests are currently sent to another page. Turning this on sends them here instead.', 'woo-force-authentification-before-checkout' ),
				'loginPageNeedsWoo'   => __( 'Sending guests here needs WooCommerce: it is the "My account" page that hands them over, while you are logged out.', 'woo-force-authentification-before-checkout' ),
				'loginPageNoPermission' => __( 'Sending the guests of "My account" to this page requires a store manager.', 'woo-force-authentification-before-checkout' ),
				'loginPageNoPermissionOn' => __( 'Guests of "My account" are sent to this page. Changing that requires a store manager.', 'woo-force-authentification-before-checkout' ),
				/*
				 * Short lines for the same warnings: they are what the sidebar shows, and the
				 * texts above are the detail behind their "?" (see HelpTip). The panel is a
				 * column in the block's sidebar, where a paragraph hides the fields.
				 */
				'loginPageUnpublishedShort' => __( 'Publish this page first.', 'woo-force-authentification-before-checkout' ),
				/* translators: %s: page title. */
				'loginPageOtherShort' => __( 'Guests are sent to "%s" today.', 'woo-force-authentification-before-checkout' ),
				'loginPageOtherGenericShort' => __( 'Guests are sent to another page today.', 'woo-force-authentification-before-checkout' ),
				'loginPageNeedsWooShort' => __( 'Requires WooCommerce.', 'woo-force-authentification-before-checkout' ),
				'loginPageNoPermissionShort' => __( 'Requires a store manager.', 'woo-force-authentification-before-checkout' ),
				/* translators: %s: name of the setting the "?" belongs to. */
				'moreAbout'           => __( 'More about %s', 'woo-force-authentification-before-checkout' ),
				'redirectFill'        => __( "Use the 'My account' page", 'woo-force-authentification-before-checkout' ),
				'redirectFillFilled'  => __( 'The field already points to "My account".', 'woo-force-authentification-before-checkout' ),
				'redirectFillIgnored' => __( 'Turn off "Return to the previous page" above to use this address.', 'woo-force-authentification-before-checkout' ),
			),
		);
	}

	/**
	 * The field schema offered in the panel, taken from the settings screen.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function fields(): array {
		$all    = $this->settings->get_fields();
		$prefix = WcForceAuthOtp::OPTION_PREFIX;
		$fields = array();

		foreach ( self::FIELDS as $key ) {
			$name = $prefix . $key;

			if ( ! isset( $all[ $name ] ) || ! is_array( $all[ $name ] ) ) {
				continue;
			}

			$field = $all[ $name ];

			$fields[ $key ] = array(
				'label'       => isset( $field['block_title'] ) ? $field['block_title'] : ( isset( $field['title'] ) ? $field['title'] : $key ),
				'type'        => isset( $field['type'] ) ? $field['type'] : 'text',
				'options'     => isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array(),
				'description' => isset( $field['block_sub_title'] ) ? $field['block_sub_title'] : ( isset( $field['description'] ) ? $field['description'] : '' ),
				'group'       => 0 === strpos( $key, 'email_' ) || in_array( $key, array( 'brand_name', 'brand_name_show', 'logo_url', 'logo_show' ), true ) ? 'email' : 'code',
			);

		}

		return $fields;
	}

	/**
	 * Store-wide value of each offered field.
	 *
	 * Only used as the starting point of a page's own copy (and as the value a
	 * control shows before the page has one). Editing these here is not the way to
	 * change the store defaults — that is the OTP screen of this plugin.
	 *
	 * @return array<string,string>
	 */
	protected function page_defaults(): array {
		$values = array();

		foreach ( array_keys( $this->fields() ) as $key ) {
			$values[ $key ] = (string) get_option( WcForceAuthOtp::OPTION_PREFIX . $key, WcForceAuthOtp::default_for( $key ) );
		}

		return $values;
	}
}
