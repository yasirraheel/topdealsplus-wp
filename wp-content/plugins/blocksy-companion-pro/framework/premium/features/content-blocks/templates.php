<?php

namespace Blocksy;

if (! defined('ABSPATH')) {
	exit;
}

class ContentBlocksTemplates {
	private function get_matching_template_output($args) {
		$maybe_hook_id = blocksy_companion_get_content_block_that_matches($args);

		if (! $maybe_hook_id) {
			return null;
		}

		return blocksy_companion_render_content_block($maybe_hook_id);
	}

	private function get_template_override_notification($maybe_content_block, $type = 'single') {
		if (! $maybe_content_block) {
			return null;
		}

		// translators: %1$s is the opening link tag, %2$s is the closing link tag.
		$text = __('This single page is overrided by a custom template, to edit it please access %1$sthis page%2$s.', 'blocksy-companion');

		if ($type === 'archive') {
			// translators: %1$s is the opening link tag, %2$s is the closing link tag.
			$text = __('This archive page is overrided by a custom template, to edit it please access %1$sthis page%2$s.', 'blocksy-companion');
		}

		return [
			blocksy_rand_md5() => [
				'type' => 'ct-notification',
				'attr' => ['data-type' => 'background:white'],
				'text' => blocksy_safe_sprintf(
					$text,
					'<a href="' . get_edit_post_link($maybe_content_block) . '" target="_blank">',
					'</a>'
				)
			],
		];
	}

	public function __construct() {
		add_action('wp', function () {
			if (! is_singular() && ! is_single()) {
				return;
			}

			if (
				function_exists('blocksy_companion_get_content_block_that_matches')
				&&
				blocksy_companion_get_content_block_that_matches([
					'template_type' => 'single',
					'template_subtype' => 'canvas'
				])
			) {
				global $blocksy_template_output;
				$blocksy_template_output = true;
			}
		});

		// Allow somehow content blocks to manage filters
		add_filter('blocksy:minicart:empty:custom-output', function ($default_content) {
			ob_start();
			do_action( 'blocksy:pro:woo-extra:offcanvas:minicart:empty' );
			$content = ob_get_clean();

			if (! empty($content)) {
				return $content;
			}

			return $default_content;
		}, 10);

		add_filter('blocksy:posts-listing:container:custom-output', function ($output) {
			if (
				blocksy_companion_get_content_block_that_matches([
					'template_type' => 'archive'
				])
			) {
				$hook_id = blocksy_companion_get_content_block_that_matches([
					'template_type' => 'archive'
				]);

				$atts = blocksy_get_post_options($hook_id);

				return [
					'has_default_layout' => blocksy_akg(
						'has_template_default_layout',
						$atts,
						'yes'
					) === 'yes'
				];
			}

			return $output;
		});

		add_filter(
			'blocksy:posts-listing:canvas:custom-output',
			function ($output) {
				if ((
					is_home()
					||
					is_archive()
					||
					is_search()
				) && ! have_posts()) {
					$maybe_nothing_found_hook = blocksy_companion_get_content_block_that_matches([
						'template_type' => 'nothing_found'
					]);

					if ($maybe_nothing_found_hook) {
						return blocksy_companion_render_content_block($maybe_nothing_found_hook);
					}
				}

				$maybe_hook_id = null;

				if (
					blocksy_companion_get_content_block_that_matches([
						'template_type' => 'archive',
						'template_subtype' => 'canvas'
					])
				) {
					$maybe_hook_id = blocksy_companion_get_content_block_that_matches([
						'template_type' => 'archive',
						'template_subtype' => 'canvas'
					]);
				}

				if (! $maybe_hook_id) {
					return $output;
				}

				global $blocksy_template_output;
				$blocksy_template_output = true;

				return blocksy_companion_render_content_block($maybe_hook_id);
			}
		);

		add_filter(
			'blocksy:posts-listing:cards:custom-output',
			function ($output) {
				if (
					blocksy_companion_get_content_block_that_matches([
						'template_type' => 'archive'
					])
				) {
					$hook_id = blocksy_companion_get_content_block_that_matches([
						'template_type' => 'archive'
					]);

					$atts = blocksy_get_post_options($hook_id);

					$data = [
						'has_default_layout' => blocksy_akg(
							'has_template_default_layout',
							$atts,
							'yes'
						) === 'yes',
						'output' => blocksy_companion_render_content_block($hook_id)
					];

					$content_block_renderer = new ContentBlocksRenderer(
						$hook_id
					);

					$data['output'] .= '<style>' . $content_block_renderer->get_inline_styles() . '</style>';

					return $data;
				}

				return $output;
			}
		);

		add_filter(
			'blocksy:global:page_structure',
			function ($page_structure) {
				global $blocksy_template_output;

				if (! isset($blocksy_template_output) || ! $blocksy_template_output) {
					return $page_structure;
				}

				$maybe_matching_template = null;

				if (is_singular()) {
					$maybe_matching_template = blocksy_companion_get_content_block_that_matches([
						'template_type' => 'single',
						'template_subtype' => 'canvas'
					]);
				} else {
					$maybe_matching_template = blocksy_companion_get_content_block_that_matches([
						'template_type' => 'archive',
						'template_subtype' => 'canvas'
					]);
				}

				if ($maybe_matching_template) {
					$atts = blocksy_get_post_options($maybe_matching_template);
					return blocksy_akg('content_block_structure', $atts, 'type-4');
				}

				return $page_structure;
			}
		);

		add_filter('blocksy:single:canvas:custom-output', function ($output) {
			return $this->get_matching_template_output([
				'template_type' => 'single',
				'template_subtype' => 'canvas'
			]) ?: $output;
		});

		add_filter('blocksy:single:content:custom-output', function ($output) {
			return $this->get_matching_template_output([
				'template_type' => 'single',
				'template_subtype' => 'content'
			]) ?: $output;
		});

		add_filter('blocksy:404:custom-output', function ($output) {
			return $this->get_matching_template_output([
				'template_type' => '404',
				'match_conditions' => false
			]) ?: $output;
		});

		add_filter('blocksy:builder:header:custom-output', function ($output) {
			return $this->get_matching_template_output([
				'template_type' => 'header'
			]) ?: $output;
		});

		add_filter('blocksy:builder:footer:custom-output', function ($output) {
			return $this->get_matching_template_output([
				'template_type' => 'footer'
			]) ?: $output;
		});

		add_filter('blocksy:woocommerce:nothing-found:custom-output', function ($output) {
			return $this->get_matching_template_output([
				'template_type' => 'nothing_found',
				'match_conditions' => false
			]) ?: $output;
		});

		add_filter('blocksy:options:post', function ($options) {
			$maybe_content_block = blocksy_companion_get_content_block_that_matches([
				'template_type' => 'single',
				'template_subtype' => 'canvas',
				'match_conditions_strategy' => 'single_blog_post'
			]);

			return $this->get_template_override_notification($maybe_content_block, 'single') ?: $options;
		}, 10, 1);

		add_filter('blocksy:options:cpt:single', function ($options, $post_type) {
			$maybe_content_block = blocksy_companion_get_content_block_that_matches([
				'template_type' => 'single',
				'template_subtype' => 'canvas',
				'match_conditions_strategy' => $post_type . '_single'
			]);

			return $this->get_template_override_notification($maybe_content_block, 'single') ?: $options;
		}, 10, 2);

		add_filter('blocksy:options:blog', function ($options) {
			$maybe_content_block = blocksy_companion_get_content_block_that_matches([
				'template_type' => 'archive',
				'template_subtype' => 'canvas',
				'match_conditions_strategy' => 'blog'
			]);

			return $this->get_template_override_notification($maybe_content_block, 'archive') ?: $options;
		}, 10, 1);

		add_filter('blocksy:options:author-page', function ($options) {
			$maybe_content_block = blocksy_companion_get_content_block_that_matches([
				'template_type' => 'archive',
				'template_subtype' => 'canvas',
				'match_conditions_strategy' => 'author'
			]);

			return $this->get_template_override_notification($maybe_content_block, 'archive') ?: $options;
		}, 10, 1);

		add_filter('blocksy:options:page', function ($options) {
			$maybe_content_block = blocksy_companion_get_content_block_that_matches([
				'template_type' => 'single',
				'template_subtype' => 'canvas',
				'match_conditions_strategy' => 'single_page'
			]);

			return $this->get_template_override_notification($maybe_content_block, 'single') ?: $options;
		}, 10, 1);

		add_filter('blocksy:options:search-page', function ($options) {
			$maybe_content_block = blocksy_companion_get_content_block_that_matches([
				'template_type' => 'archive',
				'template_subtype' => 'canvas',
				'match_conditions_strategy' => 'search'
			]);

			return $this->get_template_override_notification($maybe_content_block, 'archive') ?: $options;
		}, 10, 1);

		add_filter('blocksy:options:categories', function ($options) {
			$maybe_content_block = blocksy_companion_get_content_block_that_matches([
				'template_type' => 'archive',
				'template_subtype' => 'canvas',
				'match_conditions_strategy' => 'categories'
			]);

			return $this->get_template_override_notification($maybe_content_block, 'archive') ?: $options;
		}, 10, 1);

		add_filter('blocksy:posts-listing:card_type', function($card_type) {
			$maybe_hook_id = blocksy_companion_get_content_block_that_matches([
				'template_type' => 'archive'
			]);

			if ($maybe_hook_id) {
				$card_type = 'boxed';
			}

			return $card_type;
		}, 10, 1);

		add_filter('blocksy:posts-listing:structure', function($structure, $prefix) {
			if (
				$structure === 'gutenberg'
				||
				$structure === 'simple'
			) {
				$maybe_hook_id = blocksy_companion_get_content_block_that_matches([
					'template_type' => 'archive',
					'match_conditions_strategy' => rtrim($prefix, '_')
				]);

				if ($maybe_hook_id) {
					return 'grid';
				}
			}

			return $structure;
		}, 10, 2);

		add_filter('blocksy:options:header:button', function($options) {
			$popup_templates = blocksy_companion_get_content_blocks([
				'template_type' => 'popup'
			]);

			if (! empty($popup_templates)) {
				return [
					'header_button_select_popup' => [
						'label' => __('Popup Template', 'blocksy-companion' ),
						'type' => 'ct-select',
						'design' => 'inline',
						'value' => '',
						'search' => true,
						'defaultToFirstItem' => false,
						'placeholder' => __('None', 'blocksy-companion'),
						'choices' => blocksy_ordered_keys(
							$popup_templates
						),
					],
				];
			}

			if (! empty($options)) {
				return $options;
			}

			return [
				blocksy_rand_md5() => [
					'type' => 'html',
					'label' => __('Popup Template', 'blocksy-companion'),
					'value' => '',
					'design' => 'inline',
					'html' => '<a href="' . admin_url('/edit.php?post_type=ct_content_block') . '" target="_blank" class="button" style="width: 130px; text-align: center;">' . __('Create Popup', 'blocksy-companion') . '</a>',
				],
			];
		}, 10, 1);
	}
}
