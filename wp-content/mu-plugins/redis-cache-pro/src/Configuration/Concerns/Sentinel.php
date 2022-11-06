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

trait Sentinel
{
    /**
     * The array of Redis Sentinels.
     *
     * @var array<string>
     */
    protected $sentinels;

    /**
     * The Redis Sentinel service name.
     *
     * @var string
     */
    protected $service;

    /**
     * Set the array of Redis Sentinels.
     *
     * @param  array<string>  $sentinels
     * @return void
     */
    public function setSentinels($sentinels)
    {
        if (! \is_array($sentinels)) {
            throw new ConfigurationException('`sentinels` must an array of Redis Sentinels');
        }

        if (empty($sentinels)) {
            throw new ConfigurationInvalidException('`sentinels` must be a non-empty array');
        }

        $this->sentinels = $sentinels;
    }

    /**
     * Set the connection protocol.
     *
     * @param  string  $service
     * @return void
     */
    public function setService($service)
    {
        if (! \is_string($service)) {
            throw new ConfigurationInvalidException('`service` must be a string');
        }

        $this->service = $service;
    }
}
