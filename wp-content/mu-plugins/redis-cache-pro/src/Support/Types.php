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

namespace RedisCachePro\Support;

class PluginApiResponse
{
    //
}

class AnalyticsConfiguration
{
    /** @var bool */
    public $enabled;

    /** @var bool */
    public $persist;

    /** @var int */
    public $retention;

    /** @var bool */
    public $footnote;
}

class RelayConfiguration
{
    /** @var bool */
    public $cache;

    /** @var bool */
    public $listeners;

    /** @var bool */
    public $invalidations;

    /** @var ?array<string> */
    public $allowed;

    /** @var ?array<string> */
    public $ignored;
}

class ObjectCacheInfo
{
    /** @var bool */
    public $status;

    /** @var int */
    public $hits;

    /** @var int */
    public $misses;

    /** @var int|float */
    public $ratio;

    /** @var object */
    public $groups;

    /** @var array<string> */
    public $errors;

    /** @var array<string, string> */
    public $meta;
}

class PhpRedisObjectCacheInfo extends ObjectCacheInfo
{
    /** @var ?int */
    public $prefetches;

    /** @var int */
    public $storeReads;

    /** @var int */
    public $storeWrites;

    /** @var int */
    public $storeHits;

    /** @var int */
    public $storeMisses;
}

class ObjectCacheMetrics
{
    /** @var int */
    public $hits;

    /** @var int */
    public $misses;

    /** @var int|float */
    public $ratio;

    /** @var int|float|null */
    public $bytes;

    /** @var array<string, array{keys: int, keys: int}>|null */
    public $groups;
}

class PhpRedisObjectCacheMetrics extends ObjectCacheMetrics
{
    /** @var ?int */
    public $prefetches;

    /** @var int */
    public $storeReads;

    /** @var int */
    public $storeWrites;

    /** @var int */
    public $storeHits;

    /** @var int */
    public $storeMisses;
}
