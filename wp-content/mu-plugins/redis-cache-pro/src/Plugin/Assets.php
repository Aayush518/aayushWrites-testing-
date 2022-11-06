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

use RedisCachePro\Diagnostics\Diagnostics;

/**
 * @mixin \RedisCachePro\Plugin
 */
trait Assets
{
    /**
     * Boot Assets component.
     *
     * @return void
     */
    public function bootAssets()
    {
        //
    }

    /**
     * Returns the URL to the given asset.
     *
     * @param  string  $path
     * @return string|false
     */
    public function asset($path)
    {
        if (Diagnostics::isMustUse()) {
            $plugin = $this->muAssetPath();

            return $plugin
                ? plugins_url("resources/{$path}", $plugin)
                : false;
        }

        return plugins_url("resources/{$path}", $this->filename);
    }

    /**
     * Returns the contents of the given asset.
     *
     * @param  string  $path
     * @return string
     */
    public function inlineAsset($path)
    {
        $asset = (string) file_get_contents(
            "{$this->directory}/resources/{$path}"
        );

        if (! defined('SCRIPT_DEBUG') || ! SCRIPT_DEBUG) {
            $asset = preg_replace('/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\'|")\/\/.*))/', '', $asset);
            $asset = preg_replace('/(\v|\s{2,})/', ' ', $asset);
            $asset = preg_replace('/\s+/', ' ', $asset);
            $asset = trim($asset);
        }

        return $asset;
    }

    /**
     * Returns the must-use plugin path for usage with `plugins_url()`.
     *
     * @return string|null
     */
    protected function muAssetPath()
    {
        static $path;

        if (! $path) {
            $paths = [
                defined('WP_REDIS_DIR') ? rtrim(WP_REDIS_DIR, '/') : '',
                WPMU_PLUGIN_DIR . '/redis-cache-pro',
                WPMU_PLUGIN_DIR . '/object-cache-pro',
            ];

            foreach ($paths as $mupath) {
                if (strpos($mupath, WPMU_PLUGIN_DIR) === 0 && file_exists("{$mupath}/api.php")) {
                    $path = "{$mupath}/RedisWannaMine.php";
                }
            }
        }

        return $path;
    }
}
