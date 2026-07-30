<?php

namespace Blocksy\Extensions\WoocommerceExtra;

if (! defined('ABSPATH')) {
	exit;
}

class CartReservedTimer {
	private $skip_stock_filter = false;

	public function get_dynamic_styles_data($args) {
		return [
			'path' => dirname(__FILE__) . '/dynamic-styles.php'
		];
	}

	public function __construct() {
		add_filter('blocksy:ext:woocommerce-extra:attributes_terms_stock_result', function($data, $product) {
			if (! self::should_use_cart_reservation()) {
				return $data;
			}

			$storage = new CartReservationStorage();

			// During quantity input generation: exclude current user's reservation
			// During product display: include ALL reservations
			$exclude_current_user = $this->skip_stock_filter;

			$reserved_variations = $storage->get_reserved_quantity_for_variations(
				array_keys($data),
				$exclude_current_user && WC()->session
					? ['exclude_session_id' => WC()->session->get_customer_id()]
					: []
			);

			foreach ($data as $variation_id => $variation_data) {
				if (
					isset($variation_data['_stock_status'])
					&&
					$variation_data['_stock_status'] !== 'instock'
				) {
					continue;
				}

				if (
					! isset($variation_data['_manage_stock'])
					||
					$variation_data['_manage_stock'] !== 'yes'
				) {
					continue;
				}

				$reserved = $reserved_variations[$variation_id] ?? 0;

				$stock_quantity = isset($variation_data['_stock']) ? (int) $variation_data['_stock'] : 0;
				$available = max(0, $stock_quantity - $reserved);

				if ($available <= 0) {
					if (isset($variation_data['_stock']) && $variation_data['_stock'] !== '') {
						$data[$variation_id]['_stock'] = '0';
					}

					$data[$variation_id]['_stock_status'] = 'outofstock';
				}
			}

			return $data;
		}, 10, 2);

		add_action(
			'wp_enqueue_scripts',
			function () {
				if (! function_exists('get_plugin_data')) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data = get_plugin_data(BLOCKSY__FILE__);

				$render = new \Blocksy_Header_Builder_Render();
				$has_mini_cart = $render->contains_item('cart');

				if (
					is_admin()
					||
					(
						blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_in_cart', 'yes') === 'no'
						&&
						blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_in_mini_cart', 'no') === 'no'
					)
					||
					(
						blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_in_mini_cart', 'no') === 'yes'
						&&
						! $has_mini_cart
					)
				) {
					return;
				}

				wp_enqueue_style(
					'blocksy-ext-woocommerce-extra-cart-reserved-timer-styles',
					BLOCKSY_URL .
						'framework/premium/extensions/woocommerce-extra/static/bundle/cart-reserved-timer.min.css',
					['blocksy-ext-woocommerce-extra-styles'],
					$data['Version']
				);
			},
			50
		);

		add_filter(
			'blocksy_customizer_options:woocommerce:general:end',
			function ($opts) {
				$opts['has_cart_reserved_timer_panel'] = blocksy_companion_get_options(
					dirname(__FILE__) . '/options.php',
					[],
					false
				);

				return $opts;
			},
			55
		);

		add_filter('blocksy:frontend:dynamic-js-chunks', function ($chunks) {
			if (!class_exists('WC_AJAX')) {
				return $chunks;
			}

			$cache_manager = new \Blocksy\CacheResetManager();

			$trigger = [
				[
					'trigger' => $cache_manager->is_there_any_page_caching() ? 'initial-mount' : 'slight-mousemove',
					'selector' => '[class*="ct-cart-reserved-timer"]',
				],

				[
					'selector' => '[class*="ct-cart-reserved-timer"]',
					'trigger' => 'jquery-event',
					'matchTarget' => false,
					'events' => [
						'added_to_cart',
						'removed_from_cart',
						'wc_fragments_refreshed',
						'updated_cart_totals',
					],
				]
			];

			$chunks[] = [
				'id' => 'blocksy_ext_woo_extra_cart_reserved_timer',
				'selector' => '[class*="ct-cart-reserved-timer"]',
				'mountOnLoad' => true,
				'trigger' => $trigger,
				'url' => blocksy_cdn_url(
					BLOCKSY_URL . 'framework/premium/extensions/woocommerce-extra/static/bundle/cart-reserved-timer.js'
				),
				'version' => blocksy_companion_get_version()
			];

			return $chunks;
		});

		add_action('wp', function () {
			if (self::should_use_cart_reservation()) {
				if (! WC()->session) {
					return;
				}

				// empty cart for current session if reservation expired
				$session_id = WC()->session->get_customer_id();
				$storage = new CartReservationStorage();
				$reservation = $storage->get_current_reservation($session_id);

				if ($reservation) {
					$current_time = current_time('timestamp', true);
					$last_modified = strtotime($reservation->last_modified);
					$woo_reserved_timer_time = blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_time', 10);
					$reserved_until = strtotime($reservation->last_modified) + ($woo_reserved_timer_time * 60);

					if ($current_time >= $reserved_until) {
						WC()->cart->empty_cart();
						$storage->remove_cart_reservation($session_id);
					}
				}
			}
		}, 20);

		add_action('wp', function () {
			if (blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_in_cart', 'yes') === 'yes') {
				add_action('woocommerce_cart_contents', function() {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->cart_page_render('cart');
				});
			}

			if (blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_in_mini_cart', 'no') === 'yes') {
				add_action('woocommerce_before_mini_cart', function() {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->cart_page_render('mini-cart');
				});
			}
		}, 25);

		// Set validation flag BEFORE WooCommerce processes add-to-cart (priority 20)
		// https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-form-handler.php
		add_action('wp_loaded', function() {
			// Regular add-to-cart form submission
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_REQUEST['add-to-cart'])) {
				$this->skip_stock_filter = true;
			}

			// Cart update form submission
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_REQUEST['update_cart'])) {
				$this->skip_stock_filter = true;
			}
		}, 19);

		// Set validation flag BEFORE WooCommerce AJAX (priority 0)
		// https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-ajax.php
		add_action('template_redirect', function() {
			if (!wp_doing_ajax()) {
				return;
			}

			$validation_actions = [
				'woocommerce_add_to_cart',
				'woocommerce_checkout',
				'blocksy_update_qty_cart',
			];

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';

			if (in_array($action, $validation_actions, true)) {
				$this->skip_stock_filter = true;
			}
		}, -1);

		// Set flag during offcanvas cart quantity field generation
		add_action('blocksy:ext:woocommerce-extra:offcanvas-cart:quantity-input:before', function() {
			$this->skip_stock_filter = true;
		});

		add_action('blocksy:ext:woocommerce-extra:offcanvas-cart:quantity-input:after', function() {
			$this->skip_stock_filter = false;
		});

		// Set flag during cart page quantity field generation
		add_action('woocommerce_before_cart_contents', function() {
			$this->skip_stock_filter = true;
		});

		add_action('woocommerce_after_cart_contents', function() {
			$this->skip_stock_filter = false;
		});

		// Set flag during checkout page quantity field generation
		add_action('blocksy:ext:woocommerce-extra:checkout:quantity-input:before', function() {
			$this->skip_stock_filter = true;
		});

		add_action('blocksy:ext:woocommerce-extra:checkout:quantity-input:after', function() {
			$this->skip_stock_filter = false;
		});

		add_action('init', function() {
			if (self::should_use_cart_reservation()) {
				$hooks_to_listen = [
					'woocommerce_add_to_cart',
					'woocommerce_remove_cart_item',
					'woocommerce_cart_item_restored',
					'woocommerce_cart_item_removed',
					'woocommerce_after_cart_item_quantity_update',
				];

				foreach ($hooks_to_listen as $hook) {
					add_action($hook, function() {
						if (!WC()->session || !WC()->cart) {
							return;
						}

						$session_id = WC()->session->get_customer_id();
						$cart_items = [];

						foreach (WC()->cart->get_cart() as $item) {
							$reserved_product_id = isset($item['variation_id']) && (int) $item['variation_id'] > 0
								? (int) $item['variation_id']
								: (int) $item['product_id'];

							$cart_items[] = [
								'product_id' => $reserved_product_id,
								'quantity' => $item['quantity'],
							];
						}

						$last_modified = gmdate('Y-m-d H:i:s', current_time('timestamp', true));

						$storage = new CartReservationStorage();
						$storage->set_cart_reservation($session_id, $cart_items, $last_modified);
					});
				}

				add_action('woocommerce_cart_emptied', function() {
					if (WC()->session) {
						$storage = new CartReservationStorage();
						$storage->remove_cart_reservation(WC()->session->get_customer_id());
					}
				});

				add_filter('woocommerce_cart_item_required_stock_is_not_enough', function($is_not_enough, $product, $values) {
					$storage = new CartReservationStorage();

					$reserved = $storage->get_reserved_quantity_for_product(
						$product->get_id(),
						WC()->session
							? ['exclude_session_id' => WC()->session->get_customer_id()]
							: []
					);

					$required_stock = isset($values['quantity']) ? (int) $values['quantity'] : 0;

					$available = max(0, $this->get_raw_stock($product) - $reserved);

					if ($available < $required_stock) {
						if (! $is_not_enough) {
							add_filter('woocommerce_format_stock_quantity', [$this, 'woocommerce_format_stock_quantity'], 10, 2);
						}

						return true;
					}

					return $is_not_enough;
				}, 10, 3);

				add_filter('woocommerce_product_get_stock_quantity', [$this, 'filter_stock_quantity'], 10, 2);
				add_filter('woocommerce_product_variation_get_stock_quantity', [$this, 'filter_stock_quantity'], 10, 2);

				add_filter('woocommerce_product_get_stock_status', [$this, 'filter_stock_status'], 10, 2);
				add_filter('woocommerce_product_variation_get_stock_status', [$this, 'filter_stock_status'], 10, 2);
			}
		});

		add_action('wp_ajax_blc_ext_cart_reserved_timer_sync', [$this, 'sync_timers']);
		add_action('wp_ajax_nopriv_blc_ext_cart_reserved_timer_sync', [$this, 'sync_timers']);
	}

	/**
	 * Get raw stock quantity bypassing our woocommerce_product_get_stock_quantity
	 * filter. Must be used in callbacks that subtract reservations themselves
	 * (required_stock_is_not_enough, format_stock_quantity, is_in_stock,
	 * get_stock_html) to avoid double-subtracting reservations.
	 */
	private function get_raw_stock($product) {
		return (int) $product->get_stock_quantity('edit');
	}

	public function filter_stock_quantity($stock_quantity, $product) {
		// Cart and checkout pages have two contexts:
		// 1. Validation (skip_stock_filter=false): skip entirely so user's
		//    reserved items pass stock validation
		// 2. Quantity inputs (skip_stock_filter=true): exclude only current
		//    user's reservation so max input reflects what they can order
		//    (stock minus other users' reservations)
		if ((is_cart() || is_checkout()) && !$this->skip_stock_filter) {
			return $stock_quantity;
		}

		$storage = new CartReservationStorage();

		// During quantity input generation: exclude current user's reservation
		// During product display: include ALL reservations (show true available stock)
		$exclude_current_user = $this->skip_stock_filter;

		$reserved = $storage->get_reserved_quantity_for_product(
			$product->get_id(),
			$exclude_current_user && WC()->session
				? ['exclude_session_id' => WC()->session->get_customer_id()]
				: []
		);

		return max(0, $stock_quantity - $reserved);
	}

	public function filter_stock_status($status, $product) {
		$out_of_stock = \Automattic\WooCommerce\Enums\ProductStockStatus::OUT_OF_STOCK;

		if ($status === $out_of_stock || !$product->managing_stock()) {
			return $status;
		}

		// Cart and checkout: skip so user's reserved items remain "in stock"
		if ((is_cart() || is_checkout()) && !$this->skip_stock_filter) {
			return $status;
		}

		$storage = new CartReservationStorage();

		// During quantity input generation: exclude current user's reservation
		// During product display: include ALL reservations
		$exclude_current_user = $this->skip_stock_filter;

		$reserved = $storage->get_reserved_quantity_for_product(
			$product->get_id(),
			$exclude_current_user && WC()->session
				? ['exclude_session_id' => WC()->session->get_customer_id()]
				: []
		);

		$available = $this->get_raw_stock($product) - $reserved;

		if ($available <= 0) {
			if ($product->backorders_allowed()) {
				return \Automattic\WooCommerce\Enums\ProductStockStatus::ON_BACKORDER;
			}

			return $out_of_stock;
		}

		return $status;
	}

	public function woocommerce_format_stock_quantity($stock_quantity, $product) {
		remove_filter('woocommerce_format_stock_quantity', [$this, 'woocommerce_format_stock_quantity'], 10, 2);

		$storage = new CartReservationStorage();

		$reserved = $storage->get_reserved_quantity_for_product(
			$product->get_id(),
			WC()->session
				? ['exclude_session_id' => WC()->session->get_customer_id()]
				: []
		);

		return max(0, $this->get_raw_stock($product) - $reserved);
	}

	public function sync_timers() {
		wp_send_json_success([
			'cart' => $this->cart_page_render('cart'),
			'mini_cart' => $this->cart_page_render('mini-cart'),
		]);
	}

	public function cart_page_render($class_suffix = 'cart') {
		$classes = ['ct-cart-reserved-timer-placeholder-' . $class_suffix];

		if (!WC()->session) {
			return blocksy_html_tag('div', ['class' => join(' ', $classes)], '');
		}

		$session_id = WC()->session->get_customer_id();

		if (!$session_id) {
			return blocksy_html_tag('div', ['class' => join(' ', $classes)], '');
		}

		$storage = new CartReservationStorage();
		$reservation = $storage->get_current_reservation($session_id);

		if (!$reservation) {
			return blocksy_html_tag('div', ['class' => join(' ', $classes)], '');
		}

		$current_time = current_time('timestamp', true);
		$last_modified = strtotime($reservation->last_modified);
		$woo_reserved_timer_time = blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_time', 10);
		$reserved_until = strtotime($reservation->last_modified) + ($woo_reserved_timer_time * 60);

		$remaining = $reserved_until - $current_time;

		if ($remaining <= 0) {
			return blocksy_html_tag('div', ['class' => join(' ', $classes)], '');
		}

		$remain_minutes = intdiv($remaining, 60);
		$remain_seconds = $remaining % 60;

		$date = gmdate('Y-m-d H:i:s', $reserved_until);

		$countdown = blocksy_html_tag(
			'time',
			['datetime' => $date],
			blocksy_html_tag('span', [], str_pad($remain_minutes, 2, '0', STR_PAD_LEFT)) .
				':' .
			blocksy_html_tag('span', [], str_pad($remain_seconds, 2, '0', STR_PAD_LEFT))
		);

		$message = str_replace(
			'{time}',
			$countdown,
			blocksy_companion_theme_functions()->blocksy_get_theme_mod(
				'woo_reserved_timer_message',
				// phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings
				__('<p><strong>🔥 Hurry up! Your selected items are in high demand!</strong><br>Your cart will be reserved for {time} minutes.</p>', 'blocksy-companion')
			)
		);

		$classes = ['ct-cart-reserved-timer-' . $class_suffix];

		return blocksy_html_tag(
			'div',
			[
				'class' => join(' ', $classes),
				'data-timeout' => $remaining,
			],
			blocksy_html_tag('div', ['class' => 'ct-cart-reserved-timer-message'], $message)
		);
	}

	public static function should_use_cart_reservation() {
		return (
			blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_in_cart', 'yes') === 'yes'
			||
			blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_in_mini_cart', 'no') === 'yes'
		);
	}
}
