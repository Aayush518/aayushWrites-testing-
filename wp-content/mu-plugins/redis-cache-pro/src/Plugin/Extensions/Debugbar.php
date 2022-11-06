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

namespace RedisCachePro\Plugin\Extensions;

use RedisCachePro_DebugBar_Insights;
use RedisCachePro\ObjectCaches\ObjectCache;

trait Debugbar
{
    /**
     * Boot Debug Bar component and register panels and statuses.
     *
     * @return void
     */
    public function bootDebugbar()
    {
        if (! is_plugin_active('debug-bar/debug-bar.php')) {
            return;
        }

        require_once "{$this->directory}/src/Extensions/Debugbar/Panel.php";
        require_once "{$this->directory}/src/Extensions/Debugbar/Insights.php";

        add_action('debug_bar_panels', [$this, 'panels']);
        add_action('debug_bar_statuses', [$this, 'statuses']);
    }

    /**
     * Register the default diagnostics debug bar panel, as well as
     * other panels provided by the object cache.
     *
     * @param  array<mixed>  $panels
     * @return array<mixed>
     */
    public function panels($panels)
    {
        global $wp_object_cache;

        if (! $wp_object_cache instanceof ObjectCache) {
            return $panels;
        }

        $panels[] = new RedisCachePro_DebugBar_Insights(
            $wp_object_cache->info(),
            $wp_object_cache->metrics(true)
        );

        return $panels;
    }

    /**
     * Add the Redis version to the debug bar statuses.
     *
     * @param  array<string, mixed>  $statuses
     * @return array<string, mixed>
     */
    public function statuses($statuses)
    {
        $diagnostics = $this->diagnostics()->toArray();

        $version = $diagnostics['versions']['redis'];
        $memory = $diagnostics['statistics']['redis-memory'];

        if ($version->value) {
            $position = array_search('db', array_column($statuses, 0));

            array_splice($statuses, $position + 1, 0, [['redis', 'Redis', $version]]);
        }

        if ($memory->value) {
            $position = array_search('redis-memory', array_column($statuses, 0));

            array_splice($statuses, $position + 1, 0, [['redis-memory', 'Redis Memory', $memory]]);
        }

        return $statuses;
    }
}
