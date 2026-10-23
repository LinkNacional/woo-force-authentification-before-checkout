<?php

namespace Lkn\WcForceAuth\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends guests from WooCommerce's "My account" page to a page of the
 * "LKN Forms - Login and Register" block, which serves as the store's login
 * screen.
 *
 * WooCommerce has no login page of its own: the account page shows the login and
 * registration forms to visitors who are logged out, and the account area to
 * everyone else. Picking a block page here replaces *that form*: a guest asking
 * for "My account" is redirected to the chosen page, which draws the login and
 * registration screens with the block (and with this plugin's code screens, when
 * that page asks for them). Everything else stays where it is — the account page
 * keeps its URL, its endpoints and its sections, and customers who are already
 * signed in never leave it.
 *
 * The feature lives here, not in the block: the block is a plain login form and
 * knows nothing about WooCommerce, while handing the store's login screen over to
 * a page is an authentication decision — this plugin owns the login flows, so it
 * owns this choice too. The block only offers a slot in its sidebar for the
 * switch, which this plugin fills.
 *
 * There is a single site-wide choice: one page at a time. Picking another page
 * (or "none") replaces the previous one, so the store never has two login
 * screens. While a page holds the role this plugin's own OTP stays off (see
 * WcForceAuthOtp::get_mode()): one screen at a time.
 *
 * @package Lkn\WcForceAuth
 */
class WcForceAuthLoginPage {

	/**
	 * Option holding the page whose block serves the login screen (0 = none).
	 *
	 * A single option means a single login page: saving another value replaces
	 * the previous one automatically.
	 */
	public const OPTION_PAGE_ID = 'wc_force_auth_login_page_id';

	/**
	 * Where an older version of the block plugin kept the same choice. Read once
	 * (see install()) so sites that had it keep their login page.
	 */
	public const LEGACY_OPTION_PAGE_ID = 'lknform_wc_login_page_id';

	/**
	 * Where that older version remembered this plugin's authentication mode while
	 * it was switching the OTP off by hand. See install().
	 */
	public const LEGACY_OTP_BACKUP_OPTION = 'lknform_woo_force_otp_backup';

	/**
	 * Marks the one-time migration as done.
	 */
	public const OPTION_INSTALLED = 'wc_force_auth_login_page_installed';

	/**
	 * AJAX action that saves the choice from the block editor.
	 */
	public const AJAX_ACTION = 'wc_force_auth_save_login_page';

	/**
	 * The block whose page can serve the login screen.
	 */
	public const BLOCK_NAME = 'lknform/login-register';

	/**
	 * Per-request cache for the "this page owns the login screen" check.
	 *
	 * Keyed by page revision: editing the page (or picking another one) is picked
	 * up immediately.
	 *
	 * @var array<string,bool>
	 */
	private static $owns_login_cache = array();

	/**
	 * Register the redirect and the editor's save action.
	 */
	public function register(): void {
		$this->install();

		// Priority 5: before WooCommerce and this plugin's own
		// `template_redirect` callbacks, so a guest asking for "My account" is
		// sent on before anything is rendered (or remembered) there.
		add_action( 'template_redirect', array( $this, 'redirect_guests' ), 5 );

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_save_page' ) );
	}

	/**
	 * One-time migration from the block plugin's own copy of this feature.
	 *
	 * Runs once per site; nothing is written on later requests.
	 */
	private function install(): void {
		if ( get_option( self::OPTION_INSTALLED ) ) {
			return;
		}

		$page_id = absint( get_option( self::OPTION_PAGE_ID, 0 ) );
		$legacy  = absint( get_option( self::LEGACY_OPTION_PAGE_ID, 0 ) );

		// A site that already had a login page keeps it.
		if ( $page_id < 1 && $legacy > 0 ) {
			update_option( self::OPTION_PAGE_ID, $legacy, false );
		}

		delete_option( self::LEGACY_OPTION_PAGE_ID );

		$this->restore_legacy_otp_mode();

		update_option( self::OPTION_INSTALLED, 1, false );
	}

	/**
	 * Give the store its authentication mode back after an older block plugin
	 * switched the OTP off by hand (and stored the previous mode here).
	 */
	private function restore_legacy_otp_mode(): void {
		$backup = (string) get_option( self::LEGACY_OTP_BACKUP_OPTION, '' );

		if ( '' === $backup ) {
			return;
		}

		// Only restore while the OTP is still off: a mode chosen by hand in the
		// meantime wins.
		if ( 'disabled' === WcForceAuthOtp::stored_mode() ) {
			update_option( WcForceAuthOtp::OPTION_PREFIX . 'enable_type', $backup, false );
		}

		delete_option( self::LEGACY_OTP_BACKUP_OPTION );
	}

	/**
	 * Whether a page was chosen as the store login page (regardless of whether
	 * that page still holds the block).
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return self::page_id() > 0;
	}

	/**
	 * The page hosting the block.
	 *
	 * @return int
	 */
	public static function page_id(): int {
		return absint( get_option( self::OPTION_PAGE_ID, 0 ) );
	}

	/**
	 * Whether the block really owns the login screen right now: a published page
	 * is chosen and it still contains the block.
	 *
	 * A page moved to the trash (or back to draft) stops owning the login screen,
	 * so WooCommerce gets its own form back instead of a half-configured page.
	 *
	 * @return bool
	 */
	public static function owns_login(): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		// Pages that already send guests somewhere -- or are where they are going --
		// cannot receive them: that would loop (checkout -> login page -> checkout).
		// Older versions did not reject them, so the check lives here as well as in
		// ajax_save_page().
		if ( in_array( self::page_id(), self::conflicting_page_ids(), true ) ) {
			return false;
		}

		// Without the block there is no form on that page: a page that merely
		// contains the block name (the plugin was deactivated) would take the role
		// and leave guests with an empty login screen.
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			return false;
		}

		$page = self::page();

		if ( ! $page instanceof \WP_Post ) {
			return false;
		}

		$cache_key = $page->ID . ':' . $page->post_modified_gmt;

		if ( ! isset( self::$owns_login_cache[ $cache_key ] ) ) {
			self::$owns_login_cache[ $cache_key ] = null !== self::search_blocks( parse_blocks( (string) $page->post_content ) );
		}

		return self::$owns_login_cache[ $cache_key ];
	}

	/**
	 * Whether the chosen page exists but cannot serve as the login page (draft,
	 * private, trashed, or no longer holding the block).
	 *
	 * @return bool
	 */
	public static function is_page_unusable(): bool {
		return self::is_enabled() && ! self::owns_login();
	}

	/**
	 * The chosen page, when it can really serve as the login page.
	 *
	 * @return \WP_Post|null
	 */
	private static function page() {
		$page = self::page_id() > 0 ? get_post( self::page_id() ) : null;

		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return null;
		}

		return $page;
	}

	/**
	 * Send guests from the account page to the chosen login page.
	 *
	 * WooCommerce shows the login form inside "My account" for visitors who are
	 * logged out; with a page chosen, that form lives there instead, so the guest
	 * is sent to it. Endpoints (`/my-account/orders/` and friends) are covered
	 * too: they all need an authenticated customer.
	 *
	 * Hook: template_redirect.
	 *
	 * @return void
	 */
	public function redirect_guests(): void {
		if ( is_user_logged_in() || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Cheap checks first: `owns_login()` parses the page, and this runs on
		// every front-end request.
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		// The page itself never redirects to itself (a login page that also holds
		// [woocommerce_my_account] would otherwise loop forever).
		if ( is_page( self::page_id() ) || ! self::owns_login() ) {
			return;
		}

		// The password reset keeps its own screen: WooCommerce's e-mail link lands
		// on "lost-password?key=...&login=...", and the block does not read those
		// arguments — redirecting would drop the key and lock the customer out.
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' ) ) {
			return;
		}

		$url = (string) get_permalink( self::page_id() );

		if ( '' === $url ) {
			return;
		}

		// Carry the "back to the checkout" marker along, when the request has it
		// (the forced-authentication redirect attaches it).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only marker.
		if ( isset( $_GET[ WcForceAuthCheckout::URL_ARG ] ) ) {
			$url = add_query_arg( WcForceAuthCheckout::URL_ARG, '', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Whether the current request is the chosen login page.
	 *
	 * The other side of the same coin as redirect_guests(): what used to be
	 * captured on "My account" (the page the customer came from) is now captured
	 * here, since that is where the form is.
	 *
	 * @return bool
	 */
	public static function is_login_screen(): bool {
		// `is_page()` first: it is a query comparison, while `owns_login()` parses
		// the chosen page, and this is asked on requests that are not it.
		return is_page( self::page_id() ) && self::owns_login();
	}

	/**
	 * WooCommerce's account page id (0 when the page is not set).
	 *
	 * @return int
	 */
	public static function account_page_id(): int {
		return function_exists( 'wc_get_page_id' ) ? absint( wc_get_page_id( 'myaccount' ) ) : 0;
	}

	/**
	 * Pages that cannot take the role: the account page (guests are sent from it)
	 * and the checkout/cart pages (guests on them are sent *to* the login page, so
	 * pointing them at themselves would spin).
	 *
	 * @return int[]
	 */
	public static function conflicting_page_ids(): array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return array();
		}

		$ids = array(
			self::account_page_id(),
			absint( wc_get_page_id( 'checkout' ) ),
			absint( wc_get_page_id( 'cart' ) ),
		);

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Find the block in a parsed block list (nested blocks included).
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array|null The parsed block, or null.
	 */
	private static function search_blocks( array $blocks ) {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( self::BLOCK_NAME === ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) ) {
				return $block;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = self::search_blocks( $block['innerBlocks'] );

				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Save the choice made in the block editor.
	 *
	 * Hook: wp_ajax_wc_force_auth_save_login_page.
	 */
	public function ajax_save_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-force-authentification-before-checkout' ) ) );
		}

		check_ajax_referer( self::AJAX_ACTION );

		$page_id = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;

		if ( 0 === $page_id ) {
			update_option( self::OPTION_PAGE_ID, 0, false );

			wp_send_json_success(
				array(
					'message'   => __( 'Guests are back to the WooCommerce login form on "My account".', 'woo-force-authentification-before-checkout' ),
					'pageId'    => 0,
					'pageTitle' => '',
					'pageSlug'  => '',
				)
			);
		}

		$page = get_post( $page_id );

		// Only a published page holding the block can serve the login screen:
		// anything else would leave the store without a way to sign in.
		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Only a published page can be the store login page.', 'woo-force-authentification-before-checkout' ) ) );
		}

		// "My account" itself cannot take the role (guests are sent from it), and
		// neither can the checkout/cart (guests there are sent to the login page):
		// any of those combinations spins.
		if ( in_array( $page->ID, self::conflicting_page_ids(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'That is a WooCommerce page that already sends guests to the login page (My account, checkout or cart), so it cannot be the login page itself.', 'woo-force-authentification-before-checkout' ) ) );
		}

		if ( null === self::search_blocks( parse_blocks( (string) $page->post_content ) ) ) {
			wp_send_json_error( array( 'message' => __( 'That page has no login block: the login screen would be empty.', 'woo-force-authentification-before-checkout' ) ) );
		}

		update_option( self::OPTION_PAGE_ID, $page->ID, false );

		wp_send_json_success(
			array(
				'message'   => __( 'Guests who open "My account" are now sent to this page.', 'woo-force-authentification-before-checkout' ),
				'pageId'    => $page->ID,
				'pageTitle' => get_the_title( $page->ID ),
				// The slug travels back too: the choice is a single site-wide page, and
				// the slug is what makes it unambiguous among pages with similar names.
				'pageSlug'  => (string) $page->post_name,
			)
		);
	}
}
