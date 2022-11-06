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

namespace RedisCachePro\Plugin\Pages;

use RedisCachePro\ObjectCaches\ObjectCache;

use const RedisCachePro\Version;

class Tools extends Page
{
    /**
     * Returns the page title.
     *
     * @return string
     */
    public function title()
    {
        return 'Tools';
    }

    /**
     * Returns the page slug.
     *
     * @return string
     */
    public function slug()
    {
        return 'tools';
    }

    /**
     * Whether this page is enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        global $wp_object_cache;

        return $wp_object_cache instanceof ObjectCache;
    }

    /**
     * Boot the settings page and its components.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->isCurrent()) {
            return;
        }

        add_filter('screen_options_show_screen', '__return_true', PHP_INT_MAX);

        $this->addLatencyWidget();
        $this->addGroupsWidget();
        $this->addFlushLogWidget();

        $this->enqueueScript();
        $this->enqueueAssets();
    }

    /**
     * Enqueues the assets.
     *
     * @return void
     */
    protected function enqueueAssets()
    {
        $script = $this->plugin->asset('js/tools.js');

        wp_enqueue_script('postbox');

        wp_register_script('objectcache-tools', $script, ['jquery', 'clipboard'], Version);
        wp_enqueue_script('objectcache-tools');

        if (! $script) {
            wp_add_inline_script('objectcache-tools', $this->plugin->inlineAsset('js/tools.js'));
        }
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render()
    {
        require __DIR__ . '/../templates/pages/tools.phtml';
    }

    /**
     * Adds the "Connection Latency" widget.
     *
     * @return void
     */
    protected function addLatencyWidget()
    {
        add_meta_box(
            'objectcache_latency',
            'Latency',
            function () {
                require __DIR__ . '/../templates/widgets/tools/latency.phtml';
            },
            $this->plugin->screenId(),
            'normal'
        );
    }

    /**
     * Adds the "Cache Groups" widget.
     *
     * @return void
     */
    protected function addGroupsWidget()
    {
        add_meta_box(
            'objectcache_groups',
            'Groups',
            function () {
                require __DIR__ . '/../templates/widgets/tools/groups.phtml';
            },
            $this->plugin->screenId(),
            'side'
        );
    }

    /**
     * Adds the "Flush Log" widget.
     *
     * @return void
     */
    protected function addFlushLogWidget()
    {
        add_meta_box(
            'objectcache_flushlog',
            'Flush log',
            function () {
                require __DIR__ . '/../templates/widgets/tools/flushlog.phtml';
            },
            $this->plugin->screenId(),
            'normal'
        );
    }

    /**
     * Returns the caller name for given flush-log backtrace.
     *
     * @param  string  $backtrace
     * @return string
     */
    protected function flushlogCaller(string $backtrace)
    {
        /**
         * Filters the cache flush caller.
         *
         * @param  string  $caller  The cache flush caller.
         * @param  string  $backtrace  The comma-separated string of functions that have been called.
         */
        $caller = (string) apply_filters(
            'objectcache_flushlog_caller',
            (string) strstr($backtrace, ',', true),
            $backtrace
        );

        if (strpos($caller, 'Plugin->deactivate')) {
            return 'Plugin deactivated';
        }

        if (strpos($caller, 'Plugin->handleWidgetActions')) {
            return 'Dashboard widget';
        }

        if (strpos($caller, 'Plugin->enableDropin')) {
            return 'Drop-in enabled';
        }

        if (strpos($caller, 'Plugin->disableDropin')) {
            return 'Drop-in disabled';
        }

        if ($caller == 'Cache_Command->flush') {
            return 'wp cache flush';
        }

        if (strpos($caller, 'Commands->enable')) {
            return 'wp redis enable';
        }

        if (strpos($caller, 'Commands->disable')) {
            return 'wp redis disable';
        }

        if (strpos($caller, 'Commands->flush')) {
            return 'wp redis flush';
        }

        return $caller;
    }

    /**
     * Returns a clean, formatted backtrace for flush-log entry.
     *
     * @param  string  $backtrace
     * @return string
     */
    protected function flushlogBacktrace(string $backtrace)
    {
        $frames = array_reverse(explode(', ', $backtrace));

        $frames = array_filter($frames, function ($frame) {
            return ! in_array($frame, [
                'call_user_func',
                'call_user_func_array',
                'WP_Hook->do_action',
                'WP_Hook->do_action_ref_array',
                'WP_Hook->apply_filters',
                'WP_Hook->apply_filters_ref_array',
                'RedisCachePro\Plugin->flush',
                'RedisCachePro\Plugin->maybeLogFlush',
                "apply_filters('pre_objectcache_flush')",
                'wp_cache_flush',
            ]) && ! preg_match('/^(include|require)(_once)?\(/', $frame);
        });

        return implode(', ', array_slice($frames, 0, 5));
    }
}
