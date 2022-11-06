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

namespace RedisCachePro\ObjectCaches\Concerns;

use Throwable;

use RedisCachePro\Metrics\Measurement;
use RedisCachePro\Metrics\Measurements;
use RedisCachePro\Metrics\RedisMetrics;
use RedisCachePro\Metrics\RelayMetrics;
use RedisCachePro\Metrics\WordPressMetrics;

use RedisCachePro\Connections\RelayConnection;
use RedisCachePro\Configuration\Configuration;
use RedisCachePro\ObjectCaches\PhpRedisObjectCache;

trait TakesMeasurements
{
    /**
     * The gathered metrics for the current request.
     *
     * @var \RedisCachePro\Metrics\Measurement|null
     */
    protected $requestMeasurement;

    /**
     * Retrieve measurements of the given type and range.
     *
     * @param  string|int  $min
     * @param  string|int  $max
     * @param  string|int|null  $offset
     * @param  string|int|null  $count
     * @return \RedisCachePro\Metrics\Measurements
     */
    public function measurements($min = '-inf', $max = '+inf', $offset = null, $count = null): Measurements
    {
        if (is_int($offset) && is_int($count)) {
            $options = ['limit' => [$offset, $count]];
        }

        $measurements = new Measurements;

        try {
            $this->storeReads++;

            $measurements->push(
                ...$this->connection->zRevRangeByScore(
                    (string) $this->id('measurements', 'analytics'),
                    (string) $max,
                    (string) $min,
                    $options ?? []
                )
            );
        } catch (Throwable $exception) {
            $this->error($exception);
        }

        return $measurements;
    }

    /**
     * Return number of metrics stored.
     *
     * @param  string  $min
     * @param  string  $max
     * @return int
     */
    public function countMeasurements($min = '-inf', $max = '+inf')
    {
        return $this->connection->zcount(
            (string) $this->id('measurements', 'analytics'),
            (string) $min,
            (string) $max
        );
    }

    /**
     * Stores metrics for the current request.
     *
     * @return void
     */
    protected function storeMeasurements()
    {
        if (! $this->config->analytics->enabled) {
            return;
        }

        $now = time();
        $id = (string) $this->id('measurements', 'analytics');

        $measurement = Measurement::make();
        $measurement->wp = new WordPressMetrics($this);

        try {
            $lastSample = $this->connection->get("{$id}:sample");
            $this->storeReads++;

            if ($lastSample < $now - 3) {
                $measurement->redis = new RedisMetrics($this);

                if ($this->connection instanceof RelayConnection && $this->connection->hasInMemoryCache()) {
                    $measurement->relay = new RelayMetrics($this->connection, $this->config);
                }

                $this->connection->set("{$id}:sample", $now);
                $this->storeWrites++;
            }

            $this->connection->zadd($id, $measurement->timestamp, $measurement);
            $this->storeWrites++;
        } catch (Throwable $exception) {
            $this->error($exception);
        }

        $this->requestMeasurement = $measurement;
    }

    /**
     * Discards old measurements.
     *
     * @return void
     */
    public function pruneMeasurements()
    {
        /**
         * Filters the analytics retention time.
         *
         * @param  int  $duration The retention duration
         */
        $retention = (int) apply_filters('objectcache_analytics_retention', $this->config->analytics->retention);

        try {
            $this->storeWrites++;

            $this->connection->zRemRangeByScore(
                (string) $this->id('measurements', 'analytics'),
                '-inf',
                (string) (microtime(true) - $retention)
            );
        } catch (Throwable $exception) {
            $this->error($exception);
        }
    }

    /**
     * Flush the database and restore the analytics afterwards.
     *
     * @return bool
     */
    protected function flushWithoutAnalytics()
    {
        $measurements = $this->dumpMeasurements();

        try {
            $flush = $this->connection->flushdb();
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }

        if ($measurements) {
            $this->restoreMeasurements($measurements);
        }

        return $flush;
    }

    /**
     * Returns a dump of the measurements.
     *
     * @return string|false
     */
    protected function dumpMeasurements()
    {
        if (
            $this::Client === PhpRedisObjectCache::Client &&
            $this->config->compression === Configuration::COMPRESSION_ZSTD &&
            version_compare((string) phpversion('redis'), '5.3.5', '<')
        ) {
            error_log('objectcache.notice: Unable to restore analytics when using Zstandard compression, please update to PhpRedis 5.3.5 or newer');

            return false;
        }

        try {
            return $this->connection->dump(
                (string) $this->id('measurements', 'analytics')
            );
        } catch (Throwable $exception) {
            error_log(sprintf('objectcache.notice: Failed to dump analytics (%s)', $exception));
        }

        return false;
    }

    /**
     * Restores the given measurements dump.
     *
     * @param  mixed  $measurements
     * @return bool|void
     */
    protected function restoreMeasurements($measurements)
    {
        try {
            $id = $this->id('measurements', 'analytics');

            return $this->connection->restore((string) $id, 0, $measurements);
        } catch (Throwable $exception) {
            error_log(sprintf('objectcache.notice: Failed to restore analytics (%s)', $exception));
        }
    }

    /**
     * Return the gathered metrics for the current request.
     *
     * @return \RedisCachePro\Metrics\Measurement|null
     */
    public function requestMeasurement()
    {
        return $this->requestMeasurement;
    }
}
