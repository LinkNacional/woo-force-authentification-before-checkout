=== Force Authentification Before Checkout for WooCommerce ===
Contributors: luizbills
Donate link: https://luizpb.com/donate/
Tags: woocommerce, checkout, login, register, cart
Requires at least: 4.8
Tested up to: 6.6
Requires PHP: 7.3
Stable tag: 1.4.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Force customer to log in or register before checkout

== Description ==

Force customer to log in or register before checkout to increase your conversion rate.

= Contribuitions =

- For bugs, suggestions or contribuitions open a issue in our [Github Repository](https://github.com/luizbills/woo-force-authentification-before-checkout/issues) or create a topic in [WordPress Plugin Forum](https://wordpress.org/support/plugin/woo-force-authentification-before-checkout).

= Donations =

Support this plugin on [https://luizpb.com/donate/](https://luizpb.com/donate/)

== Frequently Asked Questions ==

= Works with social login plugins? =

Yes.

= Can I change the message in "my account" page? =

Yes. With this [code](https://gist.github.com/luizbills/25d2c83848de1fb23beceb0e407226ef).

== Screenshots ==

1. Notice in "my account" page.

== Changelog ==

= 1.4.6 =

* Tested up to WordPress 6.9 and WooCommerce 10.6

= 1.4.5 =

* Tested up to WordPress 6.6

= 1.4.4 =

* Tested up to WordPress 6.4

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

= 1.2.1 - 2018/07/16 =

- First public release.
