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

use RedisCachePro\Configuration\Configuration;
use RedisCachePro\Connections\ConnectionInterface;

interface Connector
{
    /**
     * Loads required libraries and throw exception on failure.
     *
     * @return void
     */
    public static function boot(): void; // phpcs:ignore PHPCompatibility

    /**
     * Checks whether the client supports the given feature.
     *
     * @return bool
     */
    public static function supports(string $feature): bool;

    /**
     * Create a new connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\ConnectionInterface
     */
    public static function connect(Configuration $config): ConnectionInterface;

    /**
     * Create a new connection to an instance.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\ConnectionInterface
     */
    public static function connectToInstance(Configuration $config): ConnectionInterface;

    /**
     * Create a new clustered connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\ConnectionInterface
     */
    public static function connectToCluster(Configuration $config): ConnectionInterface;

    /**
     * Create a new Sentinel connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\ConnectionInterface
     */
    public static function connectToSentinels(Configuration $config): ConnectionInterface;

    /**
     * Create a new replicated connection.
     *
     * @param  \RedisCachePro\Configuration\Configuration  $config
     * @return \RedisCachePro\Connections\ConnectionInterface
     */
    public static function connectToReplicatedServers(Configuration $config): ConnectionInterface;
}
