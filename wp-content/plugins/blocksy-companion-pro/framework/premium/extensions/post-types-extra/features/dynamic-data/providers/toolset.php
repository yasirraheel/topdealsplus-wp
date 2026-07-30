<?php

namespace Blocksy\Extensions\PostTypesExtra\DynamicData;

use Blocksy\Extensions\PostTypesExtra\CustomField;

class ToolsetProvider extends BaseProvider {
	public function get_provider_id() {
		return 'toolset';
	}

	public function get_provider_label() {
		return 'Toolset';
	}

	public function get_all_fields($args = []) {
		$all_fields = [];

		if (class_exists('\Toolset_Field_Group_Post_Factory')) {
			$post_factory = \Toolset_Field_Group_Post_Factory::get_instance();
			$post_groups_by_types = $post_factory->get_groups_by_post_types();

			foreach ($post_groups_by_types as $post_type => $groups) {
				foreach ($groups as $group) {
					$fields = $group->get_field_definitions();
					foreach ($fields as $field) {
						$all_fields[$field->get_slug()] = [
							'id' => $field->get_slug(),
							'name' => $field->get_display_name(),
							'type' => $field->get_type()->get_slug()
						];
					}
				}
			}
		}

		if (class_exists('\Toolset_Field_Group_Term_Factory')) {
			$term_factory = \Toolset_Field_Group_Term_Factory::get_instance();
			$term_groups_by_taxonomies = $term_factory->get_groups_by_taxonomies();

			foreach ($term_groups_by_taxonomies as $taxonomy => $groups) {
				foreach ($groups as $group) {
					$fields = $group->get_field_definitions();
					foreach ($fields as $field) {
						$all_fields[$field->get_slug()] = [
							'id' => $field->get_slug(),
							'name' => $field->get_display_name(),
							'type' => $field->get_type()->get_slug()
						];
					}
				}
			}
		}

		return $this->toolset_fields_to_result(
			array_values($all_fields),
			$args
		);
	}

	public function get_post_fields($post_id, $post_type, $args = []) {
		if (! function_exists('wpcf_admin_fields_get_active_fields_by_post_type')) {
			return [];
		}

		$fields = wpcf_admin_fields_get_active_fields_by_post_type($post_type);

		$toolset_fields = [];

		if (is_array($fields)) {
			foreach ($fields as $field) {
				if (is_array($field)) {
					$toolset_fields[] = $field;
				}
			}
		}

		return $this->toolset_fields_to_result($toolset_fields, $args);
	}

	public function get_post_type_fields($post_type, $args = []) {
		if (! function_exists('wpcf_admin_fields_get_active_fields_by_post_type')) {
			return [];
		}

		$fields = wpcf_admin_fields_get_active_fields_by_post_type($post_type);

		$toolset_fields = [];

		if (is_array($fields)) {
			foreach ($fields as $field) {
				if (is_array($field)) {
					$toolset_fields[] = $field;
				}
			}
		}

		return $this->toolset_fields_to_result($toolset_fields, $args);
	}

	public function get_term_fields($term_id, $args = []) {
		if (! class_exists('\Toolset_Field_Group_Term_Factory')) {
			return [];
		}

		$term = get_term($term_id);

		if (
			! $term
			||
			is_wp_error($term)
		) {
			return [];
		}

		$toolset_fields = $this->get_toolset_taxonomy_fields($term->taxonomy);

		return $this->toolset_fields_to_result($toolset_fields, $args);
	}

	public function render($field_id, $args = []) {
		$args = wp_parse_args($args, [
			'context_object' => null,
			'allow_images' => false
		]);

		if (! function_exists('types_render_field')) {
			return null;
		}

		$context_object = $args['context_object'];
		$render_params = [];

		$gateway = null;

		if ($context_object instanceof \WP_Post) {
			$render_params['post_id'] = $context_object->ID;

			$gateway = new \Types_Field_Gateway_Wordpress_Post();
		} elseif ($context_object instanceof \WP_Term) {
			$render_params['termmeta'] = true;
			$render_params['term_id'] = $context_object->term_id;
			$render_params['taxonomy'] = $context_object->taxonomy;

			$gateway = new \Types_Field_Gateway_Wordpress_Term();
		} elseif (is_int($context_object)) {
			$render_params['post_id'] = $context_object;

			$gateway = new \Types_Field_Gateway_Wordpress_Post();
		}

		if (! $gateway) {
			return null;
		}

		$field_descriptor = $gateway->get_field_by_id($field_id);

		if (! $field_descriptor) {
			return null;
		}

		$field_value = types_render_field($field_id, $render_params);

		if (empty($field_value)) {
			$field_value = '';
		}

		$field = [
			'name' => $field_id,
			'label' => $field_descriptor['name'],
			'type' => CustomField::$TYPE_TEXT,
			'value' => $field_value
		];

		return $field;
	}

	// Implementation details.

	private function get_toolset_taxonomy_fields($taxonomy) {
		$all_fields = [];

		if (! class_exists('\Toolset_Field_Group_Term_Factory')) {
			return $all_fields;
		}

		$field_groups = \Toolset_Field_Group_Term_Factory::get_instance()->get_groups_by_taxonomy($taxonomy);

		foreach ($field_groups as $group) {
			$fields = $group->get_field_definitions();

			foreach ($fields as $field) {
				$all_fields[] = [
					'group_name' => $group->get_display_name(),
					'group_slug' => $group->get_slug(),
					'field_name' => $field->get_display_name(),
					'field_slug' => $field->get_slug(),
					'field_type' => $field->get_type()->get_slug(),

					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_key' => $field->get_meta_key()
				];
			}
		}

		return $all_fields;
	}

	private function toolset_unified_fields_to_result($toolset_fields, $args = []) {
		$result = [];

		foreach ($toolset_fields as $field) {
			if (! is_array($field) || ! isset($field['field_slug'])) {
				continue;
			}

			// Only support text fields for now
			if (
				isset($field['field_type'])
				&&
				$field['field_type'] !== 'textfield'
			) {
				continue;
			}

			if (
				empty($field['field_slug'])
				||
				empty($field['field_name'])
			) {
				continue;
			}

			$custom_field = new CustomField([
				'id' => $field['field_slug'],
				'label' => $field['field_name'] ?? $field['field_slug'],
				'type' => CustomField::$TYPE_TEXT,
				'provider_id' => $this->get_provider_id()
			]);

			$result[] = $custom_field;
		}

		return $result;
	}

	private function toolset_fields_to_result($toolset_fields, $args = []) {
		$result = [];

		foreach ($toolset_fields as $field) {
			if (! is_array($field) || ! isset($field['id'])) {
				continue;
			}

			$custom_field = new CustomField([
				'id' => $field['id'],
				'label' => $field['name'] ?? $field['id'],
				'type' => CustomField::$TYPE_TEXT,
				'provider_id' => $this->get_provider_id()
			]);

			$result[] = $custom_field;
		}

		return $result;
	}
}
