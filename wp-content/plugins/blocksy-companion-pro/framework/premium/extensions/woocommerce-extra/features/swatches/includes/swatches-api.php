<?php

namespace Blocksy\Extensions\WoocommerceExtra;

if (! defined('ABSPATH')) {
	exit;
}

class SwatchesApi {
	public function __construct() {
		$action = 'blocksy_swatches_get_product_out_of_stock_variations';

		add_action('wp_ajax_' . $action, [$this, 'handle_request']);
		add_action('wp_ajax_nopriv_' . $action, [$this, 'handle_request']);
	}

	public function handle_request() {
		$body = json_decode(file_get_contents('php://input'), true);

		if (! isset($body['product_id'])) {
			wp_send_json_error();
		}

		$product = wc_get_product($body['product_id']);

		$children = $product->get_children();

		global $wpdb;

		$all_attributes = $product->get_variation_attributes();

		$needed_meta_keys = [
			'_stock_status',
		];

		foreach ($all_attributes as $key => $terms) {
			$needed_meta_keys[] = blocksy_companion_get_ext('woocommerce-extra')
				->utils
				->format_attribute_slug($key);
		}

		$meta_placeholders = implode(',', array_fill(0, count($needed_meta_keys), '%s'));
		$children_placeholders = implode(',', array_fill(0, count($children), '%d'));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$variations_status_rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"SELECT * FROM $wpdb->postmeta WHERE meta_key IN ($meta_placeholders) AND post_id IN ($children_placeholders)",
				...array_merge($needed_meta_keys, array_map('intval', $children))
			)
		);

		$variations_data = [];

		foreach ($variations_status_rows as $post) {
			if (! isset($variations_data[$post->post_id])) {
				$variations_data[$post->post_id] = [
					'post_id' => $post->post_id
				];
			}

			if (strpos($post->meta_key, 'attribute_') !== false) {
				if (! isset($variations_data[$post->post_id]['attributes'])) {
					$variations_data[$post->post_id]['attributes'] = [];
				}

				$variations_data[$post->post_id]['attributes'][$post->meta_key] = $post->meta_value;

				continue;
			}

			if ($post->meta_key === '_stock_status') {
				$is_in_stock = true;

				if ($post->meta_value === 'outofstock') {
					$is_in_stock = false;
				}

				$variations_data[$post->post_id]['is_in_stock'] = $is_in_stock;

				continue;
			}

			$variations_data[$post->post_id][$post->meta_key] = $post->meta_value;
		}

		wp_send_json_success([
			'variations_data' => $variations_data
		]);
	}
}
