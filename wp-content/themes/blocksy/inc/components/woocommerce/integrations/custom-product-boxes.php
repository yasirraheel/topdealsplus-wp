<?php

// https://woocommerce.com/products/custom-product-boxes/
// Bundle products render their own gallery — opt them out of ours.

add_filter(
	'blocksy:woocommerce:default-gallery:attr',
	function ($attr, $product) {
		if ($product && $product->get_type() === 'wdm_bundle_product') {
			return [];
		}

		return $attr;
	},
	10,
	2
);
