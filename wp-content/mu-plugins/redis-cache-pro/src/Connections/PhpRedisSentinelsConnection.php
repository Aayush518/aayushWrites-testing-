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

use Throwable;

use RedisSentinel;

use RedisCachePro\Configuration\Configuration;
use RedisCachePro\Connectors\PhpRedisConnector;
use RedisCachePro\Exceptions\ConnectionException;

class PhpRedisSentinelsConnection extends PhpRedisReplicatedConnection implements ConnectionInterface
{
    /**
     * The current Sentinel node.
     *
     * @var string
     */
    protected $sentinel;

    /**
     * Holds all Sentinel states and URLs.
     *
     * If the state is `null` no connection has been established.
     * If the state is `false` the connection failed or a timeout occurred.
     * If the state is a `RedisSentinel` object it's the current Sentinel node.
     *
     * @var array<mixed>
     */
    protected $sentinels;

    /**
     * Create a new PhpRedis Sentinel connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     */
    public function __construct(Configuration $config)
    {
        $this->config = $config;

        foreach ($config->sentinels as $sentinel) {
            $this->sentinels[$sentinel] = null;
        }

        $this->connectToSentinels();
    }

    /**
     * Connect to the first available Sentinel.
     *
     * @return void
     */
    protected function connectToSentinels()
    {
        if ($this->sentinel) {
            $this->sentinels[$this->sentinel] = false;
        }

        foreach ($this->sentinels as $url => $state) {
            unset($this->sentinel, $this->master, $this->replicas, $this->pool);

            if (! is_null($state)) {
                continue;
            }

            try {
                $this->sentinel = $url;
                $this->establishConnections($url);
                $this->setPool();

                return;
            } catch (Throwable $th) {
                $this->sentinels[$url] = false;

                if ($this->config->debug) {
                    error_log('objectcache.notice: ' . $th->getMessage());
                }
            }
        }

        throw new ConnectionException('Unable to connect to any valid sentinels');
    }

    /**
     * Establish a connection to the given Redis Sentinel and its master and replicas.
     *
     * @param  string  $url
     * @return void
     */
    protected function establishConnections(string $url)
    {
        $config = clone $this->config;
        $config->setUrl($url);

        $persistentId = '';

        $parameters = [
            $config->host,
            $config->port,
            $config->timeout,
            $persistentId,
            $config->retry_interval,
            $config->read_timeout,
        ];

        if ($config->password) {
            $parameters[] = $config->username
                ? [$config->username, $config->password]
                : $config->password;
        }

        $this->sentinels[$url] = new RedisSentinel(...$parameters);

        $this->discoverMaster();
        $this->discoverReplicas();
    }

    /**
     * Discovers and connects to the Sentinel master.
     *
     * @return void
     */
    protected function discoverMaster()
    {
        $master = $this->sentinel()->getMasterAddrByName($this->config->service);

        if (! $master) {
            throw new ConnectionException("Failed to retrieve sentinel master of `{$this->sentinel}`");
        }

        $config = clone $this->config;
        $config->setHost($master[0]);
        $config->setPort($master[1]);

        $connection = PhpRedisConnector::connectToInstance($config);

        /** @var array<int, mixed> $role */
        $role = $connection->role();

        if (($role[0] ?? null) !== 'master') {
            throw new ConnectionException("Sentinel master of `{$this->sentinel}` is not a master");
        }

        $this->master = $connection;
    }

    /**
     * Discovers and connects to the Sentinel replicas.
     *
     * @return void
     */
    protected function discoverReplicas()
    {
        $replicas = $this->sentinel()->slaves($this->config->service);

        if (! $replicas) {
            throw new ConnectionException("Failed to discover Sentinel replicas of `{$this->sentinel}`");
        }

        foreach ($replicas as $replica) {
            if (($replica['role-reported'] ?? '') !== 'slave') {
                continue;
            }

            $config = clone $this->config;
            $config->setHost($replica['ip']);
            $config->setPort($replica['port']);

            $this->replicas[$replica['name']] = PhpRedisConnector::connectToInstance($config);
        }
    }

    /**
     * Returns the current Sentinel connection.
     *
     * @return \RedisSentinel
     */
    public function sentinel()
    {
        return $this->sentinels[$this->sentinel];
    }

    /**
     * Returns the current Sentinel's URL.
     *
     * @return string
     */
    public function sentinelUrl()
    {
        return $this->sentinel;
    }

    /**
     * Run a command against Redis Sentinel.
     *
     * @param  string  $name
     * @param  array<mixed>  $parameters
     * @return mixed
     */
    public function command(string $name, array $parameters = [])
    {
        $isReading = \in_array(\strtoupper($name), self::READ_COMMANDS);

        // send `alloptions` hash read requests to the master
        if ($isReading && $this->config->split_alloptions && \is_string($parameters[0] ?? null)) {
            $isReading = \strpos($parameters[0], 'options:alloptions:') === false;
        }

        try {
            return $isReading
                ? $this->pool[\array_rand($this->pool)]->command($name, $parameters)
                : $this->master->command($name, $parameters);
        } catch (Throwable $th) {
            try {
                $this->connectToSentinels();
            } catch (ConnectionException $ex) {
                throw new ConnectionException($ex->getMessage(), $ex->getCode(), $th);
            }
        }

        return $isReading
            ? $this->pool[\array_rand($this->pool)]->command($name, $parameters)
            : $this->master->command($name, $parameters);
    }
}
