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

class Measurement
{
    /**
     * The unique identifier of the measurement.
     *
     * @var string
     */
    public $id;

    /**
     * The Unix timestamp with microseconds of the measurement.
     *
     * @var float
     */
    public $timestamp;

    /**
     * The hostname on which the measurement was taken.
     *
     * @var string|null
     */
    public $hostname;

    /**
     * The URL path of the request, if applicable.
     *
     * @var string
     */
    public $path;

    /**
     * The WordPress measurement.
     *
     * @var \RedisCachePro\Metrics\WordPressMetrics
     */
    public $wp;

    /**
     * The Redis measurement.
     *
     * @var \RedisCachePro\Metrics\RedisMetrics|null
     */
    public $redis;

    /**
     * The Relay measurement.
     *
     * @var \RedisCachePro\Metrics\RelayMetrics|null
     */
    public $relay;

    /**
     * Makes a new instance.
     *
     * @return self
     */
    public static function make()
    {
        $self = new self;

        $self->id = substr(md5(uniqid((string) mt_rand(), true)), 12);
        $self->timestamp = microtime(true);
        $self->hostname = gethostname() ?: null;

        $self->path = $_SERVER['REQUEST_URI'] ?? null;

        if (isset($_ENV['DYNO'])) {
            $self->hostname = $_ENV['DYNO']; // Heroku
        }

        return $self;
    }

    /**
     * Returns an rfc3339 compatible timestamp.
     *
     * @return string
     */
    public function rfc3339()
    {
        return substr_replace(
            date('c', intval($this->timestamp)),
            substr((string) fmod($this->timestamp, 1), 1, 7),
            19,
            0
        );
    }

    /**
     * Returns the measurement as array.
     *
     * @return array<mixed>
     */
    public function toArray()
    {
        $array = $this->wp->toArray();

        if ($this->redis) {
            $array += array_combine(array_map(function ($key) {
                return "redis-{$key}";
            }, array_keys($redis = $this->redis->toArray())), $redis);
        }

        if ($this->relay) {
            $array += array_combine(array_map(function ($key) {
                return "relay-{$key}";
            }, array_keys($relay = $this->relay->toArray())), $relay);
        }

        return $array;
    }

    /**
     * Returns the request metrics in string format.
     *
     * @return string
     */
    public function __toString()
    {
        return implode(' ', array_filter([
            $this->wp,
            $this->redis ? (string) $this->redis : null,
            $this->relay ? (string) $this->relay : null,
        ]));
    }

    /**
     * Helper method to access metrics.
     *
     * @param  string  $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if (strpos($name, '->') !== false) {
            list($type, $metric) = explode('->', $name);

            if (strpos($metric, '-') !== false) {
                $metric = lcfirst(str_replace('-', '', ucwords($metric, '-')));
            }

            if (property_exists($this, $type)) {
                return $this->{$type}->{$metric} ?? null;
            }
        }

        trigger_error(
            sprintf('Undefined property: %s::$%s', get_called_class(), $name),
            E_USER_WARNING
        );
    }
}
