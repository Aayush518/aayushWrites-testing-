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

use Redis;
use Throwable;

use RedisCachePro\Configuration\Configuration;
use RedisCachePro\Connectors\PhpRedisConnector;
use RedisCachePro\Exceptions\ConnectionException;

/**
 * @mixin \Redis
 */
class PhpRedisConnection extends Connection implements ConnectionInterface
{
    /**
     * The Redis instance.
     *
     * @var \Redis|\RedisCluster|\Relay\Relay
     */
    protected $client;

    /**
     * The client's FQCN.
     *
     * @var string
     */
    protected $class = Redis::class;

    /**
     * Create a new PhpRedis instance connection.
     *
     * @param  \Redis  $client
     * @param  \RedisCachePro\Configuration\Configuration  $config
     */
    public function __construct(Redis $client, Configuration $config)
    {
        $this->client = $client;
        $this->config = $config;

        $this->log = $this->config->logger;

        $this->setSerializer();
        $this->setCompression();

        if (PhpRedisConnector::supports('backoff')) {
            $this->setBackoff();
        }
    }

    /**
     * Set the connection's retries and backoff algorithm.
     *
     * @see https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/
     * @return void
     */
    protected function setBackoff()
    {
        if ($this->config->retries) {
            $this->client->setOption($this->class::OPT_MAX_RETRIES, $this->config->retries);
        }

        if ($this->config->backoff === Configuration::BACKOFF_SMART) {
            $this->client->setOption($this->class::OPT_BACKOFF_ALGORITHM, $this->class::BACKOFF_ALGORITHM_DECORRELATED_JITTER);
            $this->client->setOption($this->class::OPT_BACKOFF_BASE, $this->config->retry_interval);
            $this->client->setOption($this->class::OPT_BACKOFF_CAP, \intval($this->config->read_timeout * 1000));
        }
    }

    /**
     * Set the connection's serializer.
     *
     * @return void
     */
    protected function setSerializer()
    {
        if ($this->config->serializer === Configuration::SERIALIZER_PHP) {
            $this->client->setOption($this->class::OPT_SERIALIZER, (string) $this->class::SERIALIZER_PHP);
        }

        if ($this->config->serializer === Configuration::SERIALIZER_IGBINARY) {
            $this->client->setOption($this->class::OPT_SERIALIZER, (string) $this->class::SERIALIZER_IGBINARY);
        }
    }

    /**
     * Set the connection's compression algorithm.
     *
     * @return void
     */
    protected function setCompression()
    {
        if ($this->config->compression === Configuration::COMPRESSION_NONE) {
            $this->client->setOption($this->class::OPT_COMPRESSION, (string) $this->class::COMPRESSION_NONE);
        }

        if ($this->config->compression === Configuration::COMPRESSION_LZF) {
            $this->client->setOption($this->class::OPT_COMPRESSION, (string) $this->class::COMPRESSION_LZF);
        }

        if ($this->config->compression === Configuration::COMPRESSION_ZSTD) {
            $this->client->setOption($this->class::OPT_COMPRESSION, (string) $this->class::COMPRESSION_ZSTD);
        }

        if ($this->config->compression === Configuration::COMPRESSION_LZ4) {
            $this->client->setOption($this->class::OPT_COMPRESSION, (string) $this->class::COMPRESSION_LZ4);
        }
    }

    /**
     * Execute the callback without data mutations on the connection,
     * such as serialization and compression algorithms.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public function withoutMutations(callable $callback)
    {
        $this->client->setOption($this->class::OPT_SERIALIZER, (string) $this->class::SERIALIZER_NONE);
        $this->client->setOption($this->class::OPT_COMPRESSION, (string) $this->class::COMPRESSION_NONE);

        try {
            return $callback($this);
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->setSerializer();
            $this->setCompression();
        }
    }

    /**
     * Execute callback without read timeout.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public function withoutTimeout(callable $callback)
    {
        return $this->withTimeout($callback, -1);
    }

    /**
     * Execute callback with custom read timeout.
     *
     * @param  callable  $callback
     * @param  mixed  $timeout
     * @return mixed
     */
    public function withTimeout(callable $callback, $timeout)
    {
        $this->client->setOption($this->class::OPT_READ_TIMEOUT, (string) $timeout);

        try {
            return $callback($this);
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->client->setOption($this->class::OPT_READ_TIMEOUT, (string) $this->config->read_timeout);
        }
    }

    /**
     * Flush the selected Redis database.
     *
     * When asynchronous flushing is not used the connection’s read timeout (if present)
     * is disabled to avoid a timeout and restores the timeout afterwards,
     * even in the event of an exception.
     *
     * @param  bool|null  $async
     * @return bool
     */
    public function flushdb($async = null)
    {
        if ($async ?? $this->config->async_flush) {
            return $this->command('flushdb', [true]);
        }

        return $this->withoutTimeout(function () {
            return $this->command('flushdb');
        });
    }

    /**
     * Hijack `pipeline()` calls to allow command logging.
     *
     * @return \RedisCachePro\Connections\Transaction
     */
    public function pipeline()
    {
        return Transaction::pipeline($this);
    }

    /**
     * Hijack `multi()` calls to allow command logging.
     *
     * @param  int  $type
     * @return \RedisCachePro\Connections\Transaction
     */
    public function multi(int $type = null)
    {
        return $type === $this->class::PIPELINE
            ? Transaction::multi($this)
            : Transaction::pipeline($this);
    }

    /**
     * Send `scan()` calls directly to the client.
     *
     * @param  int  $iterator
     * @param  string  $pattern
     * @param  int  $count
     * @return array<string>|false
     */
    public function scan(?int &$iterator, ?string $pattern = null, int $count = 0) // phpcs:ignore PHPCompatibility
    {
        return $this->client->scan($iterator, $pattern, $count);
    }

    /**
     * Hijack `restore()` calls due to a bug in modern PhpRedis versions
     * when data mutations like compression are used.
     *
     * @param  string  $key
     * @param  int  $timeout
     * @param  string  $value
     * @return bool
     */
    public function restore(string $key, int $timeout, string $value)
    {
        return $this->rawCommand('RESTORE', $key, $timeout, $value, 'REPLACE');
    }

    /**
     * Execute hijacked MULTI transaction/pipeline.
     *
     * This mimics `Connection::command()`.
     *
     * @param  \RedisCachePro\Connections\Transaction  $tx
     * @return array<mixed>
     */
    public function commands(Transaction $tx)
    {
        $method = $tx->type;

        $context = [
            'command' => \strtoupper($method),
            'parameters' => [],
        ];

        if ($this->config->debug || $this->config->save_commands) {
            $context['backtrace'] = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);

            if (\function_exists('wp_debug_backtrace_summary')) {
                $context['backtrace_summary'] = \wp_debug_backtrace_summary(__CLASS__);
            }
        }

        try {
            $start = $this->now();

            $pipe = $this->client->{$method}();

            foreach ($tx->commands as $command) {
                $pipe->{$command[0]}(...$command[1]);

                $context['parameters'][] = \array_merge([\strtoupper($command[0])], $command[1]);
            }

            $results = $pipe->exec();

            $time = $this->now() - $start;
            $this->ioWait[] = $time;
        } catch (Throwable $exception) {
            $this->log->error('Failed to execute transaction', $context + [
                'exception' => $exception,
            ]);

            throw ConnectionException::from($exception);
        }

        if (! is_array($results)) {
            $type = gettype($results);

            throw new ConnectionException("Transaction returned an unexpected type ({$type})");
        }

        $resultsCount = count($results);
        $commandCount = count($tx->commands);

        if ($resultsCount !== $commandCount) {
            throw new ConnectionException("Transaction returned {$resultsCount} results but unexpected {$commandCount}");
        }

        $ms = \round($time * 1000, 4);

        $this->log->info("Executed transaction in {$ms}ms", $context + [
            'result' => $results,
            'time' => $ms,
        ]);

        return $results;
    }
}
