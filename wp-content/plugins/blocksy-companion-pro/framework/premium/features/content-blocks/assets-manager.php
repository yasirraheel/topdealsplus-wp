<?php

namespace Blocksy;

if (! defined('ABSPATH')) {
	exit;
}

class ContentBlocksAssetsManager {
	private $hooks_to_enqueue = [];

	public function __construct() {
		add_action('wp', function () {
			if (is_admin()) {
				return;
			}

			$this->run_pre_output();
		}, 900000);
	}

	public function enqueue_hook($hook_id) {
		if (! $hook_id) {
			return;
		}

		$this->hooks_to_enqueue[] = $hook_id;
		$this->hooks_to_enqueue = array_unique($this->hooks_to_enqueue);
	}

	public function maybe_enqueue_sidebar_styles($hook_id) {
		if (! $hook_id) {
			return;
		}

		add_action('blocksy:frontend:scripts-enqueued', function() use ($hook_id) {
			$page_structure = blocksy_default_akg(
				'content_block_structure',
				blocksy_get_post_options($hook_id),
				'default'
			);

			if (
				$page_structure === 'type-1'
				||
				$page_structure === 'type-2'
			) {
				wp_enqueue_style('ct-sidebar-styles');
			}
		});
	}

	private function run_pre_output() {
		$this->enqueue_hook(
			blocksy_companion_get_content_block_that_matches([
				'template_type' => 'header'
			])
		);

		$single_template = blocksy_companion_get_content_block_that_matches([
			'template_type' => 'single',
			'template_subtype' => 'canvas'
		]);

		$this->enqueue_hook($single_template);
		$this->maybe_enqueue_sidebar_styles($single_template);

		$this->enqueue_hook(
			blocksy_companion_get_content_block_that_matches([
				'template_type' => 'single',
				'template_subtype' => 'content'
			])
		);

		$archive_template = blocksy_companion_get_content_block_that_matches([
			'template_type' => 'archive',
			'template_subtype' => 'canvas'
		]);

		$this->enqueue_hook($archive_template);
		$this->maybe_enqueue_sidebar_styles($archive_template);

		$all_blocks = array_keys(blocksy_companion_get_content_blocks([
			'template_type' => 'archive'
		]));

		foreach ($all_blocks as $block_id) {
			$values = blocksy_get_post_options($block_id);

			$conditions = blocksy_default_akg('conditions', $values, []);

			$template_subtype = blocksy_default_akg(
				'template_subtype',
				$values,
				'card'
			);

			if ($template_subtype === 'card') {
				$this->enqueue_hook($block_id);
			}
		}

		$this->enqueue_hook(
			blocksy_companion_get_content_block_that_matches([
				'template_type' => 'footer'
			])
		);

		if (is_404()) {
			$this->enqueue_hook(
				blocksy_companion_get_content_block_that_matches([
					'template_type' => '404',
					'match_conditions' => false
				])
			);
		}

		if ((
			is_home()
			||
			is_archive()
			||
			is_search()
		) && ! have_posts()) {
			$this->enqueue_hook(
				blocksy_companion_get_content_block_that_matches([
					'template_type' => 'nothing_found'
				])
			);
		}

		foreach ($this->hooks_to_enqueue as $hook_id) {
			$content_block_renderer = new ContentBlocksRenderer($hook_id);
			$content_block_renderer->pre_output();
		}
	}
}

