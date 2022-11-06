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

use RedisCachePro\Connections\RelayConnection;
use RedisCachePro\Configuration\Configuration;

class RelayMetrics
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
     * The number of keys in Relay for the current database.
     *
     * @var int|null
     */
    public $keys;

    /**
     * The amount of memory actually pointing to live objects.
     *
     * @var float
     */
    public $memoryActive;

    /**
     * The total number of bytes allocated by Relay.
     *
     * @var int
     */
    public $memoryTotal;

    /**
     * The ratio of total memory allocated by Relay compared to
     * the amount of memory actually pointing to live objects.
     *
     * @var float
     */
    public $memoryRatio;

    /**
     * Creates a new instance from given connection.
     *
     * @param  \RedisCachePro\Connections\RelayConnection  $connection
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return void
     */
    public function __construct(RelayConnection $connection, Configuration $config)
    {
        $stats = $connection->memoize('stats');
        $total = intval($stats['stats']['hits'] + $stats['stats']['misses']);

        $keys = array_sum(array_map(function ($connection) use ($config) {
            return $connection['keys'][$config->database] ?? 0;
        }, $stats['endpoints'][$connection->endpointId()]['connections'] ?? [])) ?: null;

        $this->hits = $stats['stats']['hits'];
        $this->misses = $stats['stats']['misses'];
        $this->hitRatio = $total > 0 ? round($this->hits / ($total / 100), 2) : 100;
        $this->opsPerSec = $stats['stats']['ops_per_sec'];
        $this->keys = is_null($keys) ? null : (int) $keys;
        $this->memoryTotal = $stats['memory']['total'];
        $this->memoryActive = $stats['memory']['active'];
        $this->memoryRatio = round(($this->memoryActive / $this->memoryTotal) * 100, 2);
    }

    /**
     * Returns the Relay metrics as array.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit-ratio' => $this->hitRatio,
            'ops-per-sec' => $this->opsPerSec,
            'keys' => $this->keys,
            'memory-active' => $this->memoryActive,
            'memory-total' => $this->memoryTotal,
            'memory-ratio' => $this->memoryRatio,
        ];
    }

    /**
     * Returns the Relay metrics in string format.
     *
     * @return string
     */
    public function __toString()
    {
        $metrics = $this->toArray();

        return implode(' ', array_map(function ($metric, $value) {
            return "sample#relay-{$metric}={$value}";
        }, array_keys($metrics), $metrics));
    }

    /**
     * Returns the schema for the Relay metrics.
     *
     * @return array<string, array<string, string>>
     */
    public static function schema()
    {
        $metrics = [
            'relay-hits' => [
                'title' => 'Hits',
                'description' => 'Number of successful key lookups.',
                'type' => 'integer',
            ],
            'relay-misses' => [
                'title' => 'Misses',
                'description' => 'Number of failed key lookups.',
                'type' => 'integer',
            ],
            'relay-hit-ratio' => [
                'title' => 'Hit ratio',
                'description' => 'The hits-to-misses ratio.',
                'type' => 'ratio',
            ],
            'relay-ops-per-sec' => [
                'title' => 'Throughput',
                'description' => 'Number of commands processed per second.',
                'type' => 'integer',
            ],
            'relay-keys' => [
                'title' => 'Keys',
                'description' => 'The number of keys in Relay for the current database.',
                'type' => 'integer',
            ],
            'relay-memory-active' => [
                'title' => 'Active memory',
                'description' => 'The total amount of memory mapped into the allocator.',
                'type' => 'bytes',
            ],
            'relay-memory-total' => [
                'title' => 'Total memory',
                'description' => 'The total bytes of allocated memory by Relay.',
                'type' => 'bytes',
            ],
            'relay-memory-ratio' => [
                'title' => 'Memory ratio',
                'description' => 'The ratio of bytes of allocated memory by Relay compared to the total amount of memory mapped into the allocator.',
                'type' => 'ratio',
            ],
        ];

        return array_map(function ($metric) {
            $metric['group'] = 'relay';

            return $metric;
        }, $metrics);
    }
}
