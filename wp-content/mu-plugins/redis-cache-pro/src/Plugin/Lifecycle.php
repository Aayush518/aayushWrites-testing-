<?php
/**
 * Copyright © Rhubarb Tech Inc. All Rights Reserved.
 *
 * All information contained herein is, and remains the property of Rhubarb Tech Incorporated.
 * The intellectual and technical concepts contained herein are proprietary to Rhubarb Tech Incorporated and
 * are protected by trade secret or copyright law. Dissemination and modification of this information or
 * reproduction of this material is strictly forbidden unless prior written permission is obtained from
 * Rhubarb Tech Incorporated.
 *
 * You should have received a copy of the `LICENSE` with this file. If not, please visit:
 * https://objectcache.pro/license.txt
 */

declare(strict_types=1);

namespace RedisCachePro\Plugin;

/**
 * @mixin \RedisCachePro\Plugin
 */
trait Lifecycle
{
    /**
     * Boot lifecycle component and register hooks.
     *
     * @return void
     */
    public function bootLifecycle()
    {
        add_action('init', [$this, 'run']);

        add_action("deactivate_{$this->basename}", [$this, 'deactivate']);
        add_action("uninstall_{$this->basename}", [$this, 'uninstall']);

        add_filter('pre_objectcache_flush', [$this, 'maybeLogFlush'], PHP_INT_MAX);
    }

    /**
     * Called when initializing WordPress.
     *
     * @return void
     */
    public function run()
    {
        if (is_admin()) {
            $this->license();
        }
    }

    /**
     * Called by `deactivate_{$plugin}` hook.
     *
     * @return void
     */
    public function deactivate()
    {
        delete_site_option('objectcache_license');

        delete_site_option('rediscache_license');
        delete_site_option('rediscache_license_last_check');

        $this->disableDropin();
        $this->flush();
    }

    /**
     * Called by `uninstall_{$plugin}` hook.
     *
     * @return void
     */
    public function uninstall()
    {
        wp_unschedule_event(
            (int) wp_next_scheduled('objectcache_prune_analytics'),
            'objectcache_prune_analytics'
        );
    }

    /**
     * Maybe log cache flush. Called by `pre_objectcache_flush` hook.
     *
     * @param  bool  $should_flush
     * @return bool
     */
    public function maybeLogFlush($should_flush)
    {
        if ($should_flush) {
            $this->logFlush();
        }

        return $should_flush;
    }

    /**
     * Log cache flush.
     *
     * @return void
     */
    public function logFlush()
    {
        /** @var string $traceSummary */
        $traceSummary = wp_debug_backtrace_summary(null, 1);

        if ($this->config->debug || (WP_DEBUG && WP_DEBUG_LOG)) {
            error_log("objectcache.debug: Flushing object cache... {$traceSummary}");
        }

        if ($this->config->debug || WP_DEBUG || $this->option('flushlog')) {
            $log = (array) get_site_option('objectcache_flushlog', []);

            array_unshift($log, [
                'time' => time(),
                'user' => get_current_user_id() ?: null,
                'site' => get_current_blog_id(),
                'cron' => wp_doing_cron(),
                'cli' => defined('WP_CLI') && WP_CLI,
                'trace' => $traceSummary,
            ]);

            update_site_option('objectcache_flushlog', array_slice($log, 0, 10));
        }
    }
}
