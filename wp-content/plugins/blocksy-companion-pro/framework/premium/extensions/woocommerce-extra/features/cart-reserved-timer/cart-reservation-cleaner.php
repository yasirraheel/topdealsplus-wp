<?php

namespace Blocksy\Extensions\WoocommerceExtra;

if (! defined('ABSPATH')) {
	exit;
}

class CartReservationCleaner {
	private $cron_interval = 43200; // 12 hours
	private $cron_hook = 'blocksy_cart_reservations_cleanup';

	public function __construct() {
		add_action($this->cron_hook, [$this, 'cleanup']);

		add_filter('cron_schedules', function($schedules) {
			if (!isset($schedules['every_12_hours'])) {
				$schedules['every_12_hours'] = [
					'interval' => $this->cron_interval,
					'display'  => __('Every 12 Hours', 'blocksy-companion')
				];
			}
			return $schedules;
		});

		if (! wp_next_scheduled($this->cron_hook)) {
			wp_schedule_event(time(), 'every_12_hours', $this->cron_hook);
		}
	}

	public function cleanup() {
		$storage = new CartReservationStorage();
		$storage->clear_expired();
	}
}
