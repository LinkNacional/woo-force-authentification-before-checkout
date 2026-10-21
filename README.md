# Force Authentification Before Checkout for WooCommerce

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/woo-force-authentification-before-checkout?label=Plugin%20Version&logo=wordpress&style=flat-square)](https://wordpress.org/plugins/woo-force-authentification-before-checkout/)
[![WordPress Plugin Required PHP Version](https://img.shields.io/wordpress/plugin/required-php/woo-force-authentification-before-checkout?label=PHP%20Required&logo=php&logoColor=white&style=flat-square)](https://wordpress.org/plugins/woo-force-authentification-before-checkout/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/stars/woo-force-authentification-before-checkout?label=Plugin%20Rating&logo=wordpress&style=flat-square)](https://wordpress.org/support/plugin/woo-force-authentification-before-checkout/reviews/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/woo-force-authentification-before-checkout.svg?label=Downloads&logo=wordpress&style=flat-square)](https://wordpress.org/plugins/woo-force-authentification-before-checkout/advanced/)
[![License](https://img.shields.io/badge/LICENSE-GPLv3-blue?style=flat-square)](https://wordpress.org/plugins/woo-force-authentification-before-checkout/)

[WordPress.org](https://wordpress.org/plugins/woo-force-authentification-before-checkout/) · [Link Nacional](https://www.linknacional.com/)

## Description

Force customer to log in or register before checkout to increase your conversion rate.

When a guest tries to access the checkout, the plugin redirects them to the "My account" page to log in or create an account. After logging in or registering, they are automatically sent back to the checkout to complete the purchase.

Optionally, replace the login and registration forms on the "My account" page with a passwordless email OTP (one-time password) flow: customers enter their email, receive a code and are logged in (a new account is created automatically in the "Login and register" mode).

## Features

- Redirects guests from the checkout to the "My account" page
- Returns the customer to the checkout automatically after login or registration
- Shows a notice on the "My account" page asking the customer to log in or register
- Email OTP authentication with three modes: Disabled, Login only + Register and Login and register
- Configurable code length (4, 6 or 8 digits), expiration time and resend interval (with countdown timer)
- Code input as a single field or one box per digit (PIN style), with visual grouping: plain, groups with space, groups with dash or pairs
- Automatic clipboard detection: when the code is copied, a floating prompt offers to use it and fills the field
- "Didn't receive the code?" report popup that emails every administrator
- Branded login and verification screens using your store identity (logo, primary color, headings and legal text are configurable)
- Notification style per store: floating pop-ups (SweetAlert2) or inline messages shown inside the component
- Codes stored hashed, with attempt limiting and resend throttling
- Settings to enable/disable the requirement, set custom login and checkout pages, and choose the destination after login
- Works with social login plugins
- Shows an admin notice when account registration is disabled
- Extensible via filters:

| Filter | Description |
| --- | --- |
| `wc_force_auth_redirect_to_account_page` | Controls the redirect condition (default: checkout + not logged in) |
| `wc_force_auth_login_page_url` | Customize the login page URL |
| `wc_force_auth_checkout_page_url` | Customize the checkout page URL |
| `wc_force_auth_message` | Customize the notice message |

## Requirements

- [WordPress](https://www.linknacional.com.br/wordpress/) 6.0+
- PHP 8.2+
- [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) (installed and activated)

## Installation

1. Download the plugin ZIP.
2. In the [WordPress](https://www.linknacional.com.br/wordpress/) admin, go to **Plugins → Add New**.
3. Click "Upload Plugin" and select the downloaded ZIP file.
4. Click "Install Now", then "Activate Plugin".
5. Make sure [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) is also activated.

## Usage

1. Go to **[WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) → Settings → Accounts & Privacy**.
2. Under "Account creation", enable **"On the 'My account' page"** so customers can create an account.
3. Open the **"Authentification"** menu in the [WordPress](https://www.linknacional.com.br/wordpress/) sidebar:
   - **Force** — controls the login-before-checkout requirement and the notice message.
   - **Settings** — the shared redirect flow: login/checkout pages and the destination after login (used by both the force-authentication and the OTP flows).
   - **OTP** — configures the email one-time password (mode, code, appearance and email texts).

That's it. Guests will now be redirected to "My account" before checkout.

## Frequently Asked Questions

### Works with social login plugins?

Yes.

### Can I change the message in "my account" page?

Yes. With this [code](https://gist.github.com/luizbills/25d2c83848de1fb23beceb0e407226ef).

### Does the OTP replace the default login form?

Yes. When OTP is enabled, the plugin serves its own account template, so the email OTP screens replace the default [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) login and registration forms.

## Contributions

- For bugs, suggestions or contributions, open an issue in our [Github Repository](https://github.com/LinkNacional/woo-force-authentification-before-checkout/issues) or create a topic in the [WordPress Plugin Forum](https://wordpress.org/support/plugin/woo-force-authentification-before-checkout).

## Support

Support this plugin on [Link Nacional](https://www.linknacional.com/).

## Changelog

See [readme.txt](/readme.txt)
