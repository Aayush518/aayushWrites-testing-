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

namespace RedisCachePro\Console\Watchers;

use cli\Notify;
use cli\Streams;

class LogWatcher extends Notify
{
    /**
     * Holds the command options.
     *
     * @var array<mixed>
     */
    public $options;

    /**
     * The object cache instance.
     *
     * @var \RedisCachePro\ObjectCaches\MeasuredObjectCacheInterface
     */
    public $cache;

    /**
     * Whether Relay is being used.
     *
     * @var bool
     */
    public $usingRelay;

    /**
     * Holds the last 1000 measurement IDs.
     *
     * @var array<int>
     */
    protected $ids = [];

    /**
     * The measurements to display.
     *
     * @var \RedisCachePro\Metrics\Measurements|null
     */
    protected $measurements;

    /**
     * Holds the default metrics.
     *
     * @var array<string>
     */
    protected $defaultMetrics = [
        'ms-total',
        'ms-cache',
        'ms-cache-ratio',
        'hits',
        'misses',
        'hit-ratio',
        'store-reads',
        'store-writes',
        'sql-queries',
        'redis-hit-ratio',
        'redis-ops-per-sec',
        'redis-keys',
        'relay-hits',
        'relay-keys',
        'relay-misses',
        'relay-memory-ratio',
    ];

    /**
     * Prints the metrics to the screen.
     *
     * @param  bool  $finish
     * @return void
     */
    public function display($finish = false)
    {
        if ($this->_current === 1) {
            Streams::line($this->_message);
        }

        if (! $this->measurements) {
            return;
        }

        $metrics = empty($this->options['metrics'])
            ? $this->defaultMetrics
            : $this->options['metrics'];

        foreach ($this->measurements as $measurement) {
            Streams::line(sprintf(
                "\e[32m%s %s:\e[0m %s %s",
                $measurement->rfc3339(),
                $measurement->hostname,
                "\e[2mpath=\e[0m\e[32m\"{$measurement->path}\"\e[0m",
                $this->formatMeasurement(
                    $measurement->toArray(),
                    $metrics
                )
            ));
        }

        $this->measurements = null;
    }

    /**
     * Prepare the metrics.
     *
     * @return void
     */
    public function prepare()
    {
        if (empty($this->ids)) {
            $this->ids = $this->cache->measurements('-inf', '+inf', 0, 100)->pluck('id');

            return;
        }

        $this->measurements = $this->cache->measurements(
            strval(microtime(true) - 2)
        )->filter(function ($measurement) {
            return ! in_array($measurement->id, $this->ids);
        });

        array_push($this->ids, ...$this->measurements->pluck('id'));

        $this->ids = array_slice($this->ids, -1000);
    }

    /**
     * Format the given measurement in log format.
     *
     * @param  array<mixed>  $measurement
     * @param  array<string>  $metrics
     * @return string
     */
    protected function formatMeasurement(array $measurement, array $metrics)
    {
        $format = "\e[2m%s\e[2m#\e[0m\e[%sm%s\e[0m\e[2m=\e[0m%s";

        return implode(' ', array_filter(
            array_map(function ($null, $metric) use ($measurement, $format) {
                if (! isset($measurement[$metric])) {
                    return;
                }

                switch (strstr($metric, '-', true)) {
                    case 'redis':
                        return sprintf($format, 'sample', 31, $metric, $measurement[$metric]);
                    case 'relay':
                        return sprintf($format, 'sample', 35, $metric, $measurement[$metric]);
                    case 'ms':
                        return sprintf($format, 'metric', 36, $metric, $measurement[$metric]);
                    case 'sql':
                        return sprintf($format, 'metric', 33, $metric, $measurement[$metric]);
                    default:
                        return sprintf($format, 'metric', 34, $metric, $measurement[$metric]);
                }
            }, array_flip($metrics), $metrics)
        ));
    }
}
