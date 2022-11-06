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

namespace RedisCachePro\Console\Watchers;

use WP_CLI;
use cli\Notify;
use cli\Streams;

class AggregateWatcher extends Notify
{
    /**
     * Holds the command options.
     *
     * @var array<mixed>
     */
    public $options;

    /**
     * The object cache instance.
     *
     * @var \RedisCachePro\ObjectCaches\MeasuredObjectCacheInterface
     */
    public $cache;

    /**
     * Whether Relay is being used.
     *
     * @var bool
     */
    public $usingRelay;

    /**
     * Holds the timestamp of the next aggregate.
     *
     * @var int
     */
    protected $next;

    /**
     * The measurements to display.
     *
     * @var \RedisCachePro\Metrics\Measurements|null
     */
    protected $measurements;

    /**
     * Holds the default metrics.
     *
     * @var array<string>
     */
    protected $defaultMetrics = [
        'ms-total',
        'ms-cache',
        'ms-cache-ratio',
        'hits',
        'misses',
        'hit-ratio',
        'store-reads',
        'store-writes',
        'store-hits',
        'store-misses',
        'sql-queries',
        'redis-hit-ratio',
        'redis-ops-per-sec',
        'redis-memory-ratio',
        'relay-hits',
        'relay-misses',
        'relay-memory-ratio',
    ];

    /**
     * Prints the metrics to the screen.
     *
     * @param  bool  $finish
     * @return void
     */
    public function display($finish = false)
    {
        if ($this->_current === 1) {
            Streams::line(WP_CLI::colorize($this->_message));
        }

        if (! $this->measurements || ! $this->measurements->count()) {
            return;
        }

        $metrics = empty($this->options['metrics'])
            ? $this->defaultMetrics
            : $this->options['metrics'];

        $data = [
            $this->format('measurements', $this->measurements->count()),
        ];

        foreach ($metrics as $metric) {
            $method = 'get' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $metric)));

            if (! method_exists($this, $method)) {
                WP_CLI::error("Invalid metric name: {$metric}.");
            }

            $data[] = $this->format($metric, $this->{$method}());
        }

        /** @var array<string> $data */
        $data = array_filter($data);

        Streams::line(implode(' ', $data));

        $this->measurements = null;
    }

    /**
     * Prepare the metrics.
     *
     * @return void
     */
    public function prepare()
    {
        $now = time();
        $window = $this->options['seconds'];

        if (! $this->next) {
            $this->next = $now + $window;
        }

        if ($now < $this->next) {
            return;
        }

        $this->next = $now + $window;

        $max = $now - 2;
        $min = $max - $window;

        $this->measurements = $this->cache->measurements($min, "({$max}");
    }

    /**
     * Format the given measurement in log format.
     *
     * @param  string  $metric
     * @param  mixed  $value
     * @return string|void
     */
    protected function format(string $metric, $value)
    {
        if (is_null($value)) {
            return;
        }

        $format = "\e[%sm%s\e[0m\e[2m=\e[0m%s";

        switch (strstr($metric, '-', true) ?: $metric) {
            case 'redis':
                return sprintf($format, 31, $metric, $value);
            case 'relay':
                return sprintf($format, 35, $metric, $value);
            case 'ms':
                return sprintf($format, 36, $metric, $value);
            case 'sql':
                return sprintf($format, 33, $metric, $value);
            case 'measurements':
                return sprintf($format, 32, $metric, $value);
            default:
                return sprintf($format, 34, $metric, $value);
        }
    }

    /**
     * @return int
     */
    protected function getHits()
    {
        return (int) round($this->measurements->median('wp->hits'));
    }

    /**
     * @return int
     */
    protected function getMisses()
    {
        return (int) round($this->measurements->median('wp->misses'));
    }

    /**
     * @return string|null
     */
    protected function getHitRatio()
    {
        $hitRatioMedian = $this->measurements->median('wp->hitRatio');

        return is_null($hitRatioMedian) ? null : number_format($hitRatioMedian, 1);
    }

    /**
     * @return int
     */
    protected function getBytes()
    {
        return (int) round($this->measurements->median('wp->bytes'));
    }

    /**
     * @return int
     */
    protected function getPrefetches()
    {
        return (int) round($this->measurements->median('wp->prefetches'));
    }

    /**
     * @return int
     */
    protected function getStoreReads()
    {
        return (int) round($this->measurements->median('wp->storeReads'));
    }

    /**
     * @return int
     */
    protected function getStoreWrites()
    {
        return (int) round($this->measurements->median('wp->storeWrites'));
    }

    /**
     * @return int
     */
    protected function getStoreHits()
    {
        return (int) round($this->measurements->median('wp->storeHits'));
    }

    /**
     * @return int
     */
    protected function getStoreMisses()
    {
        return (int) round($this->measurements->median('wp->storeMisses'));
    }

    /**
     * @return int
     */
    protected function getSqlQueries()
    {
        return (int) round($this->measurements->median('wp->sqlQueries'));
    }

    /**
     * @return string|null
     */
    protected function getMsTotal()
    {
        $msTotalMedian = $this->measurements->median('wp->msTotal');

        return is_null($msTotalMedian) ? null : number_format($msTotalMedian, 2, '.', '');
    }

    /**
     * @return string|null
     */
    protected function getMsCache()
    {
        $msCacheMedian = $this->measurements->median('wp->msCache');

        return is_null($msCacheMedian) ? null : number_format($msCacheMedian, 2, '.', '');
    }

    /**
     * @return string|null
     */
    protected function getMsCacheRatio()
    {
        $msCacheRatioMedian = $this->measurements->median('wp->msCacheRatio');

        return is_null($msCacheRatioMedian) ? null : number_format($msCacheRatioMedian, 2, '.', '');
    }

    /**
     * @return int
     */
    protected function getRedisHits()
    {
        return (int) round($this->measurements->median('redis->hits'));
    }

    /**
     * @return int
     */
    protected function getRedisMisses()
    {
        return (int) round($this->measurements->median('redis->misses'));
    }

    /**
     * @return string|null
     */
    protected function getRedisHitRatio()
    {
        $hitRatioMedian = $this->measurements->median('redis->hitRatio');

        return is_null($hitRatioMedian) ? null : number_format($hitRatioMedian, 1);
    }

    /**
     * @return int|null
     */
    protected function getRedisOpsPerSec()
    {
        $opsPerSec = $this->measurements->latest('redis->opsPerSec');

        return is_null($opsPerSec) ? null : (int) round($opsPerSec);
    }

    /**
     * @return int|null
     */
    protected function getRedisEvictedKeys()
    {
        $evictedKeys = $this->measurements->latest('redis->evictedKeys');

        return is_null($evictedKeys) ? null : (int) round($evictedKeys);
    }

    /**
     * @return int|null
     */
    protected function getRedisUsedMemory()
    {
        $usedMemory = $this->measurements->latest('redis->usedMemory');

        return is_null($usedMemory) ? null : (int) round($usedMemory);
    }

    /**
     * @return int|null
     */
    protected function getRedisUsedMemoryRss()
    {
        $usedMemoryRss = $this->measurements->latest('redis->usedMemoryRss');

        return is_null($usedMemoryRss) ? null : (int) round($usedMemoryRss);
    }

    /**
     * @return string|null
     */
    protected function getRedisMemoryRatio()
    {
        $memoryRatio = $this->measurements->latest('redis->memoryRatio');

        return is_null($memoryRatio) ? null : number_format($memoryRatio, 1);
    }

    /**
     * @return string|null
     */
    protected function getRedisMemoryFragmentationRatio()
    {
        $memoryFragmentationRatio = $this->measurements->latest('redis->memoryFragmentationRatio');

        return is_null($memoryFragmentationRatio) ? null : number_format($memoryFragmentationRatio, 1);
    }

    /**
     * @return int|null
     */
    protected function getRedisConnectedClients()
    {
        $connectedClients = $this->measurements->latest('redis->connectedClients');

        return is_null($connectedClients) ? null : (int) round($connectedClients);
    }

    /**
     * @return int|null
     */
    protected function getRedisTrackingClients()
    {
        $trackingClients = $this->measurements->latest('redis->trackingClients');

        return is_null($trackingClients) ? null : (int) round($trackingClients);
    }

    /**
     * @return int|null
     */
    protected function getRedisRejectedConnections()
    {
        $rejectedConnections = $this->measurements->latest('redis->rejectedConnections');

        return is_null($rejectedConnections) ? null : (int) round($rejectedConnections);
    }

    /**
     * @return int|null
     */
    protected function getRedisKeys()
    {
        $keys = $this->measurements->latest('redis->keys');

        return is_null($keys) ? null : (int) round($keys);
    }

    /**
     * @return int|void
     */
    protected function getRelayHits()
    {
        if ($this->usingRelay) {
            $hits = $this->measurements->latest('relay->hits');

            return is_null($hits) ? null : (int) round($hits);
        }
    }

    /**
     * @return int|void
     */
    protected function getRelayMisses()
    {
        if ($this->usingRelay) {
            $misses = $this->measurements->latest('relay->misses');

            return is_null($misses) ? null : (int) round($misses);
        }
    }

    /**
     * @return int|void
     */
    protected function getRelayMemoryActive()
    {
        if ($this->usingRelay) {
            $memoryActive = $this->measurements->latest('relay->memoryActive');

            return is_null($memoryActive) ? null : (int) round($memoryActive);
        }
    }

    /**
     * @return int|void
     */
    protected function getRelayMemoryTotal()
    {
        if ($this->usingRelay) {
            $memoryTotal = $this->measurements->latest('relay->memoryTotal');

            return is_null($memoryTotal) ? null : (int) round($memoryTotal);
        }
    }

    /**
     * @return string|void
     */
    protected function getRelayMemoryRatio()
    {
        if ($this->usingRelay) {
            $memoryRatio = $this->measurements->latest('relay->memoryRatio');

            return is_null($memoryRatio) ? null : number_format($memoryRatio, 1);
        }
    }
}
