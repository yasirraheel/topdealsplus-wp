<?php

namespace Blocksy\Extensions\WoocommerceExtra;

if (! defined('ABSPATH')) {
	exit;
}

class Swatches {
	private $is_computing_label = false;

	public function get_dynamic_styles_data($args) {
		return [
			'path' => dirname(__FILE__) . '/dynamic-styles.php'
		];
	}

	public function __construct() {
		new SwatchesApi();

		add_action(
			'woocommerce_before_add_to_cart_form',
			function () {
				ob_start();
			}
		);

		add_action(
			'woocommerce_after_add_to_cart_form',
			function () {
				$content = ob_get_clean();

				global $product;

				$form_processor = new \WP_HTML_Tag_Processor($content);

				if ($form_processor->next_tag([
					'tag_name' => 'form',
					'class_name' => 'variations_form'
				])) {
					foreach ($this->get_variations_form_attributes($product) as $name => $value) {
						$form_processor->set_attribute($name, $value);
					}

					$content = $form_processor->get_updated_html();
				}

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $content;
			}
		);

		add_filter('blocksy:general:ct-scripts-localizations', function ($data) {
			if (!function_exists('get_plugin_data')) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_data = get_plugin_data(BLOCKSY__FILE__);

			$data['dynamic_styles']['swatches'] = add_query_arg(
				'ver',
				$plugin_data['Version'],
				blocksy_cdn_url(
					BLOCKSY_URL . 'framework/premium/extensions/woocommerce-extra/static/bundle/variation-swatches.min.css'
				)
			);

			$data['swatches_data'] = [
				'woocommerce_hide_out_of_stock_items' => get_option(
					'woocommerce_hide_out_of_stock_items',
					'no'
				) === 'yes',
				'limit_number_of_swatches_message' => esc_html__('+{items} More', 'blocksy-companion'),
			];

			return $data;
		});

		add_action(
			'wp_enqueue_scripts',
			function () {
				if (!function_exists('get_plugin_data')) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data = get_plugin_data(BLOCKSY__FILE__);

				wp_register_style(
					'blocksy-ext-woocommerce-extra-variation-swatches-styles',
					BLOCKSY_URL .
						'framework/premium/extensions/woocommerce-extra/static/bundle/variation-swatches.min.css',
					['blocksy-ext-woocommerce-extra-styles'],
					$data['Version']
				);

				if (is_cart() && WC()->cart) {
					$cross_sells = WC()->cart->get_cross_sells();

					$has_variable = false;

					foreach ($cross_sells as $cross_sell) {
						$product = wc_get_product($cross_sell);

						if (! $product) {
							continue;
						}

						if ($product->get_type() === 'variable') {
							$has_variable = true;
							break;
						}
					}

					if ($has_variable) {
						wp_enqueue_style(
							'blocksy-ext-woocommerce-extra-variation-swatches-styles'
						);
					}
				}

				if (blocksy_companion_theme_functions()->blocksy_manager()) {
					blocksy_companion_theme_functions()->blocksy_manager()->screen->on_product_shortcode_rendered(function ($tag) {
						if (
							$tag !== 'product_page'
							&&
							function_exists('blocksy_has_product_card_specific_layer')
							&&
							blocksy_has_product_card_specific_layer('product_swatches')
						) {
							wp_enqueue_style(
								'blocksy-ext-woocommerce-extra-variation-swatches-styles'
							);
						}

						if (
							$tag === 'product_page'
							&&
							blocksy_companion_theme_functions()->blocksy_has_product_specific_layer('product_add_to_cart', [
								'respect_post_type' => false
							])
						) {
							wp_enqueue_style(
								'blocksy-ext-woocommerce-extra-variation-swatches-styles'
							);
						}
					});
				}

				add_filter(
					'render_block',
					function ($block_content, $block) {
						if (
							$block['blockName'] === 'blocksy/woocommerce-filters'
							||
							$block['blockName'] === 'woocommerce/add-to-cart-form'
						) {
							wp_enqueue_style(
								'blocksy-ext-woocommerce-extra-variation-swatches-styles'
							);
						}

						return $block_content;
					},
					10,
					2
				);

				/**
				 * Filters whether variation swatches styles should be force-enqueued.
				 *
				 * @since 2.0.49
				 *
				 * @param bool $force_enqueue Whether to enqueue variation swatches styles. Default false.
				 */
				$force_enqueue = apply_filters('blocksy:ext:woocommerce-extra:swatches:css', false);

				if (
					$force_enqueue
					||
					is_customize_preview()
				) {
					wp_enqueue_style(
						'blocksy-ext-woocommerce-extra-variation-swatches-styles'
					);
				}

				global $post;
				$product = null;

				if (
					$post
					&&
					$post instanceof \WP_Post
				) {
					$product = wc_get_product($post->ID);
				}

				$is_simple_product = blocksy_companion_get_ext(
					'woocommerce-extra'
				)->utils->is_simple_product($product);

				if (
					is_admin()
					||
					(
						is_singular('product')
						&&
						$is_simple_product['value']
						&&
						! is_customize_preview()
						&&
						(
							blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_has_related_upsells', 'yes') === 'no'
							||
							(
								blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_has_related_upsells', 'yes') === 'yes'
								&&
								function_exists('blocksy_has_product_card_specific_layer')
								&&
								! blocksy_has_product_card_specific_layer('product_swatches')
							)
						)
					)
					||
					(
						is_singular('product')
						&&
						! blocksy_companion_theme_functions()->blocksy_has_product_specific_layer('product_add_to_cart')
						&&
						! is_customize_preview()
						&&
						blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_has_related_upsells', 'yes') === 'no'
					)
					||
					(
						function_exists('is_woocommerce')
						&&
						! is_woocommerce()
					)
					||
					(
						(
							is_shop()
							||
							is_product_tag()
							||
							is_product_category()
							||
							is_product_taxonomy()
							||
							is_search()
						)
						&&
						function_exists('blocksy_has_product_card_specific_layer')
						&&
						! blocksy_has_product_card_specific_layer('product_swatches')
					)
				) {
					return;
				}

				wp_enqueue_style(
					'blocksy-ext-woocommerce-extra-variation-swatches-styles'
				);
			},
			50
		);

		new SwatchesLoopVariableProduct();

		if (is_admin()) {
			new SwatchesPersistAttributes();
		}

		add_filter('blocksy:frontend:dynamic-js-chunks', function ($chunks) {
			if (! class_exists('WC_AJAX')) {
				return $chunks;
			}

			$chunks[] = [
				'id' => 'blocksy_ext_woo_extra_swatches_variation_url',
				'selector' => implode(', ', [
					'.variations_form[data-variations-url="yes"] .ct-variation-swatches select'
				]),
				'trigger' => [
					[
						'trigger' => 'dom-event',
						'events' => [
							'change'
						],
						'selector' => '.variations_form[data-variations-url="yes"] .ct-variation-swatches select',
					],
				],
				'url' => blocksy_cdn_url(
					BLOCKSY_URL . 'framework/premium/extensions/woocommerce-extra/static/bundle/variation-url.js'
				),
				'version' => blocksy_companion_get_version()
			];

			ob_start();
			wc_get_template('single-product/add-to-cart/variation.php');
			$raw_html = ob_get_clean();

			$chunks[] = [
				'id' => 'blocksy_ext_woo_extra_swatches',
				'selector' => implode(', ', [
					'.variations_form .ct-swatch-container',
					'.ct-card-variation-swatches .ct-swatch-container',
					'.variations_form .ct-variation-swatches select:last-child',
					'.ct-card-variation-swatches .ct-variation-swatches select:last-child'
				]),
				'trigger' => [
					[
						'trigger' => 'click',
						'selector' => '.variations_form .ct-swatch-container, .ct-card-variation-swatches .ct-swatch-container',
					],

					[
						'trigger' => 'dom-event',
						'events' => [
							'change'
						],
						'selector' => '.variations_form .ct-variation-swatches select:last-child, .ct-card-variation-swatches .ct-variation-swatches select:last-child',
					],

					// TODO: We should ideally listen to the normal reset event
					[
						'trigger' => 'click',
						'selector' => '.variations_form .reset_variations, .ct-card-variation-swatches .reset_variations',
					],

					[
						'trigger' => 'click',
						'selector' => '.ct-swatches-more',
					],

					// "Extra Product Options & Add-Ons for WooCommerce" by ThemeComplete
					// (https://themecomplete.com/extra-product-options/)
					// embeds nested variable products via AJAX and fires
					// tc_variation_form jQuery event on the nested .variations_form
					// once ready. See: swatches/integrations/themecomplete-epo.js
					[
						'trigger' => 'jquery-event',
						'events' => [
							'tc_variation_form'
						],
						'selector' => '.variations_form',
					]
				],
				'url' => blocksy_cdn_url(
					BLOCKSY_URL . 'framework/premium/extensions/woocommerce-extra/static/bundle/swatches.js'
				),
				'deps' => [
					'underscore',
					'wc-add-to-cart-variation',
					'wp-util'
				],
				'global_data' => [
					[
						'var' => 'wc_add_to_cart_variation_params',
						'data' => [
							'wc_ajax_url' => \WC_AJAX::get_endpoint('%%endpoint%%'),
							'i18n_no_matching_variations_text' => esc_attr__('Sorry, no products matched your selection. Please choose a different combination.', 'blocksy-companion'),
							'i18n_make_a_selection_text' => esc_attr__('Please select some product options before adding this product to your cart.', 'blocksy-companion'),
							'i18n_unavailable_text' => esc_attr__('Sorry, this product is unavailable. Please choose a different combination.', 'blocksy-companion'),
							'i18n_out_of_stock' => esc_attr__('Out of Stock', 'blocksy-companion'),
						]
					]
				],
				'raw_html' => [
					'html' => $raw_html,
					'selector' => '#tmpl-variation-template'
				]
			];

			return $chunks;
		});

		add_filter(
			'blocksy_customizer_options:woocommerce:general:end',
			function ($opts) {
				$opts['has_variation_swatches_panel'] = blocksy_companion_get_options(
					dirname(__FILE__) . '/options.php',
					[],
					false
				);

				return $opts;
			},
			55
		);

		add_filter(
			'blocksy_woo_card_options_layers:defaults',
			function ($defaults) {
				$defaults[] = [
					'id' => 'product_swatches',
					'enabled' => false
				];

				return $defaults;
			}
		);

		add_filter(
			'blocksy_woo_card_options_layers:extra',
			[$this, 'add_layer_options']
		);

		add_action('blocksy:woocommerce:product-card:custom:layer', [
			$this,
			'render_layer',
		]);

		add_filter(
			'woocommerce_dropdown_variation_attribute_options_html',
			function ($html, $args) {
				/**
				 * Filters whether single product variation swatches should render.
				 *
				 * @since 2.0.12
				 *
				 * @param bool $has_single_product_swatches Whether single product swatches should render. Default true.
				 */
				$has_single_product_swatches = apply_filters(
					'blocksy:pro:woocommerce-extra:swatches:has-single-product-swatches',
					true
				);

				if (! $has_single_product_swatches) {
					return $html;
				}

				global $blocksy_rendering_woo_card;

				if ($blocksy_rendering_woo_card) {
					return $html;
				}

				$conf = new SwatchesConfig();
				$type = $conf->get_attribute_type(
					$args['attribute'],
					[
						'product' => isset($args['product']) ? $args['product'] : null
					]
				);

				$attr = [
					'class' => 'ct-variation-swatches',
					'data-swatches-type' => $type
				];

				if ($type === 'color') {
					$attr['data-swatches-shape'] = blocksy_companion_theme_functions()->blocksy_get_theme_mod('color_swatch_shape', 'round');
				}

				if ($type === 'image') {
					$attr['data-swatches-shape'] = blocksy_companion_theme_functions()->blocksy_get_theme_mod('image_swatch_shape', 'round');
				}

				if ($type === 'button') {
					$attr['data-swatches-shape'] = blocksy_companion_theme_functions()->blocksy_get_theme_mod('button_swatch_shape', 'round');
				}

				if ($type === 'mixed') {
					$attr['data-swatches-shape'] = blocksy_companion_theme_functions()->blocksy_get_theme_mod('mixed_swatch_shape', 'round');
				}

				$custom_swatch_html = '';
				$renderer = new SwatchesFrontend();

				if (
					blocksy_companion_theme_functions()->blocksy_get_theme_mod('limit_number_of_swatches', 'no') === 'yes'
					&&
					! empty(blocksy_companion_theme_functions()->blocksy_get_theme_mod('single_limit_number_of_swatches_number', ''))
				) {
					$args['limit'] = blocksy_companion_theme_functions()->blocksy_get_theme_mod('single_limit_number_of_swatches_number', '');
				}

				if ($type !== 'select') {
					$custom_swatch_html = $renderer->get_swatch_html($args);
				}

				return blocksy_html_tag('div', $attr, $html . $custom_swatch_html);
			},
			999, 2
		);

		add_action('woocommerce_before_add_to_cart_form', function() {
			add_filter(
				'woocommerce_attribute_label',
				[$this, 'preselected_default_attribute_name'],
				10,
				3
			);
		});

		add_action('woocommerce_after_add_to_cart_form', function() {
			remove_filter(
				'woocommerce_attribute_label',
				[$this, 'preselected_default_attribute_name'],
				10
			);
		});

		add_filter('blocksy_woo_single_options:after_layers', function ($opts) {
			return [
				$opts,

				'variations_swatches_display_type' => [
					'label' => __('Display Variations Inline', 'blocksy-companion'),
					'type' => 'ct-switch',
					'value' => 'no',
					'sync' => [
						'id' => 'woo_single_layout_skip'
					],
				],
			];
		});

		add_filter(
			'product_attributes_type_selector',
			function ($columns) {
				return array_merge(
					$columns,
					$this->get_attribute_types()
				);
			},
		);

		add_action(
			'woocommerce_product_option_terms',
			function($attribute_taxonomy, $i, $attribute) {
				if ('select' !== $attribute_taxonomy->attribute_type && in_array($attribute_taxonomy->attribute_type, array_keys($this->get_attribute_types()))) {
					$name = sprintf('attribute_values[%s][]', esc_attr($i));
					?>
					<select multiple="multiple" data-placeholder="<?php esc_attr_e('Select terms', 'blocksy-companion'); ?>" class="multiselect attribute_values wc-enhanced-select" name="<?php echo esc_attr($name) ?>">
						<?php
							$args = array(
								'orderby'    => ! empty($attribute_taxonomy->attribute_orderby) ? $attribute_taxonomy->attribute_orderby : 'name',
								'hide_empty' => 0,
							);

							$args['taxonomy'] = $attribute->get_taxonomy();
							$all_terms = get_terms(apply_filters('woocommerce_product_attribute_terms', $args));
							if ($all_terms) {
								foreach ($all_terms as $term) {
									$options = $attribute->get_options();
									$options = ! empty($options) ? $options : array();
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo '<option value="' . esc_attr($term->term_id) . '"' . wc_selected($term->term_id, $options) . '>' . esc_html(apply_filters('woocommerce_product_attribute_term_name', $term->name, $term)) . '</option>';
								}
							}
						?>
					</select>
					<button class="button plus select_all_attributes"><?php esc_html_e('Select all', 'blocksy-companion'); ?></button>
					<button class="button minus select_no_attributes"><?php esc_html_e('Select none', 'blocksy-companion'); ?></button>
					<button class="button fr plus add_new_attribute"><?php esc_html_e('Add new', 'blocksy-companion'); ?></button>

					<?php
				}
			},
			10,
			3
		);

		add_action('woocommerce_product_data_tabs', [$this, 'add_product_tab']);
		add_action('woocommerce_process_product_meta', [$this, 'save_swatches_for_product']);
		add_action('woocommerce_product_data_panels', function() {
			$options = blocksy_companion_get_variables_from_file(
				dirname(__FILE__) . '/woo-tab-options.php',
				[
					'tooltip_options' => [],
					'mixed_options' => [],
					'color_options' => [],
					'image_options' => [],
					'button_options' => [],
					'inherit_options' => [],
				],
				[
					'option_design' => true
				]
			);

			global $post;
			$current_product_id = $post->ID;

			$meta = get_post_meta($current_product_id, '_ct-woo-attributes-list', true);

			blocksy_html_tag_e(
				'div',
				[
					'id' => 'ct_product_variation_swatches',
					'class' => 'panel woocommerce_options_panel hidden'
				],
				blocksy_html_tag(
					'div',
					[
						'class' => 'options_group'
					],
					blocksy_html_tag(
						'p',
						[
							'class' => 'form-field'
						],
						blocksy_html_tag(
							'label',
							[],
							__('Swatches', 'blocksy-companion')
						) .
						blocksy_html_tag(
							'input',
							[
								'id' => 'ct-woo-attributes-list',
								'name' => 'ct-woo-attributes-list',
								'class' => 'ct-woo-attributes-list',
								'data-options' => htmlspecialchars(
									wp_json_encode($options)
								),
								'value' => $meta
							],
							''
						)
					)
				)
			);
		});

		add_action('wp_ajax_blocksy_save_attributes_swatches', function () {
			if (! current_user_can('edit_posts')) {
				wp_send_json_error();
			}

			if (
				empty($_POST['woocommerce_meta_nonce'])
				||
				! wp_verify_nonce(sanitize_key($_POST['woocommerce_meta_nonce']), 'woocommerce_save_data')
			) {
				wp_send_json_error();
			}

			$url = wp_get_referer();
			$queryString = wp_parse_url($url, PHP_URL_QUERY);

			parse_str($queryString, $params);

			$postId = isset($params['post']) ? $params['post'] : null;

			if (!$postId) {
				wp_send_json_error();
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$attributes_config = isset($_POST['ct-woo-attributes-list']) ? wp_unslash($_POST['ct-woo-attributes-list']) : [];

			if (! $attributes_config) {
				delete_post_meta($postId, '_ct-woo-attributes-list');

				wp_send_json_success([
					'message' => __('Swatches removed', 'blocksy-companion')
				]);
				return;
			}

			update_post_meta($postId, '_ct-woo-attributes-list', $attributes_config);
			wp_send_json_success([
				'message' => __('Swatches saved', 'blocksy-companion'),
				'$attributes_config' => $attributes_config
			]);
		});
	}

	private function get_variations_form_attributes($product) {
		$attributes = [
			'data-variations-out-of-stock' => blocksy_companion_theme_functions()->blocksy_get_theme_mod(
				'out_of_stock_swatch_type',
				'faded'
			)
		];

		if (! $product) {
			return $attributes;
		}

		$is_simple_product = blocksy_companion_get_ext(
			'woocommerce-extra'
		)->utils->is_simple_product($product);

		if (
			blocksy_companion_theme_functions()->blocksy_manager()->screen->is_product()
			&&
			blocksy_companion_theme_functions()->blocksy_get_theme_mod('has_swatches_url', 'no') === 'yes'
			&&
			! $is_simple_product['value']
		) {
			$attributes['data-variations-url'] = 'yes';
		}

		global $blocksy_rendering_woo_card;

		if (
			! $blocksy_rendering_woo_card
			&&
			(int) get_queried_object_id() === (int) $product->get_id()
			&&
			blocksy_companion_theme_functions()->blocksy_has_product_specific_layer('product_add_to_cart')
			&&
			blocksy_companion_theme_functions()->blocksy_get_theme_mod(
				'variations_swatches_display_type',
				'no'
			) === 'yes'
		) {
			$attributes['data-variations-layout'] = 'inline';
		}

		return $attributes;
	}

	public function preselected_default_attribute_name($label, $name) {
		if ($this->is_computing_label) {
			return $label;
		}

		global $product;

		if (
			! $product
			||
			! $product instanceof \WC_Product
			||
			$product->get_type() !== 'variable'
		) {
			return $label;
		}

		$this->is_computing_label = true;

		$default_attributes = $product->get_default_attributes();
		$this->is_computing_label = false;

		$conf = new SwatchesConfig();
		$type = $conf->get_attribute_type($name);

		if ($type === 'select') {
			return $label;
		}

		$maybe_value = '';
		$maybeKey = sanitize_title('attribute_' . $name);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (!empty($_GET[$maybeKey])) {
			if (taxonomy_exists($name)) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$term = get_term_by('slug', sanitize_text_field(wp_unslash($_GET[$maybeKey])), $name);

				if ($term) {
					$maybe_value = $term->name;
				}
			} else {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$maybe_value = sanitize_text_field(wp_unslash($_GET[$maybeKey]));
			}
		} elseif (isset($default_attributes[$name])) {
			if (taxonomy_exists(urldecode($name))) {
				$term = get_term_by('slug', $default_attributes[$name], $name);

				if ($term) {
					$maybe_value = $term->name;
				}
			} else {
				$maybe_value = $default_attributes[$name];
			}
		} else {
			$attributes = $product->get_attributes();

			foreach ($attributes as $attribute) {
				$maybe_custom_attribute = $attribute->get_name();

				if ($maybe_custom_attribute === $name) {
					if (
						$maybe_custom_attribute
						&&
						isset($default_attributes[sanitize_title($name)])
					) {
						$maybe_value = $default_attributes[sanitize_title($name)];
					}

					break;
				}
			}
		}

		if (!empty($maybe_value)) {
			return $label . '<span>: ' . urldecode($maybe_value) . '</span>';
		}

		return $label;
	}

	public function save_swatches_for_product($post_id) {
		if (
			empty($_POST['woocommerce_meta_nonce'])
			||
			! wp_verify_nonce(
				sanitize_key($_POST['woocommerce_meta_nonce']),
				'woocommerce_save_data'
			)
		) {
			return;
		}

		$attributes_config = [];

		if (isset($_POST['ct-woo-attributes-list'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$attributes_config = wp_unslash($_POST['ct-woo-attributes-list']);
		}

		if (! $attributes_config) {
			delete_post_meta($post_id, '_ct-woo-attributes-list');
			return;
		}

		update_post_meta($post_id, '_ct-woo-attributes-list', $attributes_config);
	}

	private function get_attribute_types() {
		return [
			'button' => __('Button', 'blocksy-companion'),
			'color' => __('Color', 'blocksy-companion'),
			'image' => __('Image', 'blocksy-companion'),
			'mixed' => __('Mixed', 'blocksy-companion')
		];
	}

	public function add_product_tab($tabs) {
		$tabs['ct-swatches'] = array(
			'label' => __('Swatches', 'blocksy-companion'),
			'target' => 'ct_product_variation_swatches',
			'class' => [
				'hide_if_simple',
				'hide_if_virtual',
				'hide_if_grouped',
				'hide_if_external'
			],
			'priority' => 60,
		);

		return $tabs;
	}

	public function add_layer_options($opt) {
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		$tax_choices = [];
		$taxonomies_to_show = [
			'ct_custom_attributes' => [
				'label' => __('Custom Attributes', 'blocksy-companion'),
			]
		];

		foreach ($attribute_taxonomies as $tax) {
			$tax_choices[$tax->attribute_name] = $tax->attribute_label;

			$taxonomies_to_show[$tax->attribute_name] = [
				'label' => $tax->attribute_label,
			];
		}

		return array_merge(
			$opt,
			[
				'product_swatches' => [
					'label' => __('Swatches', 'blocksy-companion'),
					'options' => [
						'product_attributes_source' => [
							'type' => 'ct-radio',
							'label' => false,
							'value' => 'all',
							'design' => 'block',
							'disableRevertButton' => true,
							'choices' => [
								'all' => __('All', 'blocksy-companion'),
								'custom' => __('Custom', 'blocksy-companion'),
							],
						],

						blocksy_rand_md5() => [
							'type' => 'ct-condition',
							'condition' => ['product_attributes_source' => 'custom'],
							'options' => [
								'taxonomies_to_show' => [
									'label' => false,
									'type' => 'ct-layers',
									'manageable' => true,
									'value' => [],
									'settings' => $taxonomies_to_show
								]
							]
						],

						'spacing' => [
							'label' => __('Bottom Spacing', 'blocksy-companion'),
							'type' => 'ct-slider',
							'min' => 0,
							'max' => 100,
							'value' => 10,
							'responsive' => true,
							'sync' => [
								'id' => 'woo_card_layout_skip'
							]
						],
					]
				],
			]
		);
	}

	public function render_layer($layer) {
		if ($layer['id'] !== 'product_swatches') {
			return;
		}

		global $product;
		$renderer = new SwatchesFrontend();

		if ($product->get_type() !== 'variable') {
			return '';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $renderer->render_variation_swatches(['product' => $product, 'layer' => $layer]);
	}
}
