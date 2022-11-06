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

namespace RedisCachePro\Plugin\Api;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Controller;

use RedisCachePro\Plugin;

use RedisCachePro\ObjectCaches\ObjectCacheInterface;
use RedisCachePro\ObjectCaches\MeasuredObjectCacheInterface;

use RedisCachePro\Metrics\RedisMetrics;
use RedisCachePro\Metrics\RelayMetrics;
use RedisCachePro\Metrics\WordPressMetrics;

class Analytics extends WP_REST_Controller
{
    /**
     * The resource name of this controller's route.
     *
     * @var string
     */
    protected $resource_name;

    /**
     * The default interval, in seconds.
     *
     * @var int
     */
    protected static $interval = 60;

    /**
     * The default intervals.
     *
     * The keys represent the resolution in seconds
     * and the values are the number of intervals.
     *
     * @var array<int, int>
     */
    protected static $intervals = [
        10 => 30,
        60 => 30,
        300 => 24,
    ];

    /**
     * Create a new instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->namespace = 'objectcache/v1';
        $this->resource_name = 'analytics';
    }

    /**
     * Returns the default interval, in seconds.
     *
     * @return int
     */
    public static function interval()
    {
        /**
         * Filters the default interval for object cache analytics.
         *
         * @param  int  $interval  The interval, in seconds.
         */
        return (int) apply_filters('objectcache_analytics_interval', static::$interval);
    }

    /**
     * Returns the supported intervals.
     *
     * @return array<int, int>
     */
    public static function intervals()
    {
        /**
         * Filters the intervals for object cache analytics.
         *
         * The array keys represent the resolution in seconds
         * and the values are the number of intervals.
         *
         * @param  array  $intervals  The intervals.
         */
        return (array) apply_filters('objectcache_analytics_intervals', static::$intervals);
    }

    /**
     * Register all REST API routes.
     *
     * @return void
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, "/{$this->resource_name}", [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args' => $this->get_collection_params(),
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    /**
     * The permission callback for the endpoint.
     *
     * @param  \WP_REST_Request  $request
     * @return true|\WP_Error
     */
    public function get_items_permissions_check($request)
    {
        /**
         * Filter the capability required to access REST API endpoints.
         *
         * @param  string  $capability  The capability name.
         */
        $capability = (string) apply_filters('objectcache_rest_capability', Plugin::Capability);

        if (current_user_can($capability)) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            'Sorry, you are not allowed to do that.',
            ['status' => rest_authorization_required_code()]
        );
    }

    /**
     * Retrieves the query params for the posts collection.
     *
     * @return array<string, mixed>
     */
    public function get_collection_params()
    {
        $params = parent::get_collection_params();
        $params['per_page']['default'] = 30;
        $params['context']['default'] = 'compute';

        unset($params['search']);

        $params['interval'] = [
            'description' => 'The interval in seconds.',
            'type' => 'integer',
            'required' => false,
            'minimum' => 1,
            'default' => static::interval(),
        ];

        return $params;
    }

    /**
     * Returns the REST API response for the request.
     *
     * @param  \WP_REST_Request  $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_items($request)
    {
        global $wp_object_cache;

        if (! $wp_object_cache instanceof ObjectCacheInterface) {
            return new WP_Error(
                'objectcache_not_supported',
                'The object cache is not supported.',
                ['status' => 400]
            );
        }

        if (! $wp_object_cache instanceof MeasuredObjectCacheInterface) {
            return new WP_Error(
                'objectcache_analytics_unsupported',
                'The object cache does not support analytics.',
                ['status' => 400]
            );
        }

        if (! $wp_object_cache->connection()) {
            return new WP_Error(
                'objectcache_not_connected',
                'The object cache is not connected.',
                ['status' => 400]
            );
        }

        if (! $wp_object_cache->config()->analytics->enabled) {
            return new WP_Error(
                'objectcache_analytics_disabled',
                'Object cache analytics are disabled.',
                ['status' => 400]
            );
        }

        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $interval = $request->get_param('interval');

        $now = microtime(true);

        $min = (int) $now - ($interval * $per_page * $page);
        $min = $min - $min % $interval;

        $max = (int) $now - ($interval * $per_page * ($page - 1));

        $intervals = $wp_object_cache->measurements((string) $min, (string) $max)
            ->intervals($interval);

        $range = array_slice(array_reverse(range($min, $max, $interval)), 0, 30, true);

        $collection = array_map(function ($timestamp) use ($intervals, $request) {
            return $this->prepare_item_for_response([
                'timestamp' => $timestamp,
                'measurements' => $intervals[$timestamp] ?? null,
            ], $request);
        }, $range);

        return rest_ensure_response($collection);
    }

    /**
     * Prepares a single interval output for response.
     *
     * @param  array  $item
     * @param  \WP_REST_Request  $request
     * @return array
     */
    public function prepare_item_for_response($item, $request) // @phpstan-ignore-line
    {
        $fields = $this->get_fields_for_response($request);

        if (rest_is_field_included('count', $fields)) {
            $item['count'] = count($item['measurements'] ?? []);
        }

        $rfc3339 = 'Y-m-d\TH:i:s';

        if (rest_is_field_included('date', $fields)) {
            $item['date'] = get_date_from_gmt("@{$item['timestamp']}", $rfc3339);
        }

        if (rest_is_field_included('date_gmt', $fields)) {
            $item['date_gmt'] = date($rfc3339, $item['timestamp']);
        }

        if (rest_is_field_included('date_display', $fields)) {
            $item['date_display'] = [
                'date' => wp_date('D jS', $item['timestamp']),
                'time' => sprintf(
                    '%s - %s %s',
                    wp_date('H:i', $item['timestamp']),
                    wp_date('H:i', $item['timestamp'] + $request->get_param('interval')),
                    current_datetime()->format('T')
                ),
            ];
        }

        $hasMeasurements = ! empty($item['measurements']);

        foreach ($this->get_metrics() as $id => $metric) {
            foreach ($metric['computations'] as $computation) {
                $name = $metric['group'] === 'wp'
                    ? $id
                    : str_replace($metric['group'], '', $id);

                if (rest_is_field_included("{$id}.{$computation}", $fields)) {
                    $item[$id][$computation] = $hasMeasurements
                        ? $item['measurements']->{$computation}("{$metric['group']}->{$name}")
                        : null;
                }
            }
        }

        if (rest_is_field_included('measurements', $fields)) {
            $item['measurements'] = array_map(function ($measurement) {
                return $measurement->toArray();
            }, $item['measurements'] ? $item['measurements']->all() : []);
        }

        $item = $this->add_additional_fields_to_object($item, $request);
        $item = $this->filter_response_by_context($item, $request['context']);

        return $item;
    }

    /**
     * Returns the metrics for each group and their supported computations.
     *
     * @return array<string, mixed>
     */
    protected function get_metrics()
    {
        $metrics = array_merge(
            WordPressMetrics::schema(),
            RedisMetrics::schema(),
            RelayMetrics::schema()
        );

        return array_map(function ($metric) {
            $metric['computations'] = [
                'max',
                'mean',
                'median',
                'p90',
                'p95',
                'p99',
            ];

            return $metric;
        }, $metrics);
    }

    /**
     * Retrieves the endpoint's schema, conforming to JSON Schema.
     *
     * @return array<string, mixed>
     */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }

        $properties = [
            'max' => ['type' => ['integer', 'float', 'null']],
            'mean' => ['type' => ['integer', 'float', 'null']],
            'median' => ['type' => ['integer', 'float', 'null']],
            'p90' => ['type' => ['integer', 'float', 'null']],
            'p95' => ['type' => ['integer', 'float', 'null']],
            'p99' => ['type' => ['integer', 'float', 'null']],
        ];

        $schema = [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'objectcache_analytics',
            'type' => 'object',
            'properties' => [
                'timestamp' => [
                    'description' => 'The timestamp of the interval.',
                    'type' => 'integer',
                    'context' => ['raw', 'compute'],
                ],
                'date' => [
                    'description' => "The date of the interval, in the site's timezone.",
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => ['compute'],
                ],
                'date_gmt' => [
                    'description' => 'The date of the interval, as GMT.',
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => ['compute'],
                ],
                'date_display' => [
                    'description' => 'The displayable date of the interval.',
                    'type' => 'object',
                    'context' => ['compute'],
                ],
                'count' => [
                    'description' => 'The amount of measurements taken in the interval.',
                    'type' => 'integer',
                    'context' => ['raw', 'compute'],
                ],
                'measurements' => [
                    'description' => 'The measurements taken in the interval.',
                    'type' => 'array',
                    'context' => ['raw'],
                ],
            ],
        ];

        foreach ($this->get_metrics() as $id => $metric) {
            $schema['properties'][$id] = [
                'title' => $metric['title'],
                'description' => $metric['description'],
                'type' => 'object',
                'context' => ['compute'],
                'properties' => $properties,
            ];
        }

        $this->schema = $schema;

        return $this->add_additional_fields_schema($this->schema);
    }
}
