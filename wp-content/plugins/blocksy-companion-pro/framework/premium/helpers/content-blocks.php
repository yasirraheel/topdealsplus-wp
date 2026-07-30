<?php

if (! defined('ABSPATH')) {
	exit;
}

function blocksy_companion_render_content_block($id, $args = []) {
	return \Blocksy\Plugin::instance()
		->premium
		->content_blocks
		->output_hook($id, $args);
}

function blocksy_companion_get_content_block_that_matches($args = []) {
	if (! function_exists('blocksy_get_post_options')) {
		return null;
	}

	$args = wp_parse_args($args, [
		'template_type' => 'hook',
		'template_subtype' => 'card',
		'match_conditions' => true,
		'match_conditions_strategy' => 'current-screen',
		'predifined_conditions' => []
	]);

	$all_blocks = array_keys(blocksy_companion_get_content_blocks([
		'template_type' => $args['template_type']
	]));

	foreach ($all_blocks as $block_id) {
		$values = blocksy_get_post_options($block_id);

		$conditions = blocksy_default_akg('conditions', $values, []);

		$default_template_subtype = 'card';

		if ($args['template_type'] === 'single') {
			$default_template_subtype = 'canvas';
		}

		$template_subtype = blocksy_default_akg(
			'template_subtype',
			$values,
			$default_template_subtype
		);

		if (
			! \Blocksy\Plugin::instance()
				->premium
				->content_blocks
				->is_hook_eligible_for_display(
					$block_id,
					[
						'match_conditions' => $args['match_conditions'],
						'match_conditions_strategy' => $args['match_conditions_strategy'],
						'predifined_conditions' => $args['predifined_conditions']
					]
				)
		) {
			continue;
		}

		if ($template_subtype !== $args['template_subtype']) {
			continue;
		}

		return $block_id;
	}

	return null;
}

blocksy_companion_register_theme_bridge('content_blocks', 'blocksy_companion_get_content_block_that_matches');

function blocksy_companion_is_hook_eligible_for_display($args = []) {
	$args = wp_parse_args($args, [
		'hook_id' => null,
		'match_conditions' => true,
		'match_conditions_strategy' => 'current-screen',
	]);

	if (! $args['hook_id']) {
		return false;
	}

	return \Blocksy\Plugin::instance()
		->premium
		->content_blocks
		->is_hook_eligible_for_display($args['hook_id'], [
			'match_conditions' => $args['match_conditions'],
			'match_conditions_strategy' => $args['match_conditions_strategy'],
		]);
}

blocksy_companion_register_theme_bridge('content_blocks', 'blocksy_companion_is_hook_eligible_for_display');

function blocksy_companion_get_content_blocks($args = []) {
	$args = wp_parse_args($args, [
		'template_type' => 'hook'
	]);

	static $all_items = null;

	if ($all_items === null) {
		$all_items = get_posts([
			'post_type' => 'ct_content_block',
			'numberposts' => -1,
			'suppress_filters' => false,
			'fields' => 'ids'
		]);
	}

	if (! is_array($all_items)) {
		return [];
	}

	$blocks = [];

	foreach($all_items as $hook_id) {
		$template_type = get_post_meta($hook_id, 'template_type', true);

		if ($template_type !== $args['template_type']) {
			continue;
		}

		$blocks[$hook_id] = html_entity_decode(get_the_title($hook_id));
	}

	return $blocks;
}

