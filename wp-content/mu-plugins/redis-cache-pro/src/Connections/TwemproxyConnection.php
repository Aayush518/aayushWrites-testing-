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

namespace RedisCachePro\Connections;

use RedisCachePro\Connections\ConnectionInterface;

class TwemproxyConnection extends PhpRedisConnection implements ConnectionInterface
{
    /**
     * The connection.
     *
     * @var \RedisCachePro\Connections\ConnectionInterface
     */
    protected $connection;

    /**
     * Create a new PhpRedis instance connection.
     *
     * @param  \RedisCachePro\Connections\ConnectionInterface  $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Run a command against Redis.
     *
     * @param  string  $name
     * @param  array<mixed>  $parameters
     * @return mixed
     */
    public function command(string $name, array $parameters = [])
    {
        if ($name === 'info') {
            return function_exists('\twemproxy_info')
                ? \twemproxy_info()
                : [
                    'redis_version' => '1.0.0',
                    'maxmemory_policy' => 'noeviction',
                    'used_memory' => 0,
                    'used_memory_rss' => 0,
                    'keyspace_hits' => 0,
                    'keyspace_misses' => 0,
                    'instantaneous_ops_per_sec' => 0,
                    'evicted_keys' => 0,
                    'mem_fragmentation_ratio' => 0,
                    'connected_clients' => 0,
                    'rejected_connections' => 0,
                ];
        }

        return $this->connection->command($name, $parameters);
    }
}
