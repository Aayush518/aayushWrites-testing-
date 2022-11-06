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

namespace RedisCachePro\Metrics;

use RedisCachePro\ObjectCaches\ObjectCache;

class WordPressMetrics
{
    /**
     * The amount of times the cache data was already cached in memory.
     *
     * @var int
     */
    public $hits;

    /**
     * The amount of times the cache did not have the object in memory.
     *
     * @var int
     */
    public $misses;

    /**
     * The in-memory hits-to-misses ratio.
     *
     * @var float
     */
    public $hitRatio;

    /**
     * The in-memory cache's size in bytes.
     *
     * @var int|float
     */
    public $bytes;

    /**
     * The number of valid, prefetched keys.
     *
     * @var int
     */
    public $prefetches;

    /**
     * The number of times the cache read from the external cache.
     *
     * @var int
     */
    public $storeReads;

    /**
     * The number of times the cache wrote to the external cache.
     *
     * @var int
     */
    public $storeWrites;

    /**
     * The number of times the external cache had the object already cached.
     *
     * @var int
     */
    public $storeHits;

    /**
     * The Number of times the external cache did not have the object.
     *
     * @var int
     */
    public $storeMisses;

    /**
     * The number of executed SQL queries.
     *
     * @var int|null
     */
    public $sqlQueries;

    /**
     * The amount of time (ms) WordPress took to render the request.
     *
     * @var float
     */
    public $msTotal;

    /**
     * The total amount of time (ms) waited for the external cache to respond.
     *
     * @var float
     */
    public $msCache;

    /**
     * The median amount of time (ms) waited for the external cache to respond.
     *
     * @var float
     */
    public $msCacheMedian;

    /**
     * The percentage of time waited for the external cache to respond,
     * relative to the amount of time WordPress took to render the request.
     *
     * @var float
     */
    public $msCacheRatio;

    /**
     * Creates a new instance from given object cache.
     *
     * @param  \RedisCachePro\ObjectCaches\ObjectCache  $cache
     * @return void
     */
    public function __construct(ObjectCache $cache)
    {
        global $timestart;

        /** @var \RedisCachePro\Support\PhpRedisObjectCacheMetrics $metrics */
        $metrics = $cache->metrics(true);

        $ioWait = $cache->connection()->ioWait();
        $ioWaitTotal = array_sum($ioWait);
        $ioWaitMedian = ObjectCache::array_median($ioWait);

        $this->hits = $metrics->hits;
        $this->misses = $metrics->misses;
        $this->hitRatio = $metrics->ratio;
        $this->bytes = $metrics->bytes;
        $this->prefetches = $metrics->prefetches;
        $this->storeReads = $metrics->storeReads;
        $this->storeWrites = $metrics->storeWrites;
        $this->storeHits = $metrics->storeHits;
        $this->storeMisses = $metrics->storeMisses;
        $this->msCache = round($ioWaitTotal * 1000, 2);
        $this->msCacheMedian = round($ioWaitMedian * 1000, 2);

        $requestStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? $timestart;

        if ($requestStart) {
            $this->msTotal = round((microtime(true) - $requestStart) * 1000, 2);
            $this->msCacheRatio = round(($this->msCache / $this->msTotal) * 100, 2);
        }

        $this->sqlQueries = function_exists('\get_num_queries')
            ? \get_num_queries()
            : null;
    }

    /**
     * Returns the request metrics as array.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit-ratio' => number_format($this->hitRatio, 1),
            'bytes' => $this->bytes,
            'prefetches' => $this->prefetches,
            'store-reads' => $this->storeReads,
            'store-writes' => $this->storeWrites,
            'store-hits' => $this->storeHits,
            'store-misses' => $this->storeMisses,
            'sql-queries' => $this->sqlQueries,
            'ms-total' => sprintf('%.2f', $this->msTotal),
            'ms-cache' => sprintf('%.2f', $this->msCache),
            'ms-cache-median' => sprintf('%.2f', $this->msCacheMedian),
            'ms-cache-ratio' => number_format($this->msCacheRatio, 1),
        ];
    }

    /**
     * Returns the request metrics in string format.
     *
     * @return string
     */
    public function __toString()
    {
        $metrics = $this->toArray();

        return implode(' ', array_map(function ($metric, $value) {
            return "metric#{$metric}={$value}";
        }, array_keys($metrics), $metrics));
    }

    /**
     * Returns the schema for the WordPress metrics.
     *
     * @return array<string, array<string, string>>
     */
    public static function schema()
    {
        $metrics = [
            'hits' => [
                'title' => 'Hits',
                'description' => 'The amount of times the cache data was already cached in memory.',
                'type' => 'integer',
            ],
            'misses' => [
                'title' => 'Misses',
                'description' => 'The amount of times the cache did not have the object in memory.',
                'type' => 'integer',
            ],
            'hit-ratio' => [
                'title' => 'Hit Ratio',
                'description' => 'The in-memory hits-to-misses ratio.',
                'type' => 'ratio',
            ],
            'bytes' => [
                'title' => 'Bytes',
                'description' => "The in-memory cache's size in bytes.",
                'type' => 'bytes',
            ],
            'prefetches' => [
                'title' => 'Prefetches',
                'description' => 'The number of valid, prefetched keys.',
                'type' => 'integer',
            ],
            'store-reads' => [
                'title' => 'Store Reads',
                'description' => 'The number of times the cache read from the external cache.',
                'type' => 'integer',
            ],
            'store-writes' => [
                'title' => 'Store Writes',
                'description' => 'The number of times the cache wrote to the external cache.',
                'type' => 'integer',
            ],
            'store-hits' => [
                'title' => 'Store Hits',
                'description' => 'The number of times the external cache did have the object.',
                'type' => 'integer',
            ],
            'store-misses' => [
                'title' => 'Store Misses',
                'description' => 'The number of times the external cache did not have the object.',
                'type' => 'integer',
            ],
            'sql-queries' => [
                'title' => 'SQL Queries',
                'description' => 'The number of SQL queries executed.',
                'type' => 'integer',
            ],
            'ms-total' => [
                'title' => 'Response Time',
                'description' => 'The amount of time (ms) WordPress took to render the request.',
                'type' => 'time',
            ],
            'ms-cache' => [
                'title' => 'Store Response Time',
                'description' => 'The total amount of time (ms) waited for the external cache (Redis) to respond.',
                'type' => 'time',
            ],
            'ms-cache-median' => [
                'title' => 'Store Command Time',
                'description' => 'The median amount of time (ms) waited for the external cache (Redis) to respond.',
                'type' => 'time',
            ],
            'ms-cache-ratio' => [
                'title' => 'Store Time Ratio',
                'description' => 'The percentage of time waited for the external cache to respond, relative to the amount of time WordPress took to render the request.',
                'type' => 'ratio',
            ],
        ];

        return array_map(function ($metric) {
            $metric['group'] = 'wp';

            return $metric;
        }, $metrics);
    }
}
