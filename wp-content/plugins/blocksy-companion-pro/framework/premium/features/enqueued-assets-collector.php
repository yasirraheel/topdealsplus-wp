<?php

namespace Blocksy;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Captures the styles and scripts enqueued during a unit of work.
 *
 * Snapshots the wp_styles()/wp_scripts() queues on construction, then on
 * collect() diffs them and returns only the handles enqueued in between —
 * resolved into a shippable shape (handle + src + inline data) ready to be
 * injected on the client.
 *
 * Used to deliver the assets of content that is rendered out-of-band, where the
 * normal page lifecycle won't enqueue/print them for us — e.g. AJAX popups, and
 * in the future mega menu loading, etc.
 *
 * Usage:
 *
 *   $collector = new EnqueuedAssetsCollector();
 *   // ... render something that enqueues styles/scripts ...
 *   $assets = $collector->collect(); // ['styles' => [...], 'scripts' => [...]]
 *
 * Reference consumer: ContentBlocksPopupsLogic::render_popup_with_assets()
 * in framework/premium/features/content-blocks/popups.php — it wraps the popup
 * render with a collector and ships the result as the AJAX response. Note this
 * captures only assets that go through wp_enqueue_style/script; handle-less
 * inline CSS (e.g. Blocksy's per-popup dynamic CSS) is collected separately and
 * attached by the consumer.
 */
class EnqueuedAssetsCollector {
	private $styles_before;
	private $scripts_before;
	private $include_queued_styles;
	private $include_queued_scripts;

	public function __construct($args = []) {
		$args = wp_parse_args($args, [
			'include_queued_styles' => [],
			'include_queued_scripts' => []
		]);

		$this->styles_before = wp_styles()->queue;
		$this->scripts_before = wp_scripts()->queue;
		$this->include_queued_styles = $args['include_queued_styles'];
		$this->include_queued_scripts = $args['include_queued_scripts'];
	}

	public function collect() {
		return [
			'styles' => $this->collect_styles(),
			'scripts' => $this->collect_scripts()
		];
	}

	private function collect_styles() {
		$styles = wp_styles();
		$assets = [];

		$new_handles = $this->resolve_handles_with_dependencies(
			array_unique(array_merge(
				array_diff($styles->queue, $this->styles_before),
				array_intersect($this->include_queued_styles, $styles->queue)
			)),
			$styles
		);

		foreach ($new_handles as $handle) {
			if (! isset($styles->registered[$handle])) {
				continue;
			}

			$obj = $styles->registered[$handle];
			$inline_style = $styles->get_data($handle, 'after');

			// A handle with neither a file nor inline CSS has nothing to ship.
			if (! $obj->src && ! $inline_style) {
				continue;
			}

			$entry = ['handle' => $handle];

			$src = $this->resolve_src($obj, $styles->base_url);

			if ($src) {
				$entry['src'] = $src;
			}

			if ($inline_style) {
				$entry['inline'] = implode("\n", $inline_style);
			}

			$assets[] = $entry;
		}

		return $assets;
	}

	private function collect_scripts() {
		$scripts = wp_scripts();
		$assets = [];

		$new_handles = $this->resolve_handles_with_dependencies(
			array_unique(array_merge(
				array_diff($scripts->queue, $this->scripts_before),
				array_intersect($this->include_queued_scripts, $scripts->queue)
			)),
			$scripts
		);

		foreach ($new_handles as $handle) {
			if (! isset($scripts->registered[$handle])) {
				continue;
			}

			$obj = $scripts->registered[$handle];

			if (! $obj->src) {
				continue;
			}

			$entry = [
				'handle' => $handle,
				'src' => $this->resolve_src($obj, $scripts->base_url)
			];

			$data = $scripts->get_data($handle, 'data');

			if ($data) {
				$entry['data'] = $data;
			}

			$assets[] = $entry;
		}

		return $assets;
	}

	// Resolve a registered dependency's src into an absolute, versioned URL,
	// matching how WP_Dependencies would print it.
	private function resolve_src($obj, $base_url) {
		if (! $obj->src) {
			return '';
		}

		$src = $obj->src;

		if (! preg_match('|^(https?:)?//|', $src)) {
			$src = $base_url . $src;
		}

		if ($obj->ver) {
			$src = add_query_arg('ver', $obj->ver, $src);
		}

		return $src;
	}

	private function resolve_handles_with_dependencies($handles, $dependencies) {
		$result = [];

		$append = function ($handle) use (&$append, &$result, $dependencies) {
			if (in_array($handle, $result, true)) {
				return;
			}

			if (! isset($dependencies->registered[$handle])) {
				return;
			}

			foreach ($dependencies->registered[$handle]->deps as $dependency) {
				$append($dependency);
			}

			$result[] = $handle;
		};

		foreach ($handles as $handle) {
			$append($handle);
		}

		return $result;
	}
}
