<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class FiltersUtils {
	static public function get_query_params() {
		$url = blocksy_current_url();

		return [
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for filter functionality, not form processing
			'params' => $_GET,
			'url' => $url
		];
	}

	static public function get_link_url($param, $value, $args = []) {
		$args = wp_parse_args($args, [
			'is_multiple' => true,
			'to_add' => []
		]);

		$value = urldecode($value);

		$query_string = array_merge([
			$param => $value,
		], $args['to_add']);

		$params = FiltersUtils::get_query_params();

		$url = $params['url'];
		$params = $params['params'];

		if (isset($params[$param])) {
			$all_attrs = explode(',', $params[$param]);

			if ($args['is_multiple']) {
				if (in_array($value, $all_attrs)) {
					$all_attrs = array_diff($all_attrs, [$value]);
				} else {
					array_push($all_attrs, $value);
				}
			} else {
				$all_attrs = array_diff([$value], $all_attrs);
			}

			sort($all_attrs);

			if (! empty($all_attrs)) {
				$query_string = array_merge([
					$param => implode(',', $all_attrs)
				], $args['to_add']);
			} else {
				$query_string = [];
			}

			$to_remove = array_merge([
				$param
			], array_keys($args['to_add']));

			$final_to_remove = [];

			foreach ($to_remove as $value) {
				if (! isset($query_string[$value])) {
					$final_to_remove[] = $value;
				}
			}

			if (! empty($final_to_remove)) {
				$url = remove_query_arg(
					$final_to_remove,
					$url
				);
			}
		}

		// Pre-encode values for proper UTF-8 handling.
		//
		// add_query_arg() uses build_query() with $urlencode=false, so new values
		// are not encoded - raw UTF-8 bytes end up in the URL string.
		// Later, parse_str() expects percent-encoded input and corrupts raw high bytes
		// (e.g. Greek π = bytes CF 80 → CF gets kept, 80 becomes underscore).
		//
		// Pre-encoding ensures: %CF%80 → parse_str → π → http_build_query → %CF%80
		// https://github.com/Creative-Themes/blocksy/issues/5103
		$encoded_query_string = [];

		foreach ($query_string as $key => $val) {
			$encoded_query_string[$key] = rawurlencode($val);
		}

		$url = add_query_arg($encoded_query_string, $url);

		// Sort params and rebuild URL
		parse_str(wp_parse_url($url, PHP_URL_QUERY) ?? '', $params);
		ksort($params);

		$url = strtok($url, '?') . '?' . http_build_query($params);

		// if url contains page in url, remove it
		//
		// Need to understand why is that.
		$url = preg_replace('/\/page\/[0-9]+/', '', $url);

		return $url;
	}
}

