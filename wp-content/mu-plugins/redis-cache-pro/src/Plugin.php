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

namespace RedisCachePro;

use Throwable;

use RedisCachePro\Diagnostics\Diagnostics;
use RedisCachePro\Configuration\Configuration;
use RedisCachePro\ObjectCaches\ObjectCacheInterface;

final class Plugin
{
    use Plugin\Extensions\Debugbar,
        Plugin\Extensions\QueryMonitor,
        Plugin\Analytics,
        Plugin\Assets,
        Plugin\Authorization,
        Plugin\Dropin,
        Plugin\Health,
        Plugin\Licensing,
        Plugin\Lifecycle,
        Plugin\Meta,
        Plugin\Network,
        Plugin\Settings,
        Plugin\Updates,
        Plugin\Widget;

    /**
     * The configuration instance.
     *
     * @var \RedisCachePro\Configuration\Configuration
     */
    protected $config;

    /**
     * Holds the plugin version number.
     *
     * @var string
     */
    protected $version;

    /**
     * Holds the plugin basename.
     *
     * @var string
     */
    protected $basename;

    /**
     * Holds the plugin directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * Holds the plugin filename.
     *
     * @var string
     */
    protected $filename;

    /**
     * Holds the plugin website.
     *
     * @var string
     */
    const Url = 'https://objectcache.pro';

    /**
     * The capability required to manage this plugin.
     *
     * @var string
     */
    const Capability = 'objectcache_manage';

    /**
     * Initialize the plugin, load all extensions and register lifecycle hooks.
     *
     * @return self
     */
    public static function boot()
    {
        global $wp_object_cache;

        $instance = new static;
        $instance->version = Version;
        $instance->basename = Basename;
        $instance->filename = Filename;
        $instance->directory = (string) realpath(__DIR__ . '/..');

        if ($wp_object_cache instanceof ObjectCacheInterface) {
            $instance->config = $wp_object_cache->config();
        } else {
            $instance->config = Configuration::safelyFrom(
                defined('\WP_REDIS_CONFIG') ? \WP_REDIS_CONFIG : []
            );
        }

        foreach ((array) class_uses($instance) as $class) {
            $name = substr((string) $class, strrpos((string) $class, '\\') + 1);

            $instance->{"boot{$name}"}();
        }

        return $instance;
    }

    /**
     * Returns the raw basename.
     *
     * @return string
     */
    public function basename()
    {
        return $this->basename;
    }

    /**
     * Returns the cleaned up basename.
     *
     * @return string
     */
    public function slug()
    {
        return strpos($this->basename, '/') === false
            ? $this->basename
            : dirname($this->basename);
    }

    /**
     * Returns the configuration instance.
     *
     * @return \RedisCachePro\Configuration\Configuration
     */
    public function config()
    {
        return $this->config;
    }

    /**
     * Returns a singleton diagnostics instance.
     *
     * @return \RedisCachePro\Diagnostics\Diagnostics
     */
    public function diagnostics()
    {
        global $wp_object_cache;

        static $diagnostics = null;

        if (! $diagnostics) {
            $diagnostics = new Diagnostics($wp_object_cache);
        }

        return $diagnostics;
    }

    /**
     * Attempt to flush the object cache on a standalone
     * connection without throwing an error.
     *
     * @return bool
     */
    public function flush()
    {
        try {
            $connection = $this->config->connector::connect($this->config);

            $this->logFlush();

            return $connection->flushdb($this->config->async_flush);
        } catch (Throwable $exception) {
            //
        }

        return false;
    }
}
