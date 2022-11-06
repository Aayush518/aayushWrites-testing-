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
use WP_CLI\Formatter;

use cli\Notify;
use cli\Streams;

use RedisCachePro\Metrics\Measurements;

class DigestWatcher extends Notify
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
     * The calculated metric items.
     *
     * @var array<mixed>|null
     */
    protected $items;

    /**
     * The characters used for the spinner.
     *
     * @var string
     */
    protected $chars = '-\|/';

    /**
     * Holds the current iteration.
     *
     * @var int
     */
    protected $iteration = 0;

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
        'sql-queries',
        'redis-hit-ratio',
        'redis-ops-per-sec',
        'redis-keys',
        'relay-hits',
        'relay-keys',
        'relay-misses',
        'relay-memory-human',
    ];

    /**
     * Prints the metrics table to the screen.
     *
     * @param  bool  $finish
     * @return void
     */
    public function display($finish = false)
    {
        $idx = $this->iteration++ % 4;
        $lines = 4 + count($this->items);

        $arguments = ['format' => 'table'];
        $fields = ['Metric', 'Median'];

        ob_start();

        $formatter = new Formatter($arguments, $fields);
        $formatter->display_items($this->items, true);

        Streams::out((string) ob_get_clean());

        Streams::out(WP_CLI::colorize('{:msg} %g{:char}%n'), [
            'msg' => WP_CLI::colorize($this->_message),
            'char' => $this->chars[$idx],
        ]);

        Streams::out("\e[{$lines}A");
        Streams::out("\e[0G");
    }

    /**
     * Prepare the metrics.
     *
     * @return void
     */
    public function prepare()
    {
        $this->items = null;

        $metrics = empty($this->options['metrics'])
            ? $this->defaultMetrics
            : $this->options['metrics'];

        $measurements = $this->cache->measurements(
            strval(microtime(true) - $this->options['seconds'])
        );

        foreach ($metrics as $metric) {
            $method = 'get' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $metric)));

            if (! method_exists($this, $method)) {
                WP_CLI::error("Invalid metric name: {$metric}.");
            }

            $item = $this->{$method}($measurements);
            $item->Median = str_pad((string) $item->Median, 20, ' ', STR_PAD_LEFT);

            $this->items[] = $item;
        }
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getHits(Measurements $measurements)
    {
        $hitsMedian = $measurements->median('wp->hits');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Hits'),
            'Median' => is_null($hitsMedian) ? '' : number_format($hitsMedian),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getMisses(Measurements $measurements)
    {
        $missesMedian = $measurements->median('wp->misses');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Misses'),
            'Median' => is_null($missesMedian) ? '' : number_format($missesMedian),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getHitRatio(Measurements $measurements)
    {
        $hitRatioMedian = $measurements->median('wp->hitRatio');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Hit ratio'),
            'Median' => is_null($hitRatioMedian) ? '' : number_format($hitRatioMedian, 1) . '%',
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getBytes(Measurements $measurements)
    {
        $bytesMedian = $measurements->median('wp->bytes');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Size'),
            'Median' => is_null($bytesMedian) ? '' : size_format($bytesMedian, 1),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getPrefetches(Measurements $measurements)
    {
        $prefetchesMedian = $measurements->median('wp->prefetches');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Reads'),
            'Median' => is_null($prefetchesMedian) ? '' : number_format($prefetchesMedian),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getStoreReads(Measurements $measurements)
    {
        $storeReadsMedian = $measurements->median('wp->storeReads');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Reads'),
            'Median' => is_null($storeReadsMedian) ? '' : number_format($storeReadsMedian),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getStoreWrites(Measurements $measurements)
    {
        $storeWritesMedian = $measurements->median('wp->storeWrites');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Writes'),
            'Median' => is_null($storeWritesMedian) ? '' : number_format($storeWritesMedian),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getStoreHits(Measurements $measurements)
    {
        $storeHitsMedian = $measurements->median('wp->storeHits');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Hits'),
            'Median' => is_null($storeHitsMedian) ? '' : number_format($storeHitsMedian),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getStoreMisses(Measurements $measurements)
    {
        $storeMissesMedian = $measurements->median('wp->storeMisses');

        return (object) [
            'Metric' => WP_CLI::colorize('%bCache%n: Misses'),
            'Median' => is_null($storeMissesMedian) ? '' : number_format($storeMissesMedian),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getSqlQueries(Measurements $measurements)
    {
        $sqlQueriesMedian = $measurements->median('wp->sqlQueries');

        return (object) [
            'Metric' => WP_CLI::colorize('%ySQL%n: Queries'),
            'Median' => is_null($sqlQueriesMedian) ? '' : number_format($sqlQueriesMedian),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getMsTotal(Measurements $measurements)
    {
        $msTotalMedian = $measurements->median('wp->msTotal');

        return (object) [
            'Metric' => WP_CLI::colorize('%cTime%n: Request'),
            'Median' => is_null($msTotalMedian) ? '' : number_format($msTotalMedian, 2) . ' ms',
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getMsCache(Measurements $measurements)
    {
        $msCacheMedian = $measurements->median('wp->msCache');

        return (object) [
            'Metric' => WP_CLI::colorize('%cTime%n: Cache'),
            'Median' => is_null($msCacheMedian) ? '' : number_format($msCacheMedian, 2) . ' ms',
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getMsCacheRatio(Measurements $measurements)
    {
        $msCacheRatioMedian = $measurements->median('wp->msCacheRatio');

        return (object) [
            'Metric' => WP_CLI::colorize('%cTime%n: Cache ratio'),
            'Median' => is_null($msCacheRatioMedian) ? '' : number_format($msCacheRatioMedian, 2) . '%',
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisHits(Measurements $measurements)
    {
        $hits = $measurements->median('redis->hits');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Hits'),
            'Median' => is_null($hits) ? '' : number_format($hits),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisMisses(Measurements $measurements)
    {
        $misses = $measurements->median('redis->misses');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Misses'),
            'Median' => is_null($misses) ? '' : number_format($misses),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisHitRatio(Measurements $measurements)
    {
        $hitRatioMedian = $measurements->median('redis->hitRatio');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Hit ratio'),
            'Median' => is_null($hitRatioMedian) ? '' : number_format($hitRatioMedian, 1) . '%',
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisOpsPerSec(Measurements $measurements)
    {
        $opsPerSec = $measurements->latest('redis->opsPerSec');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Operations'),
            'Median' => is_null($opsPerSec) ? '' : number_format($opsPerSec) . ' ops/s',
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisEvictedKeys(Measurements $measurements)
    {
        $evictedKeys = $measurements->latest('redis->evictedKeys');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Evicted keys'),
            'Median' => is_null($evictedKeys) ? '' : number_format($evictedKeys),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisUsedMemory(Measurements $measurements)
    {
        $usedMemory = $measurements->latest('redis->usedMemory');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Used memory'),
            'Median' => is_null($usedMemory) ? '' : number_format($usedMemory),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisUsedMemoryRss(Measurements $measurements)
    {
        $usedMemoryRss = $measurements->latest('redis->usedMemoryRss');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Used memory RSS'),
            'Median' => is_null($usedMemoryRss) ? '' : number_format($usedMemoryRss),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisMemoryFragmentationRatio(Measurements $measurements)
    {
        $memoryFragmentationRatio = $measurements->latest('redis->memoryFragmentationRatio');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Memory Frag. Ratio'),
            'Median' => is_null($memoryFragmentationRatio) ? '' : number_format($memoryFragmentationRatio, 1) . '%',
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisConnectedClients(Measurements $measurements)
    {
        $connectedClients = $measurements->latest('redis->connectedClients');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Connected clients'),
            'Median' => is_null($connectedClients) ? '' : number_format($connectedClients),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisTrackingClients(Measurements $measurements)
    {
        $trackingClients = $measurements->latest('redis->trackingClients');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Tracking clients'),
            'Median' => is_null($trackingClients) ? '' : number_format($trackingClients),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisRejectedConnections(Measurements $measurements)
    {
        $rejectedConnections = $measurements->latest('redis->rejectedConnections');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Rejected connections'),
            'Median' => is_null($rejectedConnections) ? '' : number_format($rejectedConnections),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRedisKeys(Measurements $measurements)
    {
        $keys = $measurements->latest('redis->keys');

        return (object) [
            'Metric' => WP_CLI::colorize('%rRedis%n: Keys'),
            'Median' => is_null($keys) ? '' : number_format($keys),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRelayHits(Measurements $measurements)
    {
        $hits = $this->usingRelay
            ? $measurements->latest('relay->hits')
            : null;

        return (object) [
            'Metric' => WP_CLI::colorize('%pRelay%n: Hits'),
            'Median' => is_null($hits) ? '' : number_format($hits),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRelayMisses(Measurements $measurements)
    {
        $misses = $this->usingRelay
            ? $measurements->latest('relay->misses')
            : null;

        return (object) [
            'Metric' => WP_CLI::colorize('%pRelay%n: Misses'),
            'Median' => is_null($misses) ? '' : number_format($misses),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRelayKeys(Measurements $measurements)
    {
        $keys = $measurements->latest('relay->keys');

        return (object) [
            'Metric' => WP_CLI::colorize('%pRelay%n: Keys'),
            'Median' => is_null($keys) ? '' : number_format($keys),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRelayMemoryActive(Measurements $measurements)
    {
        $memoryActive = $this->usingRelay
            ? $measurements->latest('relay->memoryActive')
            : null;

        return (object) [
            'Metric' => WP_CLI::colorize('%pRelay%n: Memory active'),
            'Median' => is_null($memoryActive) ? '' : size_format($memoryActive),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRelayMemoryTotal(Measurements $measurements)
    {
        $memoryTotal = $this->usingRelay
            ? $measurements->latest('relay->memoryTotal')
            : null;

        return (object) [
            'Metric' => WP_CLI::colorize('%pRelay%n: Memory total'),
            'Median' => is_null($memoryTotal) ? '' : size_format($memoryTotal),
        ];
    }

    /**
     * @param  \RedisCachePro\Metrics\Measurements  $measurements
     * @return object
     */
    protected function getRelayMemoryHuman(Measurements $measurements)
    {
        $memoryHuman = null;

        if ($this->usingRelay) {
            $memoryTotal = $measurements->latest('relay->memoryTotal');
            $memoryActive = $measurements->latest('relay->memoryActive');

            if ($memoryActive && $memoryTotal) {
                $memoryHuman = sprintf(
                    '%s / %s',
                    size_format($memoryActive),
                    size_format($memoryTotal)
                );
            }
        }

        return (object) [
            'Metric' => WP_CLI::colorize('%pRelay%n: Memory'),
            'Median' => is_null($memoryHuman) ? '' : $memoryHuman,
        ];
    }
}
