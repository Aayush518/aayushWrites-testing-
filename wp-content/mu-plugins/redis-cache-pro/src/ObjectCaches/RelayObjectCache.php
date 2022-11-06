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

namespace RedisCachePro\ObjectCaches;

use RedisCachePro\Connections\RelayConnection;
use RedisCachePro\Configuration\Configuration;

class RelayObjectCache extends PhpRedisObjectCache
{
    /**
     * The client name.
     *
     * @var string
     */
    const Client = 'Relay';

    /**
     * The connection instance.
     *
     * @var \RedisCachePro\Connections\RelayConnection
     */
    protected $connection;

    /**
     * Create new Relay object cache instance.
     *
     * @param  \RedisCachePro\Connections\RelayConnection  $connection
     * @param  \RedisCachePro\Configuration\Configuration  $config
     */
    public function __construct(RelayConnection $connection, Configuration $config)
    {
        $this->config = $config;
        $this->connection = $connection;
        $this->log = $this->config->logger;

        if ($this->config->relay->listeners && $this->connection->hasInMemoryCache()) {
            $this->connection->onInvalidated(
                [$this, 'invalidated'],
                $config->prefix ? "{$config->prefix}*" : null
            );

            $this->connection->onFlushed(
                [$this, 'flushed']
            );
        }
    }

    /**
     * Callback for the `invalidated` event to keep the in-memory cache in sync.
     *
     * @param  \Relay\Event  $event
     * @return void
     */
    public function invalidated($event)
    {
        $bits = explode(':', $event->key);

        $this->deleteFromMemory(...array_reverse(array_splice($bits, -2)));
    }

    /**
     * Callback for the `flushed` event to keep the in-memory cache fresh.
     *
     * @return void
     */
    public function flushed()
    {
        $this->flush_runtime();
    }

    /**
     * Returns various information about the object cache.
     *
     * @return object
     */
    public function info()
    {
        $info = parent::info();
        $stats = $this->connection->memoize('stats');

        if ($this->connection->hasInMemoryCache()) {
            $meta = [
                'Relay Cache' => 'Disabled',
            ];
        } else {
            $meta = [
                'Relay Cache' => 'Enabled',
                'Relay Memory' => sprintf(
                    '%s of %s',
                    size_format($stats['memory']['active'], 2),
                    size_format($stats['memory']['total'], 2)
                ),
                'Relay Eviction' => (string) ini_get('relay.eviction_policy'),
            ];
        }

        $info->meta = $meta + $info->meta;

        return $info;
    }
}
