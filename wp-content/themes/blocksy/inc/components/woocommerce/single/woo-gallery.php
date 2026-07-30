<?php

add_action(
	'woocommerce_before_template_part',
	function ($template_name, $template_path, $located, $args) {
		if ($template_name !== 'single-product/product-image.php') {
			return;
		}

		wp_enqueue_style('ct-flexy-styles');

		if (blocksy_woocommerce_has_flexy_view()) {
			echo blocksy_render_view(dirname(__FILE__) . '/woo-gallery-template.php');

			ob_start();
			return;
		}

		ob_start();
	},
	4, 4
);

add_action(
	'woocommerce_after_template_part',
	function ($template_name, $template_path, $located, $args) {
		if ($template_name !== 'single-product/product-image.php') {
			return;
		}

		$product_gallery = ob_get_clean();

		if (blocksy_woocommerce_has_flexy_view()) {
			return;
		}

		global $product;

		if (
			! blocksy_manager()->screen->is_product()
			||
			! $product
		) {
			echo $product_gallery;
			return;
		}

		/**
		 * Filters the attributes applied to the default (non-flexy) single
		 * product gallery wrapper (`.woocommerce-product-gallery`).
		 *
		 * Integrations can return an empty array to opt a product out
		 * entirely — e.g. the Custom Product Boxes integration for
		 * `wdm_bundle_product`, which renders its own gallery.
		 *
		 * @since 2.1.50
		 *
		 * @param array       $attr    Map of attribute name => value applied to
		 *                             the gallery wrapper. Default
		 *                             `['data-gallery' => 'default']`.
		 * @param \WC_Product $product The product being rendered.
		 */
		$gallery_attr = apply_filters(
			'blocksy:woocommerce:default-gallery:attr',
			['data-gallery' => 'default'],
			$product
		);

		if (empty($gallery_attr)) {
			echo $product_gallery;
			return;
		}

		$gallery_processor = new \WP_HTML_Tag_Processor($product_gallery);

		if ($gallery_processor->next_tag([
			'class_name' => 'woocommerce-product-gallery'
		])) {
			foreach ($gallery_attr as $attr_name => $attr_value) {
				$gallery_processor->set_attribute($attr_name, $attr_value);
			}
		}

		echo $gallery_processor->get_updated_html();
	},
	4, 4
);

add_filter(
	'blocksy:woocommerce:single-product:post-class',
	'blocksy_woo_single_post_class'
);

function blocksy_woo_single_post_class($classes) {
	// Not redundant with the filter's own scoping — the filter also fires
	// for AJAX renders (quick view), where these gallery classes must not
	// be applied.
	if (! blocksy_manager()->screen->is_product()) {
		return $classes;
	}

	$product_view_type = blocksy_get_product_view_type();

	if (
		$product_view_type === 'default-gallery'
		||
		$product_view_type === 'stacked-gallery'
	) {
		if (blocksy_get_theme_mod('has_product_sticky_gallery', 'no') === 'yes') {
			$classes[] = 'sticky-gallery';
		}

		if (blocksy_get_theme_mod('has_product_sticky_summary', 'no') === 'yes') {
			$classes[] = 'sticky-summary';
		}
	}

	return $classes;
}

function blocksy_get_product_view_type() {
	/**
	 * Filters the view type used for the main single product gallery layout.
	 *
	 * @since 2.0.1
	 *
	 * @param string $view_type Product gallery view type. Default 'default-gallery'.
	 */
	return apply_filters(
		'blocksy:woocommerce:product-single:view-type',
		'default-gallery'
	);
}

