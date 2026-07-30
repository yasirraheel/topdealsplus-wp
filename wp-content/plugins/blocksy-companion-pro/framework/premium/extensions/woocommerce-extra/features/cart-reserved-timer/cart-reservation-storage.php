<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class CartReservationStorage {
	private $table;
	private static $cache = [];
	private static $table_ready = false;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'blocksy_cart_reservations';
	}

	private function table_exists() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$wpdb->esc_like($this->table)
			)
		);

		return $result === $this->table;
	}

	private function needs_migration() {
		global $wpdb;

		if (! $this->table_exists()) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM %i",
				$this->table,
			)
		);

		$column_names = array_map(function($column) {
			return $column->Field;
		}, $columns);

		return (
			in_array('cart_data', $column_names, true)
			||
			in_array('variation_id', $column_names, true)
		);
	}

	private function ensure_table() {
		if (self::$table_ready) {
			return;
		}

		if (! $this->table_exists() || $this->needs_migration()) {
			$this->create_table();
		}

		self::$table_ready = true;
	}

	public function create_table() {
		global $wpdb;

		if ($this->needs_migration()) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare("DROP TABLE IF EXISTS %i", $this->table)
			);
		}

		$table_name = $this->table;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(255) NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			last_modified DATETIME NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY product_id (product_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		dbDelta($sql);
	}

	public function set_cart_reservation($session_id, $cart_items, $last_modified) {
		global $wpdb;

		$this->ensure_table();

		if (empty($cart_items)) {
			$this->remove_cart_reservation($session_id);
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query('START TRANSACTION');

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete($this->table, ['session_id' => $session_id]);

		$placeholders = [];
		$values = [$this->table];

		foreach ($cart_items as $item) {
			$reserved_product_id = isset($item['variation_id']) && (int) $item['variation_id'] > 0
				? (int) $item['variation_id']
				: (int) $item['product_id'];

			$placeholders[] = '(%s, %d, %d, %s, %s)';
			$values[] = $session_id;
			$values[] = $reserved_product_id;
			$values[] = (int) $item['quantity'];
			$values[] = $last_modified;
			$values[] = $last_modified;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"INSERT INTO %i (session_id, product_id, quantity, last_modified, created_at) VALUES " . implode(', ', $placeholders),
				...$values
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query('COMMIT');

		self::$cache = [];
	}

	public function remove_cart_reservation($session_id) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete($this->table, ['session_id' => $session_id]);

		self::$cache = [];
	}

	public function get_reserved_quantity_for_variations($variation_ids, $args = []) {
		global $wpdb;

		$this->ensure_table();

		$variation_ids = array_values(
			array_unique(
				array_map('intval', (array) $variation_ids)
			)
		);

		if (empty($variation_ids)) {
			return [];
		}

		sort($variation_ids);

		$exclude_session_id = $args['exclude_session_id'] ?? null;
		$suffix = '_' . ($exclude_session_id ?? 'all');
		$reserved = [];
		$missing_ids = [];

		foreach ($variation_ids as $variation_id) {
			$id_cache_key = 'qty_' . $variation_id . $suffix;

			if (isset(self::$cache[$id_cache_key])) {
				$reserved[$variation_id] = self::$cache[$id_cache_key];
				continue;
			}

			$missing_ids[] = $variation_id;
		}

		if (empty($missing_ids)) {
			return $reserved;
		}

		$woo_reserved_timer_time = blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_time', 10);
		$total_seconds = $woo_reserved_timer_time * 60;
		$current_time = gmdate('Y-m-d H:i:s', current_time('timestamp', true));

		$placeholders = implode(', ', array_fill(0, count($missing_ids), '%d'));

		$sql = "SELECT product_id, SUM(quantity) AS reserved_qty FROM %i WHERE product_id IN ($placeholders) AND TIMESTAMPDIFF(SECOND, last_modified, %s) < %d";
		$params = array_merge([$this->table], $missing_ids, [$current_time, $total_seconds]);

		if ($exclude_session_id) {
			$sql .= " AND session_id != %s";
			$params[] = $exclude_session_id;
		}

		$sql .= " GROUP BY product_id";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql,
				...$params
			)
		);

		$fetched = [];

		foreach ($results as $row) {
			$product_id = (int) $row->product_id;
			$qty = (int) $row->reserved_qty;

			$fetched[$product_id] = $qty;
			self::$cache['qty_' . $product_id . $suffix] = $qty;
			$reserved[$product_id] = $qty;
		}

		foreach ($missing_ids as $variation_id) {
			if (isset($fetched[$variation_id])) {
				continue;
			}

			self::$cache['qty_' . $variation_id . $suffix] = 0;
			$reserved[$variation_id] = 0;
		}

		return $reserved;
	}

	public function get_reserved_quantity_for_product($product_id, $args = []) {
		global $wpdb;

		$this->ensure_table();

		$exclude_session_id = $args['exclude_session_id'] ?? null;

		$cache_key = 'qty_' . $product_id . '_' . ($exclude_session_id ?? 'all');

		if (isset(self::$cache[$cache_key])) {
			return self::$cache[$cache_key];
		}

		$woo_reserved_timer_time = blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_time', 10);
		$total_seconds = $woo_reserved_timer_time * 60;
		$current_time = gmdate('Y-m-d H:i:s', current_time('timestamp', true));

		$sql = "SELECT SUM(quantity) FROM %i WHERE product_id = %d AND TIMESTAMPDIFF(SECOND, last_modified, %s) < %d";
		$params = [$this->table, (int) $product_id, $current_time, $total_seconds];

		if ($exclude_session_id) {
			$sql .= ' AND session_id != %s';
			$params[] = $exclude_session_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$reserved = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql,
				...$params
			)
		);

		self::$cache[$cache_key] = $reserved;

		return self::$cache[$cache_key];
	}

	public function clear_expired() {
		global $wpdb;

		$this->ensure_table();

		$woo_reserved_timer_time = blocksy_companion_theme_functions()->blocksy_get_theme_mod('woo_reserved_timer_time', 10);
		$total_seconds = $woo_reserved_timer_time * 60;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i
				WHERE TIMESTAMPDIFF(SECOND, last_modified, UTC_TIMESTAMP()) >= %d",
				$this->table,
				$total_seconds
			)
		);

		self::$cache = [];
	}

	public function get_current_reservation($session_id) {
		global $wpdb;

		$this->ensure_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT session_id, last_modified FROM %i WHERE session_id = %s LIMIT 1",
				$this->table,
				$session_id
			)
		);
	}
}

