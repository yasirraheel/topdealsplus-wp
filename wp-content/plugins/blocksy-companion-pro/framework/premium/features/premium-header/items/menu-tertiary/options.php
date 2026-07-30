<?php

if (! defined('ABSPATH')) {
	exit;
}

$options = blocksy_companion_get_options(
	get_template_directory() . '/inc/panel-builder/header/menu/options.php',
	[
		'location' => 'Header Menu 3'
	],
	false
);

