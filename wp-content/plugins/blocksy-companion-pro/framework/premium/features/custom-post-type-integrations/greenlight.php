<?php

namespace Blocksy\CustomPostType\Integrations;

if (! defined('ABSPATH')) {
	exit;
}

class GreenLight extends \Blocksy\CustomPostTypeRenderer {
	public function get_content($args = []) {
		return \Blocksy\CustomPostTypeRenderer::NOT_IMPLEMENTED;
	}

	public function pre_output() {
		wp_enqueue_style('wp-block-library');

		add_action(
			'wp_enqueue_scripts',
			function () {
				$this->enqueue_post_css();
			}
		);
	}

	private function enqueue_post_css() {
		$gspb_css_content = get_post_meta(
			$this->id,
			'_gspb_post_css',
			true
		);

		if (empty($gspb_css_content)) {
			return;
		}

		$final_css = gspb_get_final_css($gspb_css_content);

		$css_id = 'greenlight-post-css-' . $this->id;

		// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_style($css_id, false);
		// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion

		wp_enqueue_style($css_id);
		wp_add_inline_style($css_id, $final_css);
	}

	public function compute_inline_styles() {
		return get_post_meta(
			$this->id,
			'_gspb_post_css',
			true
		);
	}
}
