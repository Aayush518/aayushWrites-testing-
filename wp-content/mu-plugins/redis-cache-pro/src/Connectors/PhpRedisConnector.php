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

namespace RedisCachePro\Connectors;

use LogicException;

use Redis;
use RedisCluster;
use RedisException;
use RedisClusterException;

use RedisCachePro\Configuration\Configuration;

use RedisCachePro\Connections\PhpRedisConnection;
use RedisCachePro\Connections\ConnectionInterface;
use RedisCachePro\Connections\PhpRedisClusterConnection;
use RedisCachePro\Connections\PhpRedisSentinelsConnection;
use RedisCachePro\Connections\PhpRedisReplicatedConnection;

use RedisCachePro\Exceptions\PhpRedisMissingException;
use RedisCachePro\Exceptions\PhpRedisOutdatedException;

class PhpRedisConnector implements Connector
{
    /**
     * The minimum required PhpRedis version.
     *
     * @var string
     */
    const RequiredVersion = '3.1.1';

    /**
     * Ensure PhpRedis v3.1.1 or newer loaded.
     *
     * @return void
     */
    public static function boot(): void // phpcs:ignore PHPCompatibility
    {
        if (! \extension_loaded('redis')) {
            throw new PhpRedisMissingException;
        }

        if (\version_compare((string) \phpversion('redis'), self::RequiredVersion, '<')) {
            throw new PhpRedisOutdatedException;
        }
    }

    /**
     * Check whether the client supports the given feature.
     *
     * @return bool
     */
    public static function supports(string $feature): bool
    {
        switch ($feature) {
            case Configuration::SERIALIZER_PHP:
                return \defined('\Redis::SERIALIZER_PHP');
            case Configuration::SERIALIZER_IGBINARY:
                return \defined('\Redis::SERIALIZER_IGBINARY');
            case Configuration::COMPRESSION_NONE:
                return true;
            case Configuration::COMPRESSION_LZF:
                return \defined('\Redis::COMPRESSION_LZF');
            case Configuration::COMPRESSION_LZ4:
                return \defined('\Redis::COMPRESSION_LZ4');
            case Configuration::COMPRESSION_ZSTD:
                return \defined('\Redis::COMPRESSION_ZSTD');
            case 'retries':
            case 'backoff':
                return \defined('\Redis::BACKOFF_ALGORITHM_DECORRELATED_JITTER');
            case 'tls':
                return \version_compare((string) \phpversion('redis'), '5.3.2', '>=');
        }

        return false;
    }

    /**
     * Create a new PhpRedis connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\ConnectionInterface
     */
    public static function connect(Configuration $config): ConnectionInterface
    {
        if ($config->cluster) {
            return static::connectToCluster($config);
        }

        if ($config->sentinels) {
            return static::connectToSentinels($config);
        }

        if ($config->servers) {
            return static::connectToReplicatedServers($config);
        }

        return static::connectToInstance($config);
    }

    /**
     * Create a new PhpRedis connection to an instance.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\PhpRedisConnection
     */
    public static function connectToInstance(Configuration $config): ConnectionInterface
    {
        $client = new Redis;
        $version = (string) \phpversion('redis');

        $persistent = $config->persistent;
        $persistentId = '';

        $host = $config->host;

        if (\version_compare($version, '5.0.0', '>=') && $config->scheme) {
            $host = "{$config->scheme}://{$config->host}";
        }

        $host = \str_replace('unix://', '', $host);

        $method = $persistent ? 'pconnect' : 'connect';

        $parameters = [
            $host,
            $config->port ?? 0,
            $config->timeout,
            $persistentId,
            $config->retry_interval,
        ];

        if (\version_compare($version, '3.1.3', '>=')) {
            $parameters[] = $config->read_timeout;
        }

        $tlsContext = static::tlsOptions($config);

        if ($tlsContext && \version_compare($version, '5.3.0', '>=')) {
            $parameters[] = ['stream' => $tlsContext];
        }

        $retries = 0;

        CONNECTION_RETRY: {
            $delay = self::nextDelay($config, $retries);

            try {
                $client->{$method}(...$parameters);
            } catch (RedisException $exception) {
                if (++$retries >= $config->retries) {
                    throw $exception;
                }

                \usleep($delay * 1000);
                goto CONNECTION_RETRY;
            }
        }

        if ($config->username && $config->password) {
            $client->auth([$config->username, $config->password]);
        } elseif ($config->password) {
            $client->auth($config->password);
        }

        if ($config->database) {
            $client->select($config->database);
        }

        if ($config->read_timeout) {
            $client->setOption(Redis::OPT_READ_TIMEOUT, (string) $config->read_timeout);
        }

        return new PhpRedisConnection($client, $config);
    }

    /**
     * Create a new clustered PhpRedis connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\PhpRedisClusterConnection
     */
    public static function connectToCluster(Configuration $config): ConnectionInterface
    {
        if (\is_string($config->cluster)) {
            $parameters = [$config->cluster];
        } else {
            $parameters = [
                null,
                \array_values($config->cluster),
                $config->timeout,
                $config->read_timeout,
                $config->persistent,
            ];

            $version = (string) \phpversion('redis');

            if (\version_compare($version, '4.3.0', '>=')) {
                $parameters[] = $config->password ?? '';
            }

            $tlsContext = static::tlsOptions($config);

            if ($tlsContext && \version_compare($version, '5.3.2', '>=')) {
                $parameters[] = $tlsContext;
            }
        }

        $client = null;
        $retries = 0;

        CLUSTER_RETRY: {
            $delay = self::nextDelay($config, $retries);

            try {
                $client = new RedisCluster(...$parameters);
            } catch (RedisClusterException $exception) {
                if (++$retries >= $config->retries) {
                    throw $exception;
                }

                \usleep($delay * 1000);
                goto CLUSTER_RETRY;
            }
        }

        if ($config->cluster_failover) {
            $client->setOption(RedisCluster::OPT_SLAVE_FAILOVER, $config->getClusterFailover());
        }

        return new PhpRedisClusterConnection($client, $config);
    }

    /**
     * Create a new PhpRedis Sentinel connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\PhpRedisSentinelsConnection
     */
    public static function connectToSentinels(Configuration $config): ConnectionInterface
    {
        if (version_compare((string) phpversion('redis'), '5.3.2', '<')) {
            throw new LogicException('Redis Sentinel requires PhpRedis v5.3.2 or newer');
        }

        return new PhpRedisSentinelsConnection($config);
    }

    /**
     * Create a new replicated PhpRedis connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\PhpRedisReplicatedConnection
     */
    public static function connectToReplicatedServers(Configuration $config): ConnectionInterface
    {
        $replicas = [];

        foreach ($config->servers as $server) {
            $serverConfig = clone $config;
            $serverConfig->setUrl($server);

            if (Configuration::parseUrl($server)['role'] === 'master') {
                $master = static::connectToInstance($serverConfig);
            } else {
                $replicas[] = static::connectToInstance($serverConfig);
            }
        }

        if (! isset($master)) {
            throw new LogicException('No replication master node found');
        }

        return new PhpRedisReplicatedConnection($master, $replicas, $config);
    }

    /**
     * Returns the TLS context options for the transport.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return array<mixed>
     */
    protected static function tlsOptions(Configuration $config)
    {
        if (\defined('\WP_REDIS_PHPREDIS_OPTIONS')) {
            if (function_exists('_doing_it_wrong')) {
                $message = 'The `WP_REDIS_PHPREDIS_OPTIONS` constant is deprecated, use the `tls_options` configuration option instead. ';
                $message .= 'https://objectcache.pro/docs/configuration-options/#tls-options';

                \_doing_it_wrong(__METHOD__, $message, '1.12.1');
            }

            return \WP_REDIS_PHPREDIS_OPTIONS;
        }

        return $config->tls_options;
    }

    /**
     * Returns the next delay for the given retry.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @param  int  $retries
     * @return int
     */
    public static function nextDelay(Configuration $config, int $retries)
    {
        if ($config->backoff === Configuration::BACKOFF_NONE) {
            return $retries ** 2;
        }

        $retryInterval = $config->retry_interval;
        $jitter = $retryInterval * 0.1;

        return $retries * \mt_rand(
            (int) \floor($retryInterval - $jitter),
            (int) \ceil($retryInterval + $jitter)
        );
    }
}
