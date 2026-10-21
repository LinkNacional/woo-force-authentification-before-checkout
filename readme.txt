=== Force Authentification Before Checkout for WooCommerce ===
Contributors: linknacional
Donate link: https://linknacional.com.br/
Tags: woocommerce, checkout, login, register, cart
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Force customer to log in or register before checkout, with optional email OTP authentication.

== Description ==

Force customer to log in or register before checkout to increase your conversion rate. Guests who reach the [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) checkout are redirected to the account page and sent back after authenticating.

Optionally, replace the login and registration forms on the "My account" page with a passwordless email OTP (one-time password) flow: customers enter their email, receive a code and are logged in (a new account is created automatically in the "Login and register" mode).

**Features**

* Forces authentication before checkout (redirects guests to the login page: the account page, your own login page with the block, or a custom URL).
* Email OTP authentication with three modes: Disabled, Login only + Register and Login and register.
* The OTP login can also be used by the "Login and Register Form" block of the free LKN Forms plugin: that block draws its own e-mail and code screens (the page's texts, colors and styles) and only calls the endpoints of this plugin, so the visitor keeps the site's design — with the code rules configured per page, in the block sidebar.
* A page with that block can be the store's login page: guests who open "My account" are sent to it, and the block there is the login and registration screen. Customers who are already signed in stay on "My account", with its sections and endpoints. The switch sits in the block sidebar, right under its "Show" field, and only one page can hold the role.
* Configurable code length (4, 6 or 8 digits), expiration time and resend interval (with countdown timer).
* Code input as a single field or one box per digit (PIN style), with visual grouping: plain, groups with space, groups with dash or pairs.
* Automatic clipboard detection: when the code is copied, a floating prompt offers to use it and fills the field.
* "Didn't receive the code?" report: opens a popup (email, contact and editable message) that emails every [WordPress](https://www.linknacional.com.br/wordpress/) administrator.
* Branded login and verification screens using your store identity (logo, primary color, headings and legal text are configurable).
* Notification style per store: floating pop-ups (SweetAlert2) or inline messages shown inside the component.
* Codes stored hashed, with attempt limiting and resend throttling.
* Force Authentication settings to enable/disable the requirement, set custom login and checkout pages, and choose the destination after login.

Plugin developed and maintained by [Link Nacional](https://www.linknacional.com/).

= Contributions =

- For bugs, suggestions or contributions open a issue in our [Github Repository](https://github.com/LinkNacional/woo-force-authentification-before-checkout/issues) or create a topic in [WordPress Plugin Forum](https://wordpress.org/support/plugin/woo-force-authentification-before-checkout).

= Donations =

Support this plugin on [https://linknacional.com.br/](https://linknacional.com.br/)

== Installation ==

1. Make sure [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) is installed and active.
2. In the [WordPress](https://www.linknacional.com.br/wordpress/) admin, go to Plugins > Add New, search for the plugin and install it (or upload the plugin ZIP via Plugins > Add New > Upload Plugin).
3. Activate the plugin through the Plugins screen.
4. Open the "Force Authentification" menu in the [WordPress](https://www.linknacional.com.br/wordpress/) sidebar. The "Force Authentification" screen controls the login-before-checkout requirement, and the "OTP" submenu configures the email one-time password.

== Frequently Asked Questions ==

= Works with social login plugins? =

Yes.

= Can I change the message in "my account" page? =

Yes. With this [code](https://gist.github.com/luizbills/25d2c83848de1fb23beceb0e407226ef).

= Which code formats are supported? =

The code can be entered as a single field or one box per digit (PIN style). The visual grouping can be plain (123456), groups with space (123 456), groups with dash (123-456) or pairs (12 34 56), and the length is 4, 6 or 8 digits. The separator is visual only and is removed before the code is sent.

= Does the OTP replace the default login form? =

Yes. When OTP is enabled, the plugin serves its own account template, so the email OTP screens replace the default [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) login and registration forms.

== Screenshots ==

1. Notice in "my account" page.

== Changelog ==

= 1.7.0 - 2026-10-19 =

* New: a "Use the 'My account' page" button under the login block's **Redirect after login** field fills it with the store's account address, so nobody has to type (and mistype) that URL. It is disabled when the field already holds it or when "Return to the previous page" is on, with the reason below it. The block declares only the place; "My account" is a WooCommerce page, so the address comes from this plugin.
* New: the "Login and Register Form" block of the LKN Forms plugin can drive the code flow with its own markup — the e-mail and code screens, the countdown, the resend and the "did not receive the code" link are drawn by the block, so the visitor keeps that site's design instead of this plugin's card
* New public `get_block_config()` (endpoints, nonce, code length, expiry and resend interval) and the `WcForceAuthOtp::NONCE_ACTION` constant, so any integration can call the flow
* A code requested by the block is always 4 digits and its e-mail drops the logo, keeping only the site name: the block has no branding settings, so its e-mail stays simple
* No markup is injected into other plugins' blocks: this plugin keeps rendering its own card only on the WooCommerce account page
* New filter `wc_force_auth_otp_needs_assets` (load the OTP assets outside the My account page)
* New filter `wc_force_auth_after_login_url` (post-login destination for integrations that render the OTP on another page)
* One login screen at a time: when a page of the LKN Forms block is the store login page, the OTP is off — the authentication mode reads and saves as "Disabled" and its field is locked, with a notice pointing to the LKN Forms settings

= 1.6.0 - 2026/10/2 =

* New class-based architecture (PSR-4, `Lkn\WcForceAuth`) with `Admin/`, `Public/` and `Includes/` separation
* Dependency-free test layer (`tests/`): a custom runner that boots the real Local [WordPress](https://www.linknacional.com.br/wordpress/), with real email captured by Mailpit; covers the code formats (4/6/8, `plain`/`space`/`dash`/`pair`, single and segmented input), the REST flow (send/verify/limits) and Force Authentication
* Fix: OTP options no longer fall back to the Invoice Payment plugin's options (`lkn_wcip_otp_email_*`), which leaked values such as the expiration time between plugins
* New [WordPress](https://www.linknacional.com.br/wordpress/) admin sidebar menu ("Force Authentification" → "OTP")
* Admin menu restructure: the top-level "Force Authentification" item opens the main feature settings; hovering reveals the "Force Authentification" and "OTP" submenus
* New settings screen for the main feature (Force Authentication): enable/disable, notice message, login/checkout URLs and post-login destination; these options now drive the behavior (previously hardcoded)
* Settings architecture with a shared base class (`WcForceAuthSettingsPage`) and one page per feature
* Top spacing fixed on the settings screens (our own pages lack the [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) tab nav that provided this spacing in the fraud plugin)
* "Invoice Payment Link for [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/)" card: installation detection aligned with the antifraud plugin (checks only the [WordPress.org](https://www.linknacional.com.br/wordpress/) slug folder, `invoice-payment-for-woocommerce/wc-invoice-payment.php`)
* New feature: email OTP authentication (login and/or passwordless registration)
* Login and verification screens with the new layout, using the store identity (logo, primary color and texts are configurable)
* REST code sending/verification, hashed code storage, attempt limiting and resend throttling
* Dedicated OTP email template
* Settings saved over AJAX with SweetAlert2
* Frontend notifications (sending, verification, errors) with SweetAlert2, plus automatic clipboard detection (offers to use the code when the customer returns with it copied)
* Consistent input/button heights; the login/register button and the field icon highlight in the brand color once a valid email is entered; email and lock icons use inline SVG
* Removed the browser autofill background on the fields (email, code and register)
* Verify button shares the login button state/animation; the timer and "Resend" button are overlaid (no longer push the code input) and "Resend" uses the brand color
* Fixed the close (×) alignment and made the "Use code" button use the brand color in the detected-code notice
* Larger fonts, wider card and fixed-height controls with explicit `box-sizing` (inputs and buttons share the same height; the theme can no longer blow up the input)
* "Use another email" button with standardized height and font; gray field placeholders; the detected-code toast stays visible longer
* Timer and "Resend" below the "Verify" button: the countdown shows inside the button in a quiet gray style, then it takes the light brand tint (secondary look); the code is centered with the icon on the left; the "Use another email" button uses a light brand tint
* Legal text only on the login step; the verification step has a "Didn't receive the code?" link that opens a SweetAlert2 popup (email, contact, prefilled editable message) and emails a report to every [WordPress](https://www.linknacional.com.br/wordpress/) administrator
* Code expiration time shown below the field (formatted with the [WordPress](https://www.linknacional.com.br/wordpress/) date/time) and a loading spinner on the action buttons
* New "Code length" select (4, 6 or 8 digits)
* New "Code input style" option: single field or one box per digit (PIN style, with auto-advance/backspace/paste and visual grouping per the "Code format")
* Fix: the segmented boxes are no longer stretched by the theme (fixed width/height with higher specificity); also fixed the default value shown in the selects (a strict comparison made the select show the first option instead of the saved value)
* Critical fix: the expired-code cleanup was deleting plugin settings (the `LIKE` matched `code_length`/`code_format`/`code_input`), making options revert to default after every login and the email generate a default-length code — transient codes now use an isolated `auth_` prefix that cannot collide with any option
* New "Code format" option for the code field: plain (123456), groups with space (123 456), groups with dash (123-456) or pairs (12 34 56) — the separator is visual only and is stripped before sending; the "code detected" clipboard check accepts both the raw and the formatted value
* New "Notification style" option (floating or inline): chooses whether feedback (code sent, errors, confirmations) appears as floating pop-ups (SweetAlert2) or inside the component; in inline mode each message shows right below the active step's input; the "code detected" alert always stays floating

= 1.5.0 - 2026/8/17 =

* Added plugin banners
* Improved the "my account" page option notification
* Updated documentation links to Link Nacional
* Minor [WordPress.org](https://www.linknacional.com.br/wordpress/) compliance adjustments

= 1.4.6 =

* Tested up to [WordPress](https://www.linknacional.com.br/wordpress/) 6.9 and [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) 10.6

= 1.4.5 =

* Tested up to [WordPress](https://www.linknacional.com.br/wordpress/) 6.6

= 1.4.4 =

* Tested up to [WordPress](https://www.linknacional.com.br/wordpress/) 6.4

= 1.4.3 =

* Fix donation notice

= 1.4.2 =

* Bump Tested to up

= 1.4.1 =

* Fix call to undefined method

= 1.4.0 =

* Fix: redirect not working with custom login page
* Tweak: Now uses a cookie to dismiss the donation notice in admin panel, instead of the database

= 1.3.2 =

* Fix an syntax error with older versions of PHP

= 1.3.1 - 2020/4/19 =

- Small fix.

= 1.3.0 - 2020/4/19 =

- New filter: wc_force_auth_redirect_to_account_page
- New filter: wc_force_auth_login_page_url
- New filter: wc_force_auth_checkout_page_url

= 1.2.3 - 2018/10/28 =

- Minor fix

= 1.2.2 - 2018/09/17 =

- Minor fix

= 1.2.1 - 2018/07/16 =

- First public release.

== Upgrade Notice ==

= 1.6.0 =

Major update: adds email OTP authentication (login and passwordless registration), a dedicated admin menu with Force Authentication and OTP screens, configurable code formats and inputs, and several fixes. Review your settings after updating.

= 1.2.1 - 2018/07/16 =

- First public release.
