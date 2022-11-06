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

class RedisMetrics
{
    /**
     * Number of successful key lookups.
     *
     * @var int
     */
    public $hits;

    /**
     * Number of failed key lookups.
     *
     * @var int
     */
    public $misses;

    /**
     * The hits-to-misses ratio.
     *
     * @var float
     */
    public $hitRatio;

    /**
     * Number of commands processed per second.
     *
     * @var int
     */
    public $opsPerSec;

    /**
     * Number of evicted keys due to `maxmemory` limit.
     *
     * @var int
     */
    public $evictedKeys;

    /**
     * Total number of bytes allocated by Redis using its allocator.
     *
     * @var int
     */
    public $usedMemory;

    /**
     * Number of bytes that Redis allocated as seen by the operating system.
     *
     * @var int
     */
    public $usedMemoryRss;

    /**
     * The ratio of memory allocated by Redis compared to
     * the maximum amount of memory allocatable by Redis.
     *
     * @var int
     */
    public $memoryRatio;

    /**
     * The ratio of memory used by the OS compared to
     * the amount of memory allocated by Redis.
     *
     * @var float
     */
    public $memoryFragmentationRatio;

    /**
     * Number of client connections (excluding connections from replicas).
     *
     * @var int
     */
    public $connectedClients;

    /**
     * Number of clients being tracked.
     *
     * @var int
     */
    public $trackingClients;

    /**
     * Number of connections rejected because of `maxclients` limit.
     *
     * @var int
     */
    public $rejectedConnections;

    /**
     * The number of keys in the keyspace (database).
     *
     * @var int
     */
    public $keys;

    /**
     * Creates a new instance from given object cache.
     *
     * @param  \RedisCachePro\ObjectCaches\ObjectCache  $cache
     * @return void
     */
    public function __construct(ObjectCache $cache)
    {
        $info = $cache->connection()->memoize('info');
        $total = intval($info['keyspace_hits'] + $info['keyspace_misses']);

        $this->hits = $info['keyspace_hits'];
        $this->misses = $info['keyspace_misses'];
        $this->hitRatio = $total > 0 ? round($this->hits / ($total / 100), 2) : 100;
        $this->opsPerSec = $info['instantaneous_ops_per_sec'];
        $this->evictedKeys = $info['evicted_keys'];
        $this->usedMemory = $info['used_memory'];
        $this->usedMemoryRss = $info['used_memory_rss'];
        $this->memoryRatio = empty($info['maxmemory']) ? 0 : ($info['used_memory'] / $info['maxmemory']) * 100;
        $this->memoryFragmentationRatio = $info['mem_fragmentation_ratio'] ?? 0;
        $this->connectedClients = $info['connected_clients'];
        $this->trackingClients = $info['tracking_clients'] ?? 0;
        $this->rejectedConnections = $info['rejected_connections'];

        $dbKey = "db{$cache->config()->database}";

        if (isset($info[$dbKey])) {
            $keyspace = array_column(array_map(function ($value) {
                return explode('=', $value);
            }, explode(',', $info[$dbKey])), 1, 0);

            $this->keys = intval($keyspace['keys']);
        }
    }

    /**
     * Returns the Redis metrics as array.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit-ratio' => sprintf('%.1f', $this->hitRatio),
            'ops-per-sec' => $this->opsPerSec,
            'evicted-keys' => $this->evictedKeys,
            'used-memory' => $this->usedMemory,
            'used-memory-rss' => $this->usedMemoryRss,
            'memory-fragmentation-ratio' => sprintf('%.1f', $this->memoryFragmentationRatio),
            'connected-clients' => $this->connectedClients,
            'tracking-clients' => $this->trackingClients,
            'rejected-connections' => $this->rejectedConnections,
            'keys' => $this->keys,
        ];
    }

    /**
     * Returns the Redis metrics in string format.
     *
     * @return string
     */
    public function __toString()
    {
        $metrics = $this->toArray();

        return implode(' ', array_map(function ($metric, $value) {
            return "sample#redis-{$metric}={$value}";
        }, array_keys($metrics), $metrics));
    }

    /**
     * Returns the schema for the Redis metrics.
     *
     * @return array<string, array<string, string>>
     */
    public static function schema()
    {
        $metrics = [
            'redis-hits' => [
                'title' => 'Hits',
                'description' => 'Number of successful key lookups.',
                'type' => 'integer',
            ],
            'redis-misses' => [
                'title' => 'Misses',
                'description' => 'Number of failed key lookups.',
                'type' => 'integer',
            ],
            'redis-hit-ratio' => [
                'title' => 'Hit ratio',
                'description' => 'The hits-to-misses ratio.',
                'type' => 'ratio',
            ],
            'redis-ops-per-sec' => [
                'title' => 'Throughput',
                'description' => 'Number of commands processed per second.',
                'type' => 'integer',
            ],
            'redis-evicted-keys' => [
                'title' => 'Evicted keys',
                'description' => 'Number of evicted keys due to `maxmemory` limit.',
                'type' => 'integer',
            ],
            'redis-used-memory' => [
                'title' => 'Used memory',
                'description' => 'Total number of bytes allocated by Redis using its allocator.',
                'type' => 'bytes',
            ],
            'redis-used-memory-rss' => [
                'title' => 'Used-memory-rss',
                'description' => 'Number of bytes that Redis allocated as seen by the operating system.',
                'type' => 'bytes',
            ],
            'redis-memory-ratio' => [
                'title' => 'Memory ratio',
                'description' => 'The ratio of memory allocated by Redis compared to the maximum amount of memory allocatable by Redis.',
                'type' => 'ratio',
            ],
            'redis-memory-fragmentation-ratio' => [
                'title' => 'Memory fragmentation ratio',
                'description' => 'The ratio of memory used by the OS compared to the amount of memory allocated by Redis.',
                'type' => 'ratio',
            ],
            'redis-connected-clients' => [
                'title' => 'Connected clients',
                'description' => 'Number of client connections (excluding connections from replicas).',
                'type' => 'integer',
            ],
            'redis-tracking-clients' => [
                'title' => 'Tracking clients',
                'description' => 'Number of clients being tracked.',
                'type' => 'integer',
            ],
            'redis-rejected-connections' => [
                'title' => 'Rejected connections',
                'description' => 'Number of connections rejected because of `maxclients` limit.',
                'type' => 'integer',
            ],
            'redis-keys' => [
                'title' => 'Keys',
                'description' => 'The number of keys in the keyspace (database).',
                'type' => 'integer',
            ],
        ];

        return array_map(function ($metric) {
            $metric['group'] = 'redis';

            return $metric;
        }, $metrics);
    }
}
