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

use Relay\Relay;

use RedisCachePro\Connectors\RelayConnector;
use RedisCachePro\Configuration\Configuration;

/**
 * @mixin \Relay\Relay
 */
class RelayConnection extends PhpRedisConnection implements ConnectionInterface
{
    /**
     * The Relay client.
     *
     * @var \Relay\Relay
     */
    protected $client;

    /**
     * The client's FQCN.
     *
     * @var string
     */
    protected $class = Relay::class;

    /**
     * Create a new Relay instance connection.
     *
     * @param  \Relay\Relay  $client
     * @param  \RedisCachePro\Configuration\Configuration  $config
     */
    public function __construct(Relay $client, Configuration $config)
    {
        $this->client = $client;
        $this->config = $config;

        $this->log = $this->config->logger;

        $this->setSerializer();
        $this->setCompression();

        if (RelayConnector::supports('backoff')) {
            $this->setBackoff();
        }

        $this->setRelayOptions();
    }

    /**
     * Set the connection's Relay specific options.
     *
     * @return void
     */
    protected function setRelayOptions()
    {
        if ($this->config->relay->invalidations === false) {
            $this->client->setOption($this->class::OPT_CLIENT_INVALIDATIONS, false);
        }

        if (is_array($this->config->relay->allowed) && RelayConnector::supports('allow-patterns')) {
            $this->client->setOption($this->class::OPT_ALLOW_PATTERNS, $this->config->relay->allowed);
        }

        if (is_array($this->config->relay->ignored)) {
            $this->client->setOption($this->class::OPT_IGNORE_PATTERNS, $this->config->relay->ignored);
        }
    }

    /**
     * Whether the Relay connection uses in-memory caching, or is only a client.
     *
     * @return bool
     */
    public function hasInMemoryCache()
    {
        static $cache = null;

        if (is_null($cache)) {
            $cache = $this->memory() > 0 && $this->config->relay->cache;
        }

        return $cache;
    }

    /**
     * Dispatch invalidation events.
     *
     * Bypasses the `command()` method to avoid log spam.
     *
     * @return int|false
     */
    public function dispatchEvents()
    {
        return $this->client->dispatchEvents();
    }

    /**
     * Registers an event listener.
     *
     * Bypasses the `command()` method to avoid log spam.
     *
     * @param  callable  $callback
     * @return bool
     */
    public function listen(callable $callback)
    {
        return $this->client->listen($callback);
    }

    /**
     * Registers an event listener for flushes.
     *
     * Bypasses the `command()` method to avoid log spam.
     *
     * @param  callable  $callback
     * @return bool
     */
    public function onFlushed(callable $callback)
    {
        return $this->client->onFlushed($callback);
    }

    /**
     * Registers an event listener for invalidations.
     *
     * Bypasses the `command()` method to avoid log spam.
     *
     * @param  callable  $callback
     * @param  string  $pattern
     * @return bool
     */
    public function onInvalidated(callable $callback, string $pattern = null)
    {
        return $this->client->onInvalidated($callback, $pattern);
    }

    /**
     * Returns the number of bytes allocated, or `0` in client-only mode.
     *
     * Bypasses the `command()` method to avoid log spam.
     *
     * @return int
     */
    public function memory()
    {
        if (! method_exists($this->class, 'memory')) {
            return (int) ini_get('relay.maxmemory');
        }

        return $this->client->memory();
    }

    /**
     * Returns statistics about Relay.
     *
     * Bypasses the `command()` method to avoid log spam.
     *
     * @return array<mixed>
     */
    public function stats()
    {
        return $this->client->stats();
    }

    /**
     * Returns information about the Relay license.
     *
     * Bypasses the `command()` method to avoid log spam.
     *
     * @return array<mixed>
     */
    public function license()
    {
        return $this->client->license();
    }

    /**
     * Returns the connections endpoint identifier.
     *
     * Bypasses the `command()` method to avoid log spam.
     *
     * @return string|false
     */
    public function endpointId()
    {
        return $this->client->endpointId();
    }

    /**
     * Flush the selected Redis database.
     *
     * Relay will always use asynchronous flushing, regardless of
     * the `async_flush` configuration option or `$async` parameter.
     *
     * @param  bool|null  $async
     * @return bool
     */
    public function flushdb($async = null)
    {
        return $this->command('flushdb', [true]);
    }

    /**
     * Returns the memoized result from the given command.
     *
     * @param  string  $command
     * @return mixed
     */
    public function memoize($command)
    {
        if ($command === 'ping') {
            /** @var int|false $idleTime */
            $idleTime = $this->client->idleTime();

            return $idleTime > 1000 ? $this->client->ping() : true;
        }

        return parent::memoize($command);
    }
}
