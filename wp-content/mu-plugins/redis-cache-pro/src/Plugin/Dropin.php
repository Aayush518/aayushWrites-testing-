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

use Throwable;

/**
 * @mixin \RedisCachePro\Plugin
 */
trait Dropin
{
    /**
     * Boot dropin component.
     *
     * @return void
     */
    public function bootDropin()
    {
        add_action('file_mod_allowed', [$this, 'applyFileModFilters'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'maybeUpdateDropin'], 10, 2);
    }

    /**
     * Adds shortcut filters to core's `file_mod_allowed` filter.
     *
     * @param  bool  $file_mod_allowed
     * @param  string  $context
     * @return bool
     */
    public function applyFileModFilters($file_mod_allowed, $context)
    {
        if ($context === 'object_cache_dropin') {
            /**
             * Filters whether drop-in file modifications are allowed.
             *
             * @param  bool  $dropin_mod_allowed  Whether drop-in modifications are allowed.
             */
            return (bool) apply_filters('objectcache_allow_dropin_mod', true);
        }

        return $file_mod_allowed;
    }

    /**
     * Attempt to enable the object cache drop-in.
     *
     * @return bool
     */
    public function enableDropin()
    {
        global $wp_filesystem;

        if (! \WP_Filesystem()) {
            return false;
        }

        $dropin = \WP_CONTENT_DIR . '/object-cache.php';
        $stub = "{$this->directory}/stubs/object-cache.php";

        $result = $wp_filesystem->copy($stub, $dropin, true, FS_CHMOD_FILE);

        if (function_exists('wp_opcache_invalidate')) {
            wp_opcache_invalidate($dropin, true);
        }

        /**
         * Filters whether to automatically flush the object after enabling the drop-in.
         *
         * @param  bool  $autoflush  Whether to auto-flush the object cache. Default true.
         */
        if ((bool) apply_filters('objectcache_autoflush', true)) {
            $this->flush();
        }

        return $result;
    }

    /**
     * Attempt to disable the object cache drop-in.
     *
     * @return bool
     */
    public function disableDropin()
    {
        global $wp_filesystem;

        if (! \WP_Filesystem()) {
            return false;
        }

        $dropin = \WP_CONTENT_DIR . '/object-cache.php';

        if (! $wp_filesystem->exists($dropin)) {
            return false;
        }

        $result = $wp_filesystem->delete($dropin);

        if (function_exists('wp_opcache_invalidate')) {
            wp_opcache_invalidate($dropin, true);
        }

        /**
         * Filters whether to automatically flush the object after disabling the drop-in.
         *
         * @param  bool  $autoflush  Whether to auto-flush the object cache. Default true.
         */
        if ((bool) apply_filters('objectcache_autoflush', true)) {
            $this->flush();
        }

        return $result;
    }

    /**
     * Update the object cache drop-in, if it's outdated.
     *
     * @param  \WP_Upgrader  $upgrader
     * @param  array<string, mixed>  $options
     * @return bool|void
     */
    public function maybeUpdateDropin($upgrader, $options)
    {
        $this->verifyDropin();

        if (! wp_is_file_mod_allowed('object_cache_dropin')) {
            return;
        }

        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }

        if (! in_array($this->basename, $options['plugins'] ?? [])) {
            return;
        }

        $diagnostics = $this->diagnostics();

        if (! $diagnostics->dropinExists() || ! $diagnostics->dropinIsValid()) {
            return;
        }

        if ($diagnostics->dropinIsUpToDate()) {
            return;
        }

        return $this->enableDropin();
    }

    /**
     * Verifies the object cache drop-in.
     *
     * @return void
     */
    public function verifyDropin()
    {
        if (! $this->license()->isValid()) {
            $this->disableDropin();
        }
    }

    /**
     * Initializes and connects the WordPress Filesystem Abstraction classes.
     *
     * @return \WP_Filesystem_Base
     */
    protected function wpFilesystem()
    {
        global $wp_filesystem;

        try {
            require_once \ABSPATH . 'wp-admin/includes/plugin.php';
        } catch (Throwable $th) {
            //
        }

        if (! \WP_Filesystem()) {
            try {
                require_once \ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
                require_once \ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            } catch (Throwable $th) {
                //
            }

            return new \WP_Filesystem_Direct(null);
        }

        return $wp_filesystem;
    }
}
