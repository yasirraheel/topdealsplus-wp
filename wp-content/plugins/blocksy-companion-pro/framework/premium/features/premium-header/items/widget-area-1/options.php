<?php

if (! defined('ABSPATH')) {
	exit;
}

if (! isset($sidebarId)) {
	$sidebarId = 'ct-header-sidebar-1';
}

$options = [
	'widget' => [
		'type' => 'ct-widget-area',
		'sidebarId' => $sidebarId
	],
];

