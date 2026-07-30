<?php

namespace Blocksy\Extensions\WoocommerceExtra;

if (! defined('ABSPATH')) {
	exit;
}

class Checkout {
	public function __construct() {
		add_filter(
			'blocksy_customizer_options:woocommerce:general:coupon:after',
			function ($opts) {
				$opts[] = blocksy_companion_get_options(
					dirname(__FILE__) . '/options.php',
					[],
					false
				);

				return $opts;
			},
			60
		);

		add_action('wp', function () {
			if (
				! blocksy_companion_theme_functions()->blocksy_manager()
				||
				! isset(blocksy_companion_theme_functions()->blocksy_manager()->woocommerce)
				||
				! isset(blocksy_companion_theme_functions()->blocksy_manager()->woocommerce->checkout)
				||
				! blocksy_companion_theme_functions()->blocksy_manager()->woocommerce->checkout->has_custom_checkout()
			) {
				return;
			}

			if (
				blocksy_companion_theme_functions()->blocksy_get_theme_mod('blocksy_has_image_toggle', 'no') !== 'yes'
				&&
				blocksy_companion_theme_functions()->blocksy_get_theme_mod('blocksy_has_quantity_toggle', 'no') !== 'yes'
			) {
				return;
			}

			add_action(
				'woocommerce_review_order_before_cart_contents',
				function () {
					add_filter(
						'woocommerce_cart_item_product',
						[$this, 'reset_product']
					);
				}
			);

			add_action(
				'woocommerce_review_order_after_cart_contents',
				function () {
					remove_filter(
						'woocommerce_cart_item_product',
						[$this, 'reset_product']
					);

					blocksy_companion_render_view_e(dirname(__FILE__) . '/view.php', []);
				},
				0
			);

			if (blocksy_companion_theme_functions()->blocksy_get_theme_mod('blocksy_has_quantity_toggle', 'no') === 'yes') {
				add_action(
					'woocommerce_checkout_cart_item_quantity',
					[$this, 'render_quantity_in_checkout'],
					10,
					3
				);

				add_filter('blocksy:frontend:dynamic-js-chunks', function ($chunks) {
					if (! function_exists('is_checkout') || ! is_checkout()) {
						return $chunks;
					}

					$chunks[] = [
						'id' => 'blocksy_ext_woo_extra_checkout_qty',
						'selector' => '.woocommerce-checkout [data-checkout-qty]',
						'url' => blocksy_cdn_url(
							BLOCKSY_URL . 'framework/premium/extensions/woocommerce-extra/static/bundle/checkout-quantity.js'
						),
						'trigger' => [
							[
								'selector' => '.woocommerce-checkout [data-checkout-qty]',
								'trigger' => 'dom-event',
								'events' => ['input'],
							]
						],
					];

					return $chunks;
				});
			}
		});
	}

	public function reset_product () {
		return null;
	}

	public function render_quantity_in_checkout ($product_quantity, $cart_item, $cart_item_key) {
		if (blocksy_companion_theme_functions()->blocksy_get_theme_mod('blocksy_has_quantity_toggle', 'no') !== 'yes') {
			return $product_quantity;
		}

		$product = apply_filters(
			'woocommerce_cart_item_product',
			$cart_item['data'],
			$cart_item,
			$cart_item_key
		);

		do_action('blocksy:ext:woocommerce-extra:checkout:quantity-input:before', $product);

		$product_id = apply_filters(
			'woocommerce_cart_item_product_id',
			$cart_item['product_id'],
			$cart_item,
			$cart_item_key
		);

		if (! $product->is_sold_individually()) {
			// https://codecanyon.net/item/b2bking-the-ultimate-woocommerce-b2b-plugin/26689576 integration
			$callback = function($args) use ($cart_item) {
				$args['input_value'] = $cart_item['quantity'];

				return $args;
			};

			add_filter('woocommerce_quantity_input_args', $callback, 999, 1);

			$product_quantity = woocommerce_quantity_input(
				[
					'input_name' => '',
					'input_value' => $cart_item['quantity'],
					'min_value' => 0,
					'max_value' => $product->get_max_purchase_quantity(),
				],
				$product,
				false
			);

			remove_filter('woocommerce_quantity_input_args', $callback, 999, 1);

			// Modify the qty input: remove name (so it's never serialized
			// in the checkout form) and add data attributes for the JS
			// chunk that handles quantity changes via a separate AJAX call.
			$tag = new \WP_HTML_Tag_Processor($product_quantity);

			if ($tag->next_tag(['class_name' => 'qty'])) {
				$tag->remove_attribute('name');
				$tag->set_attribute('data-checkout-qty', '');
				$tag->set_attribute('data-cart-key', $cart_item_key);
			}

			$product_quantity = $tag->get_updated_html();
		}

		do_action('blocksy:ext:woocommerce-extra:checkout:quantity-input:after', $product);

		return $product_quantity;
	}
}
