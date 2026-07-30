<?php

namespace Blocksy\Extensions\WoocommerceExtra;

if (! defined('ABSPATH')) {
	exit;
}

class FloatingCart {
	public function get_dynamic_styles_data($args) {
		return [
			'path' => dirname(__FILE__) . '/dynamic-styles.php'
		];
	}

	public function __construct() {
		add_action(
			'wp_enqueue_scripts',
			function () {
				if (!function_exists('get_plugin_data')) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data = get_plugin_data(BLOCKSY__FILE__);

				if (
					is_admin()
					||
					! is_singular('product')
				) {
					return;
				}

				wp_enqueue_style(
					'blocksy-ext-woocommerce-extra-floating-cart-styles',
					BLOCKSY_URL .
						'framework/premium/extensions/woocommerce-extra/static/bundle/floating-bar.min.css',
					['blocksy-ext-woocommerce-extra-styles'],
					$data['Version']
				);
			},
			50
		);

		add_filter(
			'blocksy_single_product_floating_cart',
			function ($opts) {
				$opts['has_floating_bar'] = blocksy_companion_get_options(
					dirname(__FILE__) . '/options.php',
					[],
					false
				);

				return $opts;
			}
		);

		add_filter(
			'blocksy:footer:offcanvas-drawer',
			function ($els, $payload) {
				$position = blocksy_companion_theme_functions()->blocksy_get_theme_mod(
					'floating_bar_position',
					'top'
				);

				if (
					$position === 'top' && $payload['location'] !== 'start'
					||
					$position === 'bottom' && $payload['location'] !== 'end'
				) {
					return $els;
				}

				if (! $this->has_floating_cart()) {
					return $els;
				}

				$view = blocksy_companion_render_view(
					dirname(__FILE__) . '/view.php',
					[]
				);

				if (! $view) {
					return $els;
				}

				if ($payload['location'] === 'start') {
					$els[] = $view;
				}

				if ($payload['location'] === 'end') {
					$els[] = [
						'attr' => [
							'data-floating-bar' => 'no'
						],
						'content' => $view,
					];
				}

				return $els;
			},
			5,
			2
		);

		add_filter('blocksy:frontend:dynamic-js-chunks', function ($chunks) {
			if (!class_exists('WC_AJAX')) {
				return $chunks;
			}

			$chunks[] = [
				'id' => 'blocksy_ext_woo_extra_floating_cart',
				'selector' => '.ct-floating-bar',
				'url' => blocksy_cdn_url(
					BLOCKSY_URL . 'framework/premium/extensions/woocommerce-extra/static/bundle/floating-cart.js'
				),
				'trigger' => [
					[
						'selector' => join(', ', [
							'.ct-floating-bar .quantity .qty',
							'.ct-cart-actions .quantity .qty'
						]),
						'trigger' => 'dom-event',
						'events' => [
							'input',
						],
					],
					[
						'selector' => '.ct-floating-bar',
						'trigger' => 'intersection-observer',
					]
				],
				'position' => 'bottom',
				'target' => '.single-product #main-container .single_add_to_cart_button',
				'version' => blocksy_companion_get_version()
			];

			return $chunks;
		});

		add_action(
			'blocksy:ext:woocommerce-extra:floating-bar:actions:before',
			function () {
				add_filter(
					'woocommerce_loop_add_to_cart_link',
					[$this, 'transform_add_to_cart'],
					10, 3
				);
			}
		);

		add_action(
			'blocksy:ext:woocommerce-extra:floating-bar:actions:after',
			function () {
				remove_filter(
					'woocommerce_loop_add_to_cart_link',
					[$this, 'transform_add_to_cart'],
					10, 3
				);
			}
		);
	}

	public function transform_add_to_cart($link, $product, $args) {
		if (! $product->is_type('variable')) {
			return $link;
		}

		$maybe_current_variation = null;

		if (blocksy_companion_theme_functions()->blocksy_manager()) {
			$maybe_current_variation = blocksy_companion_theme_functions()->blocksy_manager()
				->woocommerce
				->retrieve_product_default_variation($product);
		}

		if (! $maybe_current_variation) {
			return str_replace('add_to_cart_button', '', $link);
		}

		$simple = new \WC_Product_Simple($product->get_id());

		$args = [
			'quantity'   => 1,
			'class' => implode(
				' ',
				array_filter(
					[
						'button',
						'product_type_' . $maybe_current_variation->get_type(),
						(
							$maybe_current_variation->is_purchasable()
							&&
							$product->is_in_stock()
						) ? 'add_to_cart_button' : '',
						(
							$simple->supports('ajax_add_to_cart')
							&&
							$maybe_current_variation->is_purchasable()
							&&
							$maybe_current_variation->is_in_stock()
						) ? 'ajax_add_to_cart' : '',
					]
				)
			),
			'attributes' => [
				'data-product_id' => $maybe_current_variation->get_id(),
				'data-product_sku' => $maybe_current_variation->get_sku(),
				'aria-label' => $maybe_current_variation->add_to_cart_description(),
				'rel' => 'nofollow'
			]
		];

		$args = apply_filters(
			'woocommerce_loop_add_to_cart_args',
			$args,
			$product
		);

		return blocksy_companion_safe_sprintf(
			'<a href="%s" data-quantity="%s" class="%s" %s>%s</a>',
			esc_url($maybe_current_variation->add_to_cart_url()),
			esc_attr(isset($args['quantity']) ? $args['quantity'] : 1),
			esc_attr(isset($args['class']) ? $args['class'] : 'button'),
			isset($args['attributes']) ? wc_implode_html_attributes($args['attributes']) : '',
			esc_html($simple->add_to_cart_text())
		);
	}

	public function has_floating_cart() {
		if (! function_exists('is_woocommerce')) {
			return false;
		}

		global $product;

		global $post;

		if (is_string($product)) {
			$product = wc_get_product();
		}

		if (! blocksy_companion_theme_functions()->blocksy_manager()) {
			return false;
		}

		if (! blocksy_companion_theme_functions()->blocksy_manager()->screen->is_product()) {
			return false;
		}

		if (
			! $product && $post
			||
			intval($product->get_id()) !== intval($post->ID)
		) {
			$product = wc_get_product($post->ID);
		}

		if (! $product) {
			return false;
		}

		if (
			(
				! $product->is_purchasable() || ! $product->is_in_stock()
			) && ! $product->is_type('external')
		) {
			return false;
		}

		return $product;
	}
}

