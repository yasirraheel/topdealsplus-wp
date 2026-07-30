<?php

namespace Blocksy;

if (! defined('ABSPATH')) {
	exit;
}

class ContentBlocksRenderer extends CustomPostTypeRenderer {
	public function get_content($args = []) {
		$id = $this->id;

		$template_type = get_post_meta($id, 'template_type', true);

		return parent::get_content([
			'switch_global_post' => (
				$template_type === 'single'
				||
				$template_type === 'archive'
			)
		]);
	}

	// $args is an options bag. `dynamic_styles_descriptor`, when given, carries
	// the CSS injectors (`css`/`tablet_css`/`mobile_css` + `context`) for an
	// out-of-band render (e.g. the AJAX popup collect path). In that case the
	// dynamic CSS is generated inline against those injectors, because the page's
	// global dynamic-CSS pass — which would normally fire
	// `blocksy:global-dynamic-css:enqueue:inline` — never runs. Left null (a
	// normal page render) the dynamic styles hook onto that pass as usual.
	public function pre_output($args = []) {
		if (! $this->should_pre_output()) {
			return;
		}

		$args = wp_parse_args($args, [
			'dynamic_styles_descriptor' => null
		]);

		$dynamic_styles_descriptor = $args['dynamic_styles_descriptor'];

		$id = $this->id;

		/**
		 * Fires before a content block's assets and dynamic CSS are prepared.
		 *
		 * @since 1.8.0
		 *
		 * @param int $id The content block post ID.
		 */
		do_action('blocksy:pro:content-blocks:pre-output', $id);

		$template_type = get_post_meta($id, 'template_type', true);

		$enqueue_dynamic_styles = function ($path) use ($id, $dynamic_styles_descriptor) {
			$generate = function ($hook_args) use ($id, $path) {
				blocksy_companion_theme_functions()->blocksy_theme_get_dynamic_styles(array_merge([
					'path' => $path,
					'chunk' => 'hooks',
					'id' => $id,
					'atts' => blocksy_get_post_options($id)
				], $hook_args));
			};

			if ($dynamic_styles_descriptor !== null) {
				$generate($dynamic_styles_descriptor);
				return;
			}

			add_action('blocksy:global-dynamic-css:enqueue:inline', $generate, 10, 3);
		};

		if ($template_type === 'popup') {
			$enqueue_dynamic_styles(dirname(__FILE__) . '/popup-dynamic-styles.php');
		}

		$enqueue_dynamic_styles(dirname(__FILE__) . '/dynamic-styles.php');

		return parent::pre_output();
	}
}

