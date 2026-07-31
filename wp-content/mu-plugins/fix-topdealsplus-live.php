<?php
/**
 * Plugin Name: Top Deals Plus Live Auto Fix & Sync
 * Description: Ensures live server DB settings for Homepage Hero, Posts Grid, and Logo are always in sync.
 */

add_action('init', function() {
    // Only run once per request or when needed
    static $run = false;
    if ($run) return;
    $run = true;

    // 1. Ensure front page settings
    if (get_option('show_on_front') !== 'page' || get_option('page_on_front') != 402) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', 402);
        update_option('page_for_posts', 12);
    }

    // 2. Ensure Page 402 has post_type="post" shortcode
    $home_page = get_post(402);
    if ($home_page && strpos($home_page->post_content, 'post_type="blc-product-review"') !== false) {
        $updated_content = str_replace('post_type="blc-product-review"', 'post_type="post"', $home_page->post_content);
        $updated_content = str_replace('limit="4"', 'limit="6"', $updated_content);
        wp_update_post([
            'ID' => 402,
            'post_content' => $updated_content
        ]);
        
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }

    // 3. Ensure Blocksy Theme Mods (blog_structure=grid, custom_logo=422)
    $mods = get_option('theme_mods_blocksy');
    if (is_array($mods)) {
        $changed = false;
        if (!isset($mods['blog_structure']) || $mods['blog_structure'] !== 'grid') {
            $mods['blog_structure'] = 'grid';
            $changed = true;
        }
        if (!isset($mods['custom_logo']) || $mods['custom_logo'] != 422) {
            $mods['custom_logo'] = 422;
            $changed = true;
        }
        if (!isset($mods['logo_max_width'])) {
            $mods['logo_max_width'] = ['desktop' => 220, 'tablet' => 190, 'mobile' => 160];
            $changed = true;
        }
        if ($changed) {
            update_option('theme_mods_blocksy', $mods);
            if (function_exists('rocket_clean_domain')) {
                rocket_clean_domain();
            }
        }
    }
});
