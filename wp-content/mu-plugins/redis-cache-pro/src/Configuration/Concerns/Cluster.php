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
use RedisCachePro\Exceptions\ConfigurationInvalidException;

trait Cluster
{
    /**
     * The cluster configuration name as string, or an array of cluster nodes.
     *
     * @var string|array<string>|null
     */
    protected $cluster;

    /**
     * The cluster failover strategy.
     *
     * @var string
     */
    protected $cluster_failover = 'error';

    /**
     * The available cluster failover strategies.
     *
     * @return array<string>
     */
    protected function clusterFailovers()
    {
        return [
            // Only send commands to master nodes
            'none',

            // If a master can't be reached, and it has replicas, failover for read commands
            'error',

            // Always distribute readonly commands between masters and replicas, at random
            'distribute',

            // Always distribute readonly commands to the replicas, at random
            'distribute_replicas',
        ];
    }

    /**
     * Set the cluster configuration name or an array of cluster nodes.
     *
     * @param  string|array<string>  $cluster
     * @return void
     */
    public function setCluster($cluster)
    {
        if (is_null($cluster)) {
            return;
        }

        if (! \is_string($cluster) && ! \is_array($cluster)) {
            throw new ConfigurationException(
                '`cluster` must be a configuration name (string) or an array of cluster nodes'
            );
        }

        if (empty($cluster)) {
            throw new ConfigurationInvalidException('`cluster` must be a non-empty string or array');
        }

        $this->cluster = $cluster;
    }

    /**
     * Set the automatic replica failover / distribution.
     *
     * @param  string  $failover
     * @return void
     */
    public function setClusterFailover($failover)
    {
        $failover = \strtolower((string) $failover);
        $failover = \str_replace('distribute_slaves', 'distribute_replicas', $failover);

        if (! \in_array($failover, $this->clusterFailovers())) {
            throw new ConfigurationException("Cluster failover `{$failover}` is not supported");
        }

        $this->cluster_failover = $failover;
    }

    /**
     * Legacy method to set the automatic replica failover / distribution.
     *
     * @param  string  $failover
     * @return void
     */
    public function setSlaveFailover($failover)
    {
        $this->setClusterFailover($failover);
    }

    /**
     * Returns the value of the `RedisCluster::FAILOVER_*` constant.
     *
     * @return  int
     */
    public function getClusterFailover()
    {
        $failover = \str_replace('distribute_replicas', 'distribute_slaves', $this->cluster_failover);
        $failover = \strtoupper($failover);

        return \constant("\RedisCluster::FAILOVER_{$failover}");
    }
}
