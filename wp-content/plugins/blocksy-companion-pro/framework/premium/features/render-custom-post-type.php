<?php

namespace Blocksy;

if (! defined('ABSPATH')) {
	exit;
}

class CustomPostTypeRenderer {
	private static $posts_with_pre_output = [];

	private static $current_rendered_post_id = null;

	const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';

	protected $id = '';

	public static function get_current_rendered_post_id() {
		return self::$current_rendered_post_id;
	}

	public function __construct($id) {
		$this->id = $id;

		self::$current_rendered_post_id = $id;
	}

	public function __destruct() {
		self::$current_rendered_post_id = null;
	}

	public function get_integrations() {
		$implemented_integrations = [
			[
				'name' => 'CoreBlocks',
				'check' => function () {
					return true;
				}
			],

			[
				'name' => 'Elementor',
				'check' => function () {
					return class_exists('Elementor\Plugin');
				}
			],

			[
				'name' => 'PiotnetForms',
				'check' => function () {
					return function_exists('piotnetforms_shortcode');
				}
			],

			[
				'name' => 'ZionBuilder',
				'check' => function () {
					return class_exists('\ZionBuilder\Plugin');
				}
			],

			[
				'name' => 'Brizy',
				'check' => function () {
					return class_exists('Brizy_Editor');
				}
			],

			[
				'name' => 'GenerateBlocks',
				'check' => function () {
					return function_exists('generateblocks_get_parsed_content');
				}
			],

			[
				'name' => 'Qubely',
				'check' => function () {
					return class_exists('QUBELY_MAIN');
				}
			],

			[
				'name' => 'Spectra',
				'check' => function () {
					return class_exists('UAGB_Post_Assets');
				}
			],

			[
				'name' => 'UltimateBlocks',
				'check' => function () {
					return function_exists('ub_load_assets');
				}
			],

			[
				'name' => 'Gutenslider',
				'check' => function () {
					return class_exists('Gutenslider');
				}
			],

			[
				'name' => 'Cwicly',
				'check' => function () {
					return class_exists('Cwicly_Plugin_Updater');
				}
			],

			[
				'name' => 'UltimatePost',
				'check' => function () {
					return function_exists('ultimate_post');
				}
			],

			[
				'name' => 'UltimateProduct',
				'check' => function () {
					return function_exists('wopb_function');
				}
			],

			[
				'name' => 'Gutentor',
				'check' => function () {
					return function_exists('gutentor_hooks');
				}
			],

			[
				'name' => 'GhostKit',
				'check' => function () {
					return class_exists('GhostKit_Parse_Blocks');
				}
			],

			[
				'name' => 'JetStyleManager',
				'check' => function () {
					return class_exists('\JET_SM\Gutenberg\Style_Manager');
				}
			],

			[
				'name' => 'KadenceBlocks',
				'check' => function () {
					return class_exists('Kadence_Blocks_Frontend');
				}
			],

			[
				'name' => 'CountdownBlock',
				'check' => function () {
					return function_exists('create_block_countdown_block_init');
				}
			],

			[
				'name' => 'AffiliateBooster',
				'check' => function () {
					return function_exists('affiliate_booster_gutenberg_init');
				}
			],

			[
				'name' => 'FluentForms',
				'check' => function () {
					return defined('FLUENTFORM');
				}
			],

			[
				'name' => 'GreenShift',
				'check' => function () {
					return function_exists('gspb_GreenShift_plugin_init');
				}
			],

			[
				'name' => 'GreenLight',
				'check' => function () {
					return function_exists('gspb_GreenLight_plugin_init');
				}
			],

			[
				'name' => 'Stackable',
				'check' => function () {
					return defined('STACKABLE_VERSION');
				}
			],

			[
				'name' => 'MaxiBlocks',
				'check' => function () {
					return defined('MAXI_PLUGIN_VERSION');
				}
			],

			[
				'name' => 'Turnstile',
				'check' => function () {
					return function_exists('cfturnstile_field_show');
				}
			],

			[
				'name' => 'NectarBlocks',
				'check' => function () {
					return defined('NECTAR_BLOCKS_VERSION');
				}
			]
		];

		$result = [];

		foreach ($implemented_integrations as $integration) {
			if ($integration['check']()) {
				$class_name = __NAMESPACE__ . '\\CustomPostType\\Integrations\\' . $integration['name'];
				$integration['object'] = new $class_name($this->id);
				$result[] = $integration;
			}
		}

		return $result;
	}

	public function get_content($args = []) {
		return apply_filters(
			'blocksy:pro:custom-post-type:output-content',
			$this->get_content_unfiltered($args),
			$this->id
		);
	}

	public function get_content_unfiltered($args = []) {
		$args = wp_parse_args($args, [
			'use_integrations' => true
		]);

		$id = $this->id;

		$hook_post = get_post($id);

		$atts = blocksy_get_post_options($id);

		if (! $hook_post) {
			return '';
		}

		$has_post_blocks = has_blocks($hook_post);

		if ($args['use_integrations']) {
			$integrations = $this->get_integrations();

			foreach ($integrations as $integration) {
				$maybe_content = $integration['object']->get_content($args);

				if ($maybe_content !== self::NOT_IMPLEMENTED) {
					return $maybe_content;
				}
			}
		}

		$result = '';

		if ($has_post_blocks) {
			$blocks = $this->parse_blocks_with_code_editor_mark($hook_post->post_content);

			foreach ($blocks as $block) {
				$block['ct_hook_block'] = true;
				$result .= render_block($block);
			}
		} else {
			$result = wpautop($hook_post->post_content);
		}

		global $wp_embed;

		if ($wp_embed) {
			$result = $wp_embed->autoembed($result);
		}

		$result = wp_filter_content_tags(do_shortcode(shortcode_unautop($result)));

		return $result;
	}

	// Parse blocks and mark code-editor blocks as trusted.
	// The filter hooks for exactly one parse_blocks() call and
	// unhooks itself, so blocks parsed later (e.g. contributor
	// content via core/post-content) are never marked.
	private function parse_blocks_with_code_editor_mark($content) {
		add_filter(
			'blocksy:block-parser:result',
			[$this, 'mark_code_editor_blocks_and_unhook']
		);

		return parse_blocks($content);
	}

	public function mark_code_editor_blocks_and_unhook($blocks) {
		remove_filter(
			'blocksy:block-parser:result',
			[$this, 'mark_code_editor_blocks_and_unhook']
		);

		return $this->mark_code_editor_blocks($blocks);
	}

	private function mark_code_editor_blocks($blocks) {
		foreach ($blocks as &$block) {
			if ($block['blockName'] === 'blocksy-companion-pro/code-editor') {
				$block['ct_allow_code_editor'] = true;
			}

			if (! empty($block['innerBlocks'])) {
				$block['innerBlocks'] = $this->mark_code_editor_blocks(
					$block['innerBlocks']
				);
			}
		}

		return $blocks;
	}

	public function pre_output() {
		$id = $this->id;

		do_action('blocksy:pro:dynamic-post-type:pre-output', $id);

		$post = get_post($id);

		if (
			(
				has_block('blocksy/query', $post->post_content)
				||
				has_block('blocksy/tax-query', $post->post_content)
			)
			&&
			strpos(
				$post->post_content,
				'"has_slideshow":"yes"'
			) !== false
		) {
			wp_enqueue_style('ct-flexy-styles');
		}

		$integrations = $this->get_integrations();

		foreach ($integrations as $integration) {
			$integration['object']->pre_output();
		}
	}

	public function get_inline_styles() {
		$integrations = $this->get_integrations();

		$inline_styles = '';

		foreach ($integrations as $integration) {
			if (method_exists($integration['object'], 'compute_inline_styles')) {
				$inline_styles .= $integration['object']->compute_inline_styles();
			}
		}

		return str_replace('body.gspb-bodyfront', '', $inline_styles);
	}

	final public function should_pre_output() {
		// blocksy_print(self::$posts_with_pre_output);

		return true;
	}
}
