<?php

namespace Blocksy\CustomPostType\Integrations;

if (! defined('ABSPATH')) {
	exit;
}

class GreenShift extends \Blocksy\CustomPostTypeRenderer {
	public function get_content($args = []) {
		return \Blocksy\CustomPostTypeRenderer::NOT_IMPLEMENTED;
	}

	public function pre_output() {
		if (wp_doing_ajax()) {
			// In AJAX context wp_enqueue_scripts won't fire.
			// Enqueue directly so collect_new_assets captures it.
			// Also patch is_admin() so GreenShift's render_block
			// filter enqueues frontend scripts.
			$GLOBALS['current_screen'] = new class {
				public function in_admin() { return false; }
			};

			$this->enqueue_post_css();
			return;
		}

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

		$css_id = 'greenshift-post-css-' . $this->id;

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
