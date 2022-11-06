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
use WP_REST_Controller;

use RedisCachePro\Plugin;
use RedisCachePro\ObjectCaches\ObjectCacheInterface;

class Groups extends WP_REST_Controller
{
    /**
     * The resource name of this controller's route.
     *
     * @var string
     */
    protected $resource_name;

    /**
     * Create a new instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->namespace = 'objectcache/v1';
        $this->resource_name = 'groups';
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
         * @param  string  $capability  The drop-in metadata.
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

        if (! $wp_object_cache->connection()) {
            return new WP_Error(
                'objectcache_not_connected',
                'The object cache is not connected.',
                ['status' => 400]
            );
        }

        $config = $wp_object_cache->config();
        $connection = $wp_object_cache->connection();

        if ($config->cluster) {
            /** @var \RedisCachePro\Connections\PhpRedisClusterConnection $connection */
            return $this->clusterCacheGroups($connection);
        }

        if ($config->sentinels) {
            /** @var \RedisCachePro\Connections\PhpRedisSentinelsConnection $connection */
            return $this->instanceCacheGroups($connection);
        }

        if ($config->servers) {
            /** @var \RedisCachePro\Connections\PhpRedisReplicatedConnection $connection */
            return $this->instanceCacheGroups($connection);
        }

        return $this->instanceCacheGroups($connection);
    }

    /**
     * Returns all the keys and their count from an instance.
     *
     * @param  \RedisCachePro\Connections\ConnectionInterface  $connection
     * @return \WP_REST_Response|\WP_Error
     */
    protected function instanceCacheGroups($connection)
    {
        $groups = array_map('count', $this->iterate($connection));

        return $this->prepareGroupsForResponse($groups);
    }

    /**
     * Returns all the keys and their count from the cluster.
     *
     * @param  \RedisCachePro\Connections\PhpRedisClusterConnection  $connection
     * @return \WP_REST_Response|\WP_Error
     */
    protected function clusterCacheGroups($connection)
    {
        $groups = [];

        foreach ($connection->_masters() as $node) {
            $groups[] = $this->iterate($connection, $node);
        }

        $groups = array_map('count', array_merge_recursive([], ...$groups));

        return $this->prepareGroupsForResponse($groups);
    }

    /**
     * Run the scan command and iterate over to get all the keys.
     *
     * @param  \RedisCachePro\Connections\ConnectionInterface  $connection
     * @param  array<mixed>|null  $node
     * @return array<string, mixed>
     */
    protected function iterate($connection, $node = null)
    {
        global $wp_object_cache;

        $prefix = $wp_object_cache->config()->prefix;
        $pattern = is_null($prefix) ? '*' : "{$prefix}:*";

        $groups = [];
        $count = 100;

        /** @var ?int $iterator */
        $iterator = null;

        do {
            $scan = false;

            if ($node && method_exists($connection, 'scanNode')) {
                $scan = $connection->scanNode($iterator, $node, $pattern, $count);
            } elseif (method_exists($connection, 'scan')) {
                $scan = $connection->scan($iterator, $pattern, $count);
            }

            if ($scan === false) {
                continue;
            }

            foreach ($scan as $key) {
                $groups[$this->parseGroup($key)][] = $key;
            }
        } while ($iterator > 0);

        return $groups;
    }

    /**
     * Returns the key's group name.
     *
     * @param  string  $id
     * @return string
     */
    protected function parseGroup(string $id)
    {
        $id = str_replace('options:alloptions:', 'options:alloptions-', $id);
        $id = str_replace('analytics:measurements:', 'analytics:measurements-', $id);

        return array_reverse(explode(':', $id))[1];
    }

    /**
     * Transform the groups into the response format.
     *
     * @param  array<mixed>  $groups
     * @return \WP_REST_Response|\WP_Error
     */
    protected function prepareGroupsForResponse(array $groups)
    {
        array_walk($groups, function (&$item, $group) {
            $item = [
                'group' => str_replace(['{', '}'], '', (string) $group),
                'count' => $item,
            ];
        });

        $groups = array_values($groups);

        usort($groups, function ($a, $b) {
            return strcmp($a['group'], $b['group']);
        });

        return rest_ensure_response($groups);
    }

    /**
     * Retrieves the endpoint's schema, conforming to JSON Schema.
     *
     * @return array<string, mixed>
     */
    public function get_item_schema()
    {
        $schema = [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'objectcache_groups',
            'type' => 'object',
            'properties' => [
                'group' => [
                    'description' => 'The cache group name.',
                    'type' => 'string',
                ],
                'count' => [
                    'description' => 'The count of the group keys.',
                    'type' => 'integer',
                ],
            ],
        ];

        $this->schema = $schema;

        return $this->add_additional_fields_schema($this->schema);
    }
}
