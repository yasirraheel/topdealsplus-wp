<?php

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The template for putting the site in maintenance mode.
 *
 * @package Blocksy
 */

get_header();

$maybe_content_block = blocksy_companion_get_content_block_that_matches([
	'template_type' => 'maintenance',
	'match_conditions' => true,
]);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo blocksy_companion_render_content_block($maybe_content_block);

get_footer();
