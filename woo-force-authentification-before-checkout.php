<?php
/*
Plugin Name: Force Authentification Before Checkout for WooCommerce
Description: Force customer to log in or register before checkout, with optional email OTP authentication.
Version: 2.0.0
Author: Link Nacional
Author URI: https://linknacional.com.br/

Requires at least: 6.0
Requires PHP: 8.2
Requires Plugins: woocommerce

License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Text Domain: woo-force-authentification-before-checkout
Domain Path: /languages
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/woo-force-authentification-before-checkout-file.php';
