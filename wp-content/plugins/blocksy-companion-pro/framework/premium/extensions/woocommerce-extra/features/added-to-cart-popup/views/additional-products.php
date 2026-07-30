<?php

if (! defined('ABSPATH')) {
	exit;
}

if (empty($cart_items)) {
	return;
}

$current_cart_item = $cart_items[0];
array_shift($cart_items);

$additional_products = [];

foreach (wc()->cart->get_cart() as $cart_item) {
	if (
		! isset($cart_item['associated_parent'])
		||
		$cart_item['associated_parent'] !== $current_cart_item['cart_item']['key']
	) {
		continue;
	}

	$additional_products[] = [
		'cart_item' => $cart_item,
		'cart_id' => $cart_item['key']
	];
}

$cart_items = array_merge(
	$cart_items,
	array_filter($additional_products)
);

if (empty($cart_items)) {
	return;
}

$additional_products = '';

$cart_items_html = [];

foreach ($cart_items as $cart_item) {
	$product = wc_get_product($cart_item['cart_item']['product_id']);

	if (! $product) {
		continue;
	}

	$cart_items_html[] = blocksy_html_tag(
		'li',
		[],
		esc_html($product->get_name()) .
		blocksy_html_tag(
			'span',
			[],
			$cart_item['cart_item']['data']->get_price_html()
		)
	);
}

blocksy_html_tag_e(
	'ul',
	[
		'class' => 'ct-product-bundles'
	],
	join('', $cart_items_html)
);
