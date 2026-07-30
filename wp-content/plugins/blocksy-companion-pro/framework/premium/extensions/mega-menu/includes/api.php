<?php

namespace Blocksy\Extensions\MegaMenu;

if (! defined('ABSPATH')) {
	exit;
}

class Api {
	public function __construct() {
		add_action(
			'wp_ajax_blc_retrieve_mega_menu_content',
			[$this, 'retrieve_mega_menu_content']
		);

		add_action(
			'wp_ajax_nopriv_blc_retrieve_mega_menu_content',
			[$this, 'retrieve_mega_menu_content']
		);
	}

	private function submenu_get_children_ids($id, $items) {
		$ids = wp_filter_object_list(
			$items,
			['menu_item_parent' => $id],
			'and',
			'ID'
		);

		foreach ($ids as $id) {
			$ids = array_merge(
				$ids,
				$this->submenu_get_children_ids($id, $items)
			);
		}

		return $ids;
	}

	public function retrieve_mega_menu_content() {
		if (
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			! isset($_POST['ids'])
			||
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			! isset($_POST['menu_id'])
		) {
			wp_send_json_error();
		}

		add_filter(
			'wp_nav_menu_objects',
			function ($items, $args) {
				if (empty($args->blocksy_ajax_submenu)) {
					return $items;
				}

				$children = $this->submenu_get_children_ids(
					$args->blocksy_ajax_submenu,
					$items
				);

				foreach ($items as $key => $item) {
					if (! in_array($item->ID, $children)) {
						unset($items[$key]);
					}
				}

				return $items;
			},
			10, 2
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ids = explode(',', sanitize_text_field(wp_unslash($_POST['ids'])));

		$result = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$menu_id = sanitize_text_field(wp_unslash($_POST['menu_id']));

		foreach ($ids as $id) {
			$result[] = array_merge(
				['id' => intval($id)],
				$this->get_content_for(intval($id), $menu_id)
			);
		}

		wp_send_json_success([
			'content' => $result
		]);
	}

	public function get_content_for($id, $menu_id) {
		// Content rendered out-of-band under admin-ajax has none of the normal
		// page lifecycle: wp_enqueue_scripts never fires and the global
		// dynamic-CSS pass never runs. So a content block's frontend scripts
		// (e.g. GreenShift's accordion, gated behind is_admin() at render time)
		// and its dynamic CSS would be missing — the block arrives styled by the
		// inline <style> the walker prints, but dead. Mirror the AJAX popup
		// pipeline: run each embedded content block's pre_output() BEFORE the
		// render (flips the is_admin() gate + enqueues integration assets),
		// collect everything enqueued during the render, and ship it alongside
		// the HTML for the client to inject.
		$hooks = $this->get_content_block_hooks($id, $menu_id);

		$assets_collector = new \Blocksy\EnqueuedAssetsCollector();

		$css = new \Blocksy_Css_Injector();
		$tablet_css = new \Blocksy_Css_Injector();
		$mobile_css = new \Blocksy_Css_Injector();

		foreach ($hooks as $hook) {
			$cb_renderer = new \Blocksy\ContentBlocksRenderer(intval($hook));
			$cb_renderer->pre_output([
				'dynamic_styles_descriptor' => [
					'context' => 'inline',
					'css' => $css,
					'tablet_css' => $tablet_css,
					'mobile_css' => $mobile_css
				]
			]);
		}

		ob_start();
		wp_nav_menu([
			'container' => false,
			'blocksy_advanced_item' => true,
			'blocksy_mega_menu' => true,
			'menu' => $menu_id,
			'blocksy_ajax_submenu' => $id,
			'items_wrap' => '%3$s'
		]);
		$result = ob_get_clean();

		$assets = $assets_collector->collect();

		$inline_css = blocksy_companion_assemble_dynamic_css([
			'css' => $css,
			'tablet_css' => $tablet_css,
			'mobile_css' => $mobile_css,
		]);

		if (! empty($inline_css)) {
			$assets['inline_css'] = $inline_css;
		}

		return [
			'content' => '<ul class="sub-menu" role="menu">' . $result . '</ul>',
			'assets' => $assets
		];
	}

	// Resolve the content-block IDs embedded in a submenu (menu items with
	// `mega_menu_content_type = hook`), so their assets can be prepared before
	// the submenu is rendered.
	private function get_content_block_hooks($id, $menu_id) {
		$items = wp_get_nav_menu_items($menu_id);

		if (! $items) {
			return [];
		}

		$children = $this->submenu_get_children_ids($id, $items);

		$hooks = [];

		foreach ($children as $child_id) {
			$atts = blocksy_get_post_options($child_id);

			if (blocksy_akg('mega_menu_content_type', $atts, 'default') !== 'hook') {
				continue;
			}

			$hook = blocksy_translate_post_id(
				blocksy_default_akg('mega_menu_hook', $atts, '')
			);

			if ($hook) {
				$hooks[] = $hook;
			}
		}

		return array_values(array_unique($hooks));
	}

}

