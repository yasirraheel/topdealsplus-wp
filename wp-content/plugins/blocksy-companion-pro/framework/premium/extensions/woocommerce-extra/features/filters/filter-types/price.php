<?php

namespace Blocksy\Extensions\WoocommerceExtra;

use \Automattic\WooCommerce\Internal\ProductAttributesLookup\Filterer;
use \Automattic\WooCommerce\Internal\ProductAttributesLookup\DataRegenerator;

class PriceFilter extends BaseFilter {
	public function get_filter_id() {
		return 'price_filter';
	}

	public function get_filtered_price() {
		$request = $this->get_filtered_request();

		if (! $request) {
			return null;
		}

		$wc_filters = new \Automattic\WooCommerce\StoreApi\Utilities\ProductQueryFilters();

		$prices = $wc_filters->get_filtered_price($request);

		$min_price = $prices->min_price;
		$max_price = $prices->max_price;

		// The range comes from the product meta lookup table, which stores net
		// (tax-excluded) prices. When the shop is set to display prices
		// including tax but stores them excluding tax, the catalog shows e.g.
		// 120 while the lookup holds 100 -- so the slider bounds would read 100
		// and mismatch what the customer sees. We add the displayed tax back
		// here so the slider matches the catalog.
		//
		// We follow the WC_Query flow: this is the exact reverse of
		// WC_Query::price_filter_post_clauses() (which subtracts the same tax
		// from the submitted min/max before querying the net lookup table), and
		// our filtering runs through that same WC_Query method. Same condition
		// and same standard tax class on both sides keeps the slider bounds and
		// the actual filtering in agreement.
		//
		// Like WC_Query, this only handles one direction (prices stored
		// excluding tax, displayed including tax). The reverse (stored
		// including, displayed excluding) is intentionally left alone: WC_Query
		// doesn't adjust it either, so adjusting only the range here would
		// desync it from the filtering and break it at the bounds.
		//
		// WooCommerce also has a newer, per-tax-class flow that handles both
		// directions accurately:
		// Automattic\WooCommerce\Internal\ProductFilters\QueryClauses. If we
		// ever need that more complete solution, we'll have to move our
		// filtering off WC_Query onto that class too, so the range and the
		// filtering stay symmetric.
		if (
			wc_tax_enabled()
			&&
			'incl' === get_option('woocommerce_tax_display_shop')
			&&
			! wc_prices_include_tax()
		) {
			$tax_class = apply_filters('woocommerce_price_filter_widget_tax_class', '');
			$tax_rates = \WC_Tax::get_rates($tax_class);

			if ($tax_rates) {
				$min_price += \WC_Tax::get_tax_total(\WC_Tax::calc_exclusive_tax($min_price, $tax_rates));
				$max_price += \WC_Tax::get_tax_total(\WC_Tax::calc_exclusive_tax($max_price, $tax_rates));
			}
		}

		return [
			'min' => floor($min_price),
			'max' => ceil($max_price)
		];
	}

	public function get_reset_url($attributes = []) {
		$prices = $this->get_filtered_price();

		if (! $prices) {
			return false;
		}

		if (
			(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				isset($_GET['min_price'])
				&&
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$_GET['min_price'] !== $prices['min']
			) ||
			(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				isset($_GET['max_price'])
				&&
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$_GET['max_price'] !== $prices['max']
			)
		) {
			return remove_query_arg(self::get_query_params());
		}
	 
		return false;
	}

	public function render($attributes = []) {
		$prices = $this->get_filtered_price();

		if (! $prices) {
			return '';
		}

		$max_range = $prices['max'] - $prices['min'];

		if (intval($max_range) === 0) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$min_price = max(blocksy_akg('min_price', $_GET, $prices['min']), $prices['min']);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$max_price = min(blocksy_akg('max_price', $_GET, $prices['max']), $prices['max']);

		$leftStylePos = max(0, (($min_price - $prices['min']) / $max_range) * 100);
		$rightStylePos = min(100, (($max_price - $prices['min']) / $max_range) * 100);

		$currency = get_woocommerce_currency_symbol();
		$price_format = get_woocommerce_price_format();
		$currency_pos = get_option('woocommerce_currency_pos');

		$thousand_separator = wc_get_price_thousand_separator();

		$min_price = number_format($min_price, 0, wc_get_price_decimal_separator(), $thousand_separator);
		$max_price = number_format($max_price, 0, wc_get_price_decimal_separator(), $thousand_separator);

		$min_price_html = blocksy_html_tag(
			'span',
			['class' => 'ct-price-filter-min'],
			blocksy_safe_sprintf($price_format, $currency, $min_price)
		);

		$max_price_html = blocksy_html_tag(
			'span',
			['class' => 'ct-price-filter-max'],
			blocksy_safe_sprintf($price_format, $currency, $max_price)
		);

		$min_price_input_html = blocksy_html_tag(
			'div',
			[
				'class' => 'ct-price-filter-input-min',				
			],
			__('Minimum:', 'blocksy-companion') .
			blocksy_html_tag(
				'div',
				[
					'class' => 'ct-price-filter-input ct-pseudo-input'
				],
				blocksy_html_tag(
					'small',
					[],
					$currency
				) .
				blocksy_html_tag(
					'input',
					[
						'type' => 'number',
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'value' => sanitize_text_field(wp_unslash(blocksy_akg('min_price', $_GET, $prices['min']))),
						'min' => $prices['min'],
						'max' => $prices['max'],
						'step' => 1,
						'name' => 'min_price',
					],
					''
				),
			),
		);

		$max_price_input_html = blocksy_html_tag(
			'div',
			[
				'class' => 'ct-price-filter-input-max',				
			],
			__('Maximum:', 'blocksy-companion') .
			blocksy_html_tag(
				'div',
				[
					'class' => 'ct-price-filter-input ct-pseudo-input'
				],
				blocksy_html_tag(
					'small',
					[],
					$currency
				) .
				blocksy_html_tag(
					'input',
					[
						'type' => 'number',
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'value' => sanitize_text_field(wp_unslash(blocksy_akg('max_price', $_GET, $prices['max']))),
						'min' => $prices['min'],
						'max' => $prices['max'],
						'step' => 1,
						'name' => 'max_price',
					],
					''
				),
			),
		);

		return blocksy_html_tag(
			'div',
			[
				'class' => 'ct-price-filter',
			],
			blocksy_html_tag(
				'div',
				[
					'class' => 'ct-price-filter-slider'
				],
				blocksy_html_tag(
					'div',
					[
						'class' => 'ct-price-filter-range-track',
						'style' => '--start: ' . $leftStylePos . '%; --end: ' . ($rightStylePos) . '%;'
					],
					''
				) .
				blocksy_html_tag(
					'input',
					[
						'type' => 'range',
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'value' => isset($_GET['min_price']) ? sanitize_text_field(wp_unslash($_GET['min_price'])) : $prices['min'],
						'min' => $prices['min'],
						'max' => $prices['max'],
						'step' => 1,
						'name' => 'min_price',
					],
					''
				) .
				blocksy_html_tag(
					'span',
					[
						'class' => 'ct-price-filter-range-handle-min',
						'style' => 'inset-inline-start: ' . $leftStylePos . '%',
					],
					(
						$attributes['showTooltips'] ? blocksy_html_tag(
							'span',
							[
								'class' => 'ct-tooltip'
							],
							blocksy_safe_sprintf($price_format, $currency, $min_price)
						) : ''
					)
				) .
				blocksy_html_tag(
					'input',
					[
						'type' => 'range',
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'value' => isset($_GET['max_price']) ? sanitize_text_field(wp_unslash($_GET['max_price'])) : $prices['max'],
						'min' => $prices['min'],
						'max' => $prices['max'],
						'step' => 1,
						'name' => 'max_price',
					],
					''
				) .
				blocksy_html_tag(
					'span',
					[
						'class' => 'ct-price-filter-range-handle-max',
						'style' => 'inset-inline-start: ' . $rightStylePos . '%',
					],
					(
						$attributes['showTooltips'] ? blocksy_html_tag(
							'span',
							[
								'class' => 'ct-tooltip'
							],
							blocksy_safe_sprintf($price_format, $currency, $max_price)
						) : ''
					)
				)
			).
			(
				$attributes['showPrices'] && ! $attributes['showInputs'] ? blocksy_html_tag(
					'div',
					[
						'class' => 'ct-price-filter-values',
					],
					blocksy_html_tag(
						'span',
						[],
						__('Price:', 'blocksy-companion') . '&nbsp;'
					) .
					$min_price_html .
					blocksy_html_tag(
						'span',
						[],
						'&nbsp;-&nbsp;'
					) .
					$max_price_html
				) : ''
			) . 
			(
				$attributes['showInputs'] ? blocksy_html_tag(
					'div',
					[
						'class' => 'ct-price-filter-inputs',
						'data-currency-position' => $currency_pos,
					],
					$min_price_input_html . $max_price_input_html 
				) : ''
			)
		);
	}

	public static function get_query_params() {
		return ['min_price', 'max_price'];
	}

	public function get_applied_filters() {
		$prices = $this->get_filtered_price();

		if (
			! $prices
			||
			! $this->get_reset_url()
		) {
			return [];
		}

		return [
			'name' => __('Price', 'blocksy-companion'),
			'items' => [
				[
					'name' => blocksy_safe_sprintf(
						'%s - %s',
						wc_price(
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended
							max(blocksy_akg('min_price', $_GET, $prices['min']), $prices['min']),
							[
								'decimals' => 0
							]
						),
						wc_price(
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended
							min(blocksy_akg('max_price', $_GET, $prices['max']), $prices['max']),
							[
								'decimals' => 0
							]
						)
					),
					'value' => '',
					'href' => $this->get_reset_url()
				]
			]
		];
	}

	public function get_filtered_request() {
		$apply_filters = new ApplyFilters();

		$params = FiltersUtils::get_query_params();
		$filter_params = $this->get_query_params();

		$params = $params['params'];

		foreach ($filter_params as $param) {
			unset($params[$param]);
		}

		$products_query = $apply_filters->get_custom_query_for($params);

		$products = $products_query->posts;

		if (empty($products)) {
			return null;
		}

		$wc_filters = new \Automattic\WooCommerce\StoreApi\Utilities\ProductQueryFilters();

		$request = new \WP_REST_Request('GET', '/wp/v2/posts');

		$request->set_param('include', $products);

		return $request;
	}
}
