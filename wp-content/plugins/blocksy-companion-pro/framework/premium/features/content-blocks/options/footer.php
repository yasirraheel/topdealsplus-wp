<?php

if (! defined('ABSPATH')) {
	exit;
}

$options = blocksy_akg(
	'options',
	blocksy_companion_get_variables_from_file(
		dirname(__FILE__) . '/header.php',
		['options' => []]
	)
);

