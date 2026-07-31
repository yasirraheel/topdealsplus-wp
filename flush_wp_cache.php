<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/wp-load.php';

echo "=== FLUSHING & SYNCING WORDPRESS CACHES ===\n";

// 1. Ensure front page settings
update_option('show_on_front', 'page');
update_option('page_on_front', 402);
update_option('page_for_posts', 12);
echo "Confirmed show_on_front = page, page_on_front = 402.\n";

// 2. Ensure Page 402 shortcode targets posts
$home_page = get_post(402);
if ($home_page && strpos($home_page->post_content, 'post_type="blc-product-review"') !== false) {
    $updated_content = str_replace('post_type="blc-product-review"', 'post_type="post"', $home_page->post_content);
    $updated_content = str_replace('limit="4"', 'limit="6"', $updated_content);
    wp_update_post([
        'ID' => 402,
        'post_content' => $updated_content
    ]);
    echo "Updated Page 402 shortcode target to post_type=post.\n";
}

// 3. Ensure Blocksy Theme Mods
$mods = get_option('theme_mods_blocksy');
if (is_array($mods)) {
    $mods['blog_structure'] = 'grid';
    $mods['blog_columns'] = ['desktop' => 3, 'tablet' => 2, 'mobile' => 1];
    $mods['blc-product-review_archive_structure'] = 'grid';
    $mods['custom_logo'] = 422;
    $mods['logo_max_width'] = ['desktop' => 220, 'tablet' => 190, 'mobile' => 160];
    update_option('theme_mods_blocksy', $mods);
    echo "Updated theme_mods_blocksy options.\n";
}

// 4. Flush WP Rocket cache
if (function_exists('rocket_clean_domain')) {
    rocket_clean_domain();
    echo "WP Rocket cache flushed via rocket_clean_domain().\n";
} else {
    $cache_dir = __DIR__ . '/wp-content/cache';
    if (is_dir($cache_dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        echo "WP Rocket cache directory cleared manually.\n";
    }
}

// 5. Clear Blocksy Theme Cache & Transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%' OR option_name LIKE '%blocksy_dynamic_css%'");
echo "Cleared Blocksy transients and dynamic CSS cache.\n";

// 6. Clear WP Object Cache & Rewrite Rules
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "WP Object Cache flushed.\n";
}

flush_rewrite_rules(true);
echo "Rewrite rules flushed.\n";

echo "ALL WORDPRESS CACHES & SETTINGS SYNCED AND CLEARED SUCCESSFULLY!\n";
