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

namespace RedisCachePro\ObjectCaches;

use RedisCachePro\Configuration\Configuration;
use RedisCachePro\Connections\ConnectionInterface;

interface ObjectCacheInterface
{
    /**
     * Boots the cache.
     *
     * @return bool
     */
    public function boot(): bool;

    /**
     * Returns the configuration instance.
     *
     * @return \RedisCachePro\Configuration\Configuration
     */
    public function config(): Configuration;

    /**
     * Returns the connection instance.
     *
     * @return \RedisCachePro\Connections\ConnectionInterface|null
     */
    public function connection(): ?ConnectionInterface; // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound

    /**
     * Adds data to the cache, if the cache key doesn't already exist.
     *
     * @param  int|string  $key
     * @param  mixed  $data
     * @param  string  $group
     * @param  int  $expire
     * @return bool
     */
    public function add($key, $data, string $group = 'default', int $expire = 0): bool;

    /**
     * Adds multiple values to the cache in one call, if the cache keys doesn't already exist.
     *
     * @param  array<int|string, mixed>  $data
     * @param  string  $group
     * @param  int  $expire
     * @return array<int|string, bool>
     */
    public function add_multiple(array $data, string $group = 'default', int $expire = 0): array;

    /**
     * Set given groups as global.
     *
     * @param  array<string>  $groups
     * @return void
     */
    public function add_global_groups(array $groups);

    /**
     * Set given groups as non-persistent.
     *
     * @param  array<string>  $groups
     * @return void
     */
    public function add_non_persistent_groups(array $groups);

    /**
     * Set given groups as non-prefetchable.
     *
     * @param  array<string>  $groups
     * @return void
     */
    public function add_non_prefetchable_groups(array $groups);

    /**
     * Closes the cache.
     *
     * @return bool
     */
    public function close(): bool;

    /**
     * Decrements numeric cache item's value.
     *
     * @param  int|string  $key
     * @param  int  $offset
     * @param  string  $group
     * @return int|false
     */
    public function decr($key, int $offset = 1, string $group = 'default');

    /**
     * Removes the cache contents matching key and group.
     *
     * @param  int|string  $key
     * @param  string  $group
     * @return bool
     */
    public function delete($key, string $group = 'default'): bool;

    /**
     * Deletes multiple values from the cache in one call.
     *
     * @param  array<int|string>  $keys
     * @param  string  $group
     * @return array<int|string, bool>
     */
    public function delete_multiple(array $keys, string $group = 'default'): array;

    /**
     * Removes all cache items.
     *
     * @return bool
     */
    public function flush(): bool;

    /**
     * Removes all cache items from the in-memory runtime cache.
     *
     * @return bool
     */
    public function flush_runtime(): bool;

    /**
     * Removes all cache items given group.
     *
     * @param  string  $group
     * @return bool
     */
    public function flush_group(string $group): bool;

    /**
     * Retrieves the cache contents from the cache by key and group.
     *
     * @param  int|string  $key
     * @param  string  $group
     * @param  bool  $force
     * @param  bool  &$found
     * @return mixed|false
     */
    public function get($key, string $group = 'default', bool $force = false, &$found = null);

    /**
     * Retrieves multiple values from the cache in one call.
     *
     * @param  array<int|string>  $keys
     * @param  string  $group
     * @param  bool  $force
     * @return array<int|string, mixed>
     */
    public function get_multiple(array $keys, string $group = 'default', bool $force = false);

    /**
     * Whether the key exists in the cache.
     *
     * @param  int|string  $key
     * @param  string  $group
     * @return bool
     */
    public function has($key, string $group = 'default'): bool;

    /**
     * Increment numeric cache item's value.
     *
     * @param  int|string  $key
     * @param  int  $offset
     * @param  string  $group
     * @return int|false
     */
    public function incr($key, int $offset = 1, string $group = 'default');

    /**
     * Replaces the contents of the cache with new data.
     *
     * @param  int|string  $key
     * @param  mixed  $data
     * @param  string  $group
     * @param  int  $expire
     * @return bool
     */
    public function replace($key, $data, string $group = 'default', int $expire = 0): bool;

    /**
     * Saves the data to the cache.
     *
     * @param  int|string  $key
     * @param  mixed  $data
     * @param  string  $group
     * @param  int  $expire
     * @return bool
     */
    public function set($key, $data, string $group = 'default', int $expire = 0): bool;

    /**
     * Sets multiple values to the cache in one call.
     *
     * @param  array<int|string, mixed>  $data
     * @param  string  $group
     * @param  int  $expire
     * @return array<int|string, bool>
     */
    public function set_multiple(array $data, string $group = 'default', int $expire = 0): array;

    /**
     * Switches the internal blog ID.
     *
     * @param  int $blog_id
     * @return bool
     */
    public function switch_to_blog(int $blog_id): bool;
}
