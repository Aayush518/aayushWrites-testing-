<?php
/*
 * Plugin Name: Object Cache Pro (Drop-in)
 * Plugin URI: https://objectcache.pro
 * Description: A business class Redis object cache backend for WordPress.
 * Version: 1.16.3
 * Author: Rhubarb Group
 * Author URI: https://rhubarb.group
 * License: Proprietary
 * Requires PHP: 7.2
 */

defined('ABSPATH') || exit;

if (version_compare(PHP_VERSION, '7.2', '<')) {
    return require_once ABSPATH . WPINC . '/cache.php';
}

if (defined('WP_REDIS_DISABLED') && WP_REDIS_DISABLED) {
    return;
}

if (! empty(getenv('WP_REDIS_DISABLED'))) {
    return;
}

foreach ([
    defined('WP_REDIS_DIR') ? WP_REDIS_DIR : null,

    // Redis Cache Pro
    defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR . '/redis-cache-pro' : null,
    defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/mu-plugins/redis-cache-pro' : null,
    defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR . '/redis-cache-pro' : null,
    defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/plugins/redis-cache-pro' : null,

    // Object Cache Pro
    defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR . '/object-cache-pro' : null,
    defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/mu-plugins/object-cache-pro' : null,
    defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR . '/object-cache-pro' : null,
    defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/plugins/object-cache-pro' : null,
] as $path) {
    if ($path === null || ! is_readable("{$path}/api.php")) {
        continue;
    }

    if (include_once "{$path}/api.php") {
        return;
    }
}

error_log('objectcache.critical: Failed to locate and load object cache API');

$GLOBALS['wp_object_cache_errors'] = ['Failed to locate and load object cache API'];

if (defined('WP_DEBUG') && WP_DEBUG) {
    throw new RuntimeException('Failed to locate and load object cache API');
}
