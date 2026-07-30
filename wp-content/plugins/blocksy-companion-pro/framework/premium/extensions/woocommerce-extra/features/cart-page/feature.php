<?php

namespace Blocksy\Extensions\WoocommerceExtra;

if (! defined('ABSPATH')) {
	exit;
}

class CartPage {
	public function __construct() {

		add_filter(
			'blocksy_customizer_options:woocommerce:cart_page:before',
			function ($options) {
				$options = blocksy_companion_get_options(
					dirname(__FILE__) . '/options.php',
					[],
					false
				);

				return $options;
			}
		);

		add_filter('blocksy:woocommerce:cart:wrapper-class', function ($class) {
			if (blocksy_companion_theme_functions()->blocksy_get_theme_mod('has_cart_auto_update', 'no') === 'yes') {
				$class .= ' ct-cart-auto-update';
			}

			return trim($class);
		});

		add_filter('woocommerce_coupons_enabled', function ($enabled) {
			if (! is_cart()) {
				return $enabled;
			}

			$has_cart_coupons = blocksy_companion_theme_functions()->blocksy_get_theme_mod(
				'has_cart_coupons',
				'yes'
			);

			if ($has_cart_coupons !== 'yes') {
				return false;
			}

			return $enabled;
		});
	}
}
