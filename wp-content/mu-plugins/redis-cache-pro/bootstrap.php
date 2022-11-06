<?php

defined('ABSPATH') || exit;

spl_autoload_register(function ($fqcn) {
    if (strpos($fqcn, 'RedisCachePro\\') === 0) {
        require_once str_replace(['\\', 'RedisCachePro/'], ['/', __DIR__ . '/src/'], $fqcn) . '.php';
    }
});

(function ($config) {
    if (defined('WP_REDIS_CONFIG') || empty($config)) {
        return;
    }

    $config = json_decode((string) $config, true, 3);
    $error = json_last_error();

    if ($error !== JSON_ERROR_NONE || ! is_array($config)) {
        error_log(sprintf(
            'objectcache.warning: Unable to decode `OBJECTCACHE_CONFIG` environment variable (%s)',
            json_last_error_msg()
        ));
    }

    define('WP_REDIS_CONFIG', $config);
})(getenv('OBJECTCACHE_CONFIG'));
