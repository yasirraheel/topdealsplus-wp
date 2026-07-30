<?php

namespace Blocksy\CustomPostType\Integrations;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * WordPress registers a separate per-block style handle for each core block
 * (e.g. `wp-block-video`) at `init`, but only when is_admin() is false. A popup
 * with "Load content with AJAX" is rendered over admin-ajax, where is_admin() is
 * true, so those handles are never registered and the block types' style_handles
 * stay empty — the popup's core blocks (video, etc.) then render unstyled (a
 * stretched video being the visible symptom).
 *
 * On the frontend WordPress handles this itself, so this integration only acts in
 * the AJAX render: it enqueues each core block's own style file straight from
 * wp-includes/blocks, letting the EnqueuedAssetsCollector ship it. It deliberately
 * does not touch is_admin() — flipping it for the whole request breaks admin_init
 * consumers such as WooCommerce.
 */
class CoreBlocks extends \Blocksy\CustomPostTypeRenderer {
	public function get_content($args = []) {
		return \Blocksy\CustomPostTypeRenderer::NOT_IMPLEMENTED;
	}

	public function pre_output() {
		if (! wp_doing_ajax()) {
			return;
		}

		$post = get_post($this->id);

		if (! $post || ! has_blocks($post)) {
			return;
		}

		$this->enqueue_core_block_styles(parse_blocks($post->post_content));
	}

	private function enqueue_core_block_styles($blocks) {
		$suffix = wp_scripts_get_suffix();

		foreach ($blocks as $block) {
			if (! empty($block['innerBlocks'])) {
				$this->enqueue_core_block_styles($block['innerBlocks']);
			}

			if (empty($block['blockName'])) {
				continue;
			}

			if (strpos($block['blockName'], 'core/') !== 0) {
				continue;
			}

			$slug = substr($block['blockName'], strlen('core/'));
			$relative = 'blocks/' . $slug . '/style' . $suffix . '.css';

			if (! file_exists(ABSPATH . WPINC . '/' . $relative)) {
				continue;
			}

			$handle = generate_block_asset_handle($block['blockName'], 'style');

			if (! wp_style_is($handle, 'registered')) {
				wp_register_style(
					$handle,
					includes_url($relative),
					[],
					wp_get_wp_version()
				);
			}

			wp_enqueue_style($handle);
		}
	}
}
