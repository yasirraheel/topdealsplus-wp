<?php

if (! defined('ABSPATH')) {
	exit;
}

blocksy_html_tag_e(
	'div',
	[
		'class' => 'waitlist-product-mobile-actions ct-hidden-lg'
	],
	blocksy_html_tag(
		'ul',
		[],
		blocksy_html_tag(
			'li',
			[],
			blocksy_html_tag(
				'span',
				[],
				__('Stock Status', 'blocksy-companion') . ':'
			) .
			__('Out of Stock', 'blocksy-companion')
		) .
		blocksy_html_tag(
			'li',
			[],
			blocksy_html_tag(
				'span',
				[],
				__('Confirmed', 'blocksy-companion') . ':'
			) .
			($entry->confirmed ? __('Yes', 'blocksy-companion') : __('No', 'blocksy-companion'))
		)
	) .
	blocksy_html_tag(
		'div',
		[

		],
		blocksy_html_tag(
			'button',
			[
				'class' => 'button unsubscribe',
				'type' => 'submit',
				'data-token' => $entry->unsubscribe_token,
				'data-id' => $entry->subscription_id,
			],
			__('Unsubscribe', 'blocksy-companion')
		)
	)
);