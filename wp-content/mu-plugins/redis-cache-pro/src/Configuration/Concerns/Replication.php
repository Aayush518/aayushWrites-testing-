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

namespace RedisCachePro\Configuration\Concerns;

use RedisCachePro\Exceptions\ConfigurationException;

trait Replication
{
    /**
     * The array of replicated Redis servers.
     *
     * @var array<string>
     */
    protected $servers;

    /**
     * The replication strategy.
     *
     * @var string
     */
    protected $replication_strategy = 'distribute';

    /**
     * The available replication strategies.
     *
     * @return array<string>
     */
    protected function replicationStrategies()
    {
        return [
            // Distribute readonly commands between master and replicas, at random
            'distribute',

            // Distribute readonly commands to the replicas, at random
            'distribute_replicas',

            // Send readonly commands to a single, random replica
            'concentrate',
        ];
    }

    /**
     * Set the array of replicated Redis servers.
     *
     * @param  array<string>  $servers
     * @return void
     */
    public function setServers($servers)
    {
        if (! \is_array($servers)) {
            throw new ConfigurationException('`servers` must an array of Redis servers');
        }

        $masters = \array_filter($servers, function ($server) {
            return static::parseUrl($server)['role'] === 'master';
        });

        if (\count($masters) !== 1) {
            throw new ConfigurationException('`servers` must contain exactly one master');
        }

        $this->servers = $servers;
    }

    /**
     * Set the replication strategy.
     *
     * @param  string  $strategy
     * @return void
     */
    public function setReplicationStrategy($strategy)
    {
        $strategy = \strtolower((string) $strategy);

        if (! \in_array($strategy, $this->replicationStrategies())) {
            throw new ConfigurationException("Replication strategy `{$strategy}` is not supported");
        }

        $this->replication_strategy = $strategy;
    }
}
