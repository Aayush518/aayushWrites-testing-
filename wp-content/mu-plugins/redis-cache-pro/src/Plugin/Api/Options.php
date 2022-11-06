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
use RedisCachePro\License;

use RedisCachePro\Plugin\Options\Sanitizer;
use RedisCachePro\Plugin\Options\Validator;

class Options extends WP_REST_Controller
{
    /**
     * The plugin instance.
     *
     * @var \RedisCachePro\Plugin
     */
    protected $plugin;

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
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->namespace = 'objectcache/v1';
        $this->resource_name = 'options';
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
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'item_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::READABLE),
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'item_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
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
    public function item_permissions_check($request)
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
     * Returns the REST API response for the request.
     *
     * @param  \WP_REST_Request  $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_item($request)
    {
        $options = $this->plugin->options();

        return rest_ensure_response($options);
    }

    /**
     * Returns the REST API response for the request.
     *
     * @param  \WP_REST_Request  $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function update_item($request)
    {
        $options = $request->has_param('objectcache_options')
            ? $request->get_param('objectcache_options')
            : $request->get_params();

        $filteredOptions = array_filter((array) $options, function ($name) {
            return array_key_exists($name, $this->plugin->defaultOptions());
        }, ARRAY_FILTER_USE_KEY);

        $sanitizer = new Sanitizer;
        $sanitizedOptions = [];

        foreach ($filteredOptions as $name => $value) {
            $sanitizedOptions[$name] = $sanitizer->{$name}($value);
        }

        $validator = new Validator($this->plugin);

        $result = $validator->validate($sanitizedOptions);

        if (is_wp_error($result)) {
            return $result;
        }

        update_site_option('objectcache_options', $sanitizedOptions);

        return $this->get_item($request);
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
            'title' => 'objectcache_options',
            'type' => 'object',
            'properties' => [
                'channel' => [
                    'description' => 'The update channel acts as a "minimum stability", meaning that using the Alpha channel will also show the latest Beta releases and so on, whichever has the highest version number. Using an update channel other than Stable may break your site.',
                    'type' => 'string',
                    'enum' => array_keys(License::Stabilities),
                ],
                'flushlog' => [
                    'description' => 'Whether to keep a log of cache flushes. Ignored when debug mode is enabled.',
                    'type' => 'boolean',
                ],
            ],
        ];

        $this->schema = $schema;

        return $this->add_additional_fields_schema($this->schema);
    }
}
