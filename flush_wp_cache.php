<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/wp-load.php';

echo "=== FLUSHING WORDPRESS CACHES ===\n";

// 1. Flush WP Rocket cache if function exists or remove cache files
if (function_exists('rocket_clean_domain')) {
    rocket_clean_domain();
    echo "WP Rocket cache flushed via rocket_clean_domain().\n";
} else {
    // Manually clean cache directory
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

// 2. Clear Blocksy Theme Cache & Transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%' OR option_name LIKE '%blocksy_dynamic_css%'");
echo "Cleared Blocksy transients and dynamic CSS cache.\n";

// 3. Clear WP Object Cache & Rewrite Rules
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "WP Object Cache flushed.\n";
}

flush_rewrite_rules(true);
echo "Rewrite rules flushed.\n";

echo "ALL WORDPRESS CACHES CLEARED SUCCESSFULLY!\n";
