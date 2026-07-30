<?php

if (! defined('ABSPATH')) {
	exit;
}

global $product;

global $post;

if (is_string($product)) {
	$product = wc_get_product();
}

if (! $product && $post) {
	$product = wc_get_product($post->ID);
}

$image_output = '';

$image_visibility = blocksy_visibility_classes(
	blocksy_companion_theme_functions()->blocksy_get_theme_mod('floatingBarImageVisibility', [
		'desktop' => true,
		'tablet' => true,
		'mobile' => true,
	])
);

if ($product && $product->get_image_id()) {
	$image_output = blocksy_media([
		'attachment_id' => $product->get_image_id(),
		'size' => 'woocommerce_gallery_thumbnail',
		'ratio' => '1/1',
		'lazyload' => false,
		'tag_name' => 'div',
		'class' => $image_visibility
	]);
}

$class = 'ct-floating-bar';

$is_ajax_add_to_cart = blocksy_companion_theme_functions()->blocksy_woo_has_ajax_add_to_cart();

$class .= ' ' . blocksy_visibility_classes(
	blocksy_companion_theme_functions()->blocksy_get_theme_mod('floatingBarVisibility', [
		'desktop' => true,
		'tablet' => true,
		'mobile' => true,
	])
);

$title_class = trim('product-title ' . blocksy_visibility_classes(
	blocksy_companion_theme_functions()->blocksy_get_theme_mod('floatingBarTitleVisibility', [
		'desktop' => true,
		'tablet' => true,
		'mobile' => true,
	])
));

$price_stock_class = trim('product-price ' . blocksy_visibility_classes(
	blocksy_companion_theme_functions()->blocksy_get_theme_mod('floatingBarPriceStockVisibility', [
		'desktop' => true,
		'tablet' => true,
		'mobile' => true,
	])
));

$simple = new \WC_Product_Simple($product->get_id());

$attr = [
	'class' => 'ct-floating-bar-actions',
	'data-dynamic-add-to-cart-data' => wc_esc_json(wp_json_encode([
		'variable' => [
			'text' => $product->add_to_cart_text(),
			'link' => $product->add_to_cart_url(),
			'price' => '<span class="price">' . $product->get_price_html() . '</span>',
		],

		'simple' => [
			'text' => $simple->add_to_cart_text(),
			'link' => $simple->add_to_cart_url()
		],

		'isCompleteVariationsForm' => true
	]))
];

if ($is_ajax_add_to_cart) {
	$attr['data-add-to-cart'] = 'ajax';
}

?>

<div
	class="<?php echo esc_attr(trim($class)) ?>"
	<?php
		if (
			is_customize_preview()
			&&
			function_exists('blocksy_attr_to_html')
		) {
			blocksy_attr_to_html_e([
				'data-shortcut' => 'border',
				'data-shortcut-location' => 'woocommerce_single:has_floating_bar'
			]);
		}
	?>
>
	<div class="ct-container">
		<section class="ct-floating-bar-content">
			<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $image_output
			?>
			<div class="ct-floating-bar-item-title">
				<?php the_title( '<div class="' . $title_class . '">', '</div>' ); ?>

				<div <?php blocksy_attr_to_html_e(['class' => $price_stock_class]); ?>>
					<?php
						// Output the price directly via get_price_html() instead of
						// woocommerce_template_single_price() so a child-theme override
						// of single-product/price.php (meant for the main product
						// summary) doesn't leak its markup into this compact bar.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo '<p class="price">' . $product->get_price_html() . '</p>';
					?>
					<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo wc_get_stock_html($product);
					?>
				</div>
			</div>
		</section>

		<section <?php blocksy_attr_to_html_e($attr); ?>>
			<?php
				if (
					$product->is_purchasable()
					&&
					! $product->is_sold_individually()
				) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo woocommerce_quantity_input([], $product, false);
				}

				/**
				 * Fires before the add-to-cart controls in the WooCommerce floating bar.
				 *
				 * @since 2.1.38
				 */
				do_action('blocksy:ext:woocommerce-extra:floating-bar:actions:before');
				woocommerce_template_loop_add_to_cart();
				/**
				 * Fires after the add-to-cart controls in the WooCommerce floating bar.
				 *
				 * @since 2.1.38
				 */
				do_action('blocksy:ext:woocommerce-extra:floating-bar:actions:after');
			?>
		</section>
	</div>
</div>
