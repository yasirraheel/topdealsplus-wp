<?php

namespace Blocksy\CustomPostType\Integrations;

class StackableStorage {
	public static $is_main_script_loaded = false;
	public static $scripts_loaded = [];
}

class Stackable_Init_No_Constructor extends \Stackable_Init {
	public function __construct() {
	}
}

class Stackable extends \Blocksy\CustomPostTypeRenderer {
	public function get_content($args = []) {
		return \Blocksy\CustomPostTypeRenderer::NOT_IMPLEMENTED;
	}

	public function pre_output() {
		$post = get_post($this->id);

		if (! $post) {
			return;
		}

		$contentpost = str_replace(
			'<!-- wp:post-content /-->',
			'',
			$post->post_content
		);

		if (wp_doing_ajax()) {
			$this->prepare_ajax_frontend_loading($contentpost);
		}

		// Stackable will handle content and enqueue everything needed.
		if (method_exists('\Stackable_Init', 'enqueue_frontend_assets_for_content')) {
			\Stackable_Init::enqueue_frontend_assets_for_content($contentpost);
			return;
		}

		// Backward compatibility for older Stackable versions.
		$this->enqueue_frontend_assets_for_content($contentpost);
	}

	// We fetch popup content over admin-ajax (wp_ajax_blc_retrieve_popup_content),
	// where is_admin() === true. Stackable treats that as "backend" and breaks two
	// ways, both of which we undo here so the fetch behaves like the frontend:
	//
	//   1. Stackable require_once's its per-block frontend files
	//      (block/*/index.php) only inside `if ( ! is_admin() )` at plugin load
	//      (stackable-ultimate-gutenberg-blocks/plugin.php). Those files register
	//      the `stackable/{block}/enqueue_scripts` handlers that enqueue the real
	//      per-block scripts (stk-frontend-tabs, etc.). On admin-ajax they're
	//      never loaded — and it's too late to undo a skipped require at runtime —
	//      so enqueue_frontend_assets_for_content()'s do_action() calls fire into
	//      nothing. We require the same files ourselves below.
	//
	//   2. Those handlers (and Stackable's loaders) also bail on is_admin(), so we
	//      flip current_screen to make is_admin() return false for this render.
	//
	// The $files list mirrors Stackable's own `if ( ! is_admin() )` block in its
	// plugin.php; keep it in sync if Stackable's list changes.
	private function prepare_ajax_frontend_loading($content) {
		if (
			! defined('STACKABLE_FILE')
			||
			strpos($content, '<!-- wp:stackable/') === false
		) {
			return;
		}

		$GLOBALS['current_screen'] = new class {
			public function in_admin() {
				return false;
			}
		};

		$base = dirname(STACKABLE_FILE) . '/';

		$files = [
			'src/lightbox/index.php',
			'src/block/accordion/index.php',
			'src/block/carousel/index.php',
			'src/block/count-up/index.php',
			'src/block/countdown/index.php',
			'src/block/expand/index.php',
			'src/block/notification/index.php',
			'src/block/video-popup/index.php',
			'src/block/table-of-contents/index.php',
			'src/block/map/index.php',
			'src/block/progress-bar/index.php',
			'src/block/progress-circle/index.php',
			'src/block/horizontal-scroller/index.php',
			'src/block/tabs/index.php',
			'src/block-components/alignment/index.php',
			'src/block/columns/index.php',
			'src/block/timeline/index.php'
		];

		foreach ($files as $file) {
			if (is_readable($base . $file)) {
				require_once $base . $file;
			}
		}
	}

	public static function enqueue_frontend_assets_for_content($post_content) {
		$init = new Stackable_Init_No_Constructor();

		// If a Stackable block is present in the post content, enqueue the frontend assets.
		if ( ! StackableStorage::$is_main_script_loaded && ! is_admin() ) {
			if ( stripos( $post_content, '<!-- wp:stackable/' ) !==  false ) {
				$init->block_enqueue_frontend_assets();
				StackableStorage::$is_main_script_loaded = true;
			}
		}

		// Gather all the unique Stackable blocks and load all the block scripts once.
		// Gather all the "<!-- wp:stackable/BLOCK_NAME"
		preg_match_all('/<!-- wp:stackable\/([a-zA-Z_-]+)/', $post_content, $stackable_blocks);

		// Go through each unique block name.
		foreach ($stackable_blocks[1] as $_block_name) {
			// Clean up the block name, trailing "-" from the end since it may have "--" in the end if the post content is compressed.
			$block_name = trim($_block_name, '-');

			// Enqueue the block script once.
			if (! isset(StackableStorage::$scripts_loaded[$block_name])) {
				do_action( 'stackable/' . $block_name . '/enqueue_scripts' );
				StackableStorage::$scripts_loaded[$block_name] = true;
			}
		}

		// Check whether the current block needs to enqueue some scripts.
		// This gets called across all the blocks.
		do_action('stackable/enqueue_scripts', $post_content, null);
	}
}

