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

use Countable;
use Traversable;
use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;

/**
 * @implements \ArrayAccess<int, \RedisCachePro\Metrics\Measurement>
 * @implements \IteratorAggregate<\RedisCachePro\Metrics\Measurement>
 */
class Measurements implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * The measurements contained in the collection.
     *
     * @var \RedisCachePro\Metrics\Measurement[]
     */
    protected $items = [];

    /**
     * The cached results of extracted metric values.
     *
     * @var array<mixed>
     */
    protected $cache = [];

    /**
     * Get all of the items in the collection.
     *
     * @return \RedisCachePro\Metrics\Measurement[]
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Returns an array of metric values.
     *
     * @param  string  $metric
     * @return array<mixed>
     */
    public function metricValues(string $metric)
    {
        if (isset($this->cache[$metric])) {
            return $this->cache[$metric];
        }

        $this->cache[$metric] = array_filter(
            array_map(function ($item) use ($metric) {
                return $item->{$metric};
            }, $this->items),
            function ($value) {
                return ! is_null($value);
            }
        );

        return $this->cache[$metric];
    }

    /**
     * Execute a callback over each item.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Run a filter over each of the items.
     *
     * @param  callable  $callback
     * @return self
     */
    public function filter(callable $callback)
    {
        $this->items = array_filter($this->items, $callback);

        return $this;
    }

    /**
     * Get the latest value of a given metric.
     *
     * @param  string  $metric
     * @return mixed
     */
    public function latest(string $metric)
    {
        foreach (array_reverse($this->items) as $item) {
            if (! is_null($item->{$metric})) {
                return $item->{$metric};
            }
        }
    }

    /**
     * Get the average value of a given metric.
     *
     * @param  string  $metric
     * @return mixed
     */
    public function max(string $metric)
    {
        $values = $this->metricValues($metric);

        if (count($values)) {
            return max($values);
        }
    }

    /**
     * Get the average value of a given metric.
     *
     * @param  string  $metric
     * @return mixed
     */
    public function mean(string $metric)
    {
        $values = $this->metricValues($metric);

        if ($count = count($values)) {
            return array_sum($values) / $count;
        }
    }

    /**
     * Get the median of a given metric.
     *
     * @param  string  $metric
     * @return mixed
     */
    public function median(string $metric)
    {
        $values = $this->metricValues($metric);

        $count = count($values);

        if ($count === 0) {
            return;
        }

        sort($values);

        $middle = floor($count / 2);

        if ($count % 2) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Get the given percentile of a given metric.
     *
     * @param  string  $metric
     * @param  int  $percentile
     * @return mixed
     */
    public function percentile(string $metric, int $percentile)
    {
        $values = $this->metricValues($metric);

        $count = count($values);

        if ($count === 0) {
            return;
        }

        sort($values);

        $index = ($percentile / 100) * ($count - 1);

        if (floor($index) == $index) {
            return $values[$index];
        }

        return ($values[floor($index)] + $values[ceil($index)]) / 2;
    }

    /**
     * Get the 90th percentile of a given metric.
     *
     * @param  string  $metric
     * @return mixed
     */
    public function p90(string $metric)
    {
        return $this->percentile($metric, 90);
    }

    /**
     * Get the 95th percentile of a given metric.
     *
     * @param  string  $metric
     * @return mixed
     */
    public function p95(string $metric)
    {
        return $this->percentile($metric, 95);
    }

    /**
     * Get the 99th percentile of a given metric.
     *
     * @param  string  $metric
     * @return mixed
     */
    public function p99(string $metric)
    {
        return $this->percentile($metric, 99);
    }

    /**
     * Split the measurements into the time-based intervals.
     *
     * @param  int  $interval
     * @return array<int, mixed>
     */
    public function intervals(int $interval)
    {
        $intervals = [];
        $iterator = $this->getIterator();

        while ($iterator->valid()) {
            $item = $iterator->current();
            $timestamp = (int) $item->timestamp - (int) $item->timestamp % $interval;

            if (! isset($intervals[$timestamp])) {
                $intervals[$timestamp] = new self;
            }

            $intervals[$timestamp]->push($item);

            $iterator->next();
        }

        return $intervals;
    }

    /**
     * Get the values of a given key.
     *
     * @param  string  $metric
     * @return array<mixed>
     */
    public function pluck(string $metric)
    {
        return array_map(function ($item) use ($metric) {
            return $item->{$metric};
        }, $this->items);
    }

    /**
     * Push one or more items onto the end of the collection.
     *
     * @param  \RedisCachePro\Metrics\Measurement  ...$metrics
     * @return self
     */
    public function push(Measurement ...$metrics): self
    {
        array_push($this->items, ...$metrics);

        return $this;
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator<int, \RedisCachePro\Metrics\Measurement>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  int  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return isset($this->items[$key]);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  int  $key
     * @return \RedisCachePro\Metrics\Measurement
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param  int  $key
     * @param  \RedisCachePro\Metrics\Measurement  $value
     * @return void
     */
    public function offsetSet($key, $value): void // phpcs:ignore PHPCompatibility
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  int  $key
     * @return void
     */
    public function offsetUnset($key): void // phpcs:ignore PHPCompatibility
    {
        unset($this->items[$key]);
    }
}
