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

use Throwable;
use ReflectionClass;

use RedisCachePro\Configuration\Configuration;
use RedisCachePro\Connections\PhpRedisConnection;
use RedisCachePro\Exceptions\ObjectCacheException;

class PhpRedisObjectCache extends ObjectCache implements MeasuredObjectCacheInterface
{
    use Concerns\PrefetchesKeys,
        Concerns\FlushesNetworks,
        Concerns\TakesMeasurements,
        Concerns\SplitsAllOptionsIntoHash;

    /**
     * The client name.
     *
     * @var string
     */
    const Client = 'PhpRedis';

    /**
     * The connection instance.
     *
     * @var \RedisCachePro\Connections\PhpRedisConnection
     */
    protected $connection;

    /**
     * The amount of times Redis had the object already cached.
     *
     * @var int
     */
    protected $storeHits = 0;

    /**
     * Amount of times the Redis did not have the object.
     *
     * @var int
     */
    protected $storeMisses = 0;

    /**
     * Amount of times the cache read from Redis.
     *
     * @var int
     */
    protected $storeReads = 0;

    /**
     * Amount of times the cache wrote to Redis.
     *
     * @var int
     */
    protected $storeWrites = 0;

    /**
     * Create new PhpRedis object cache instance.
     *
     * @param  \RedisCachePro\Connections\PhpRedisConnection  $connection
     * @param  \RedisCachePro\Configuration\Configuration  $config
     */
    public function __construct(PhpRedisConnection $connection, Configuration $config)
    {
        $this->config = $config;
        $this->connection = $connection;
        $this->log = $this->config->logger;
    }

    /**
     * Adds data to the cache, if the cache key doesn't already exist.
     *
     * @param  int|string  $key
     * @param  mixed  $data
     * @param  string  $group
     * @param  int  $expire
     * @return bool
     */
    public function add($key, $data, string $group = 'default', int $expire = 0): bool
    {
        if (function_exists('wp_suspend_cache_addition') && \wp_suspend_cache_addition()) {
            return false;
        }

        if (! $id = $this->id($key, $group)) {
            return false;
        }

        if ($this->hasInMemory($id, $group)) {
            return false;
        }

        if ($this->isNonPersistentGroup($group)) {
            $this->storeInMemory($id, $data, $group);

            return true;
        }

        try {
            $result = (bool) $this->write($id, $data, $expire, 'NX');

            if ($result) {
                $this->storeInMemory($id, $data, $group);
            }

            return $result;
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }
    }

    /**
     * Adds multiple values to the cache in one call, if the cache keys doesn't already exist.
     *
     * @param  array<int|string, mixed>  $data
     * @param  string  $group
     * @param  int  $expire
     * @return array<int|string, bool>
     */
    public function add_multiple(array $data, string $group = 'default', int $expire = 0): array
    {
        if (empty($data)) {
            return [];
        }

        if (function_exists('wp_suspend_cache_addition') && \wp_suspend_cache_addition()) {
            return array_combine(array_keys($data), array_fill(0, count($data), false));
        }

        $results = [];

        if ($this->isNonPersistentGroup($group)) {
            foreach ($data as $key => $value) {
                $id = $this->id($key, $group);

                $results[$key] = \is_string($id) && ! $this->hasInMemory($id, $group);

                if ($results[$key]) {
                    $this->storeInMemory((string) $id, $value, $group);
                }
            }

            return $results;
        }

        foreach ($data as $key => $value) {
            $id = $this->id($key, $group);

            if (! $id || $this->hasInMemory($id, $group)) {
                $results[$key] = false;
            }
        }

        $remainingData = array_diff_key($data, $results);

        if (empty($remainingData)) {
            return $results;
        }

        try {
            $response = $this->multiwrite($remainingData, $group, $expire, 'NX');
        } catch (Throwable $exception) {
            $this->error($exception);

            return array_combine(array_keys($data), array_fill(0, count($data), false));
        }

        foreach ($response as $key => $result) {
            if ($result['id'] && $result['response']) {
                $this->storeInMemory($result['id'], $data[$key], $group);
            }

            $results[$key] = $result['response'];
        }

        $order = array_flip(array_keys($data));

        uksort($results, function ($a, $b) use ($order) {
            return $order[$a] - $order[$b];
        });

        return $results;
    }

    /**
     * Boots the cache.
     *
     * @return bool
     */
    public function boot(): bool
    {
        $this->prefetch();

        return true;
    }

    /**
     * Closes the cache.
     *
     * @return bool
     */
    public function close(): bool
    {
        $this->storePrefetches();
        $this->storeMeasurements();

        return true;
    }

    /**
     * Decrements numeric cache item's value.
     *
     * @param  int|string  $key
     * @param  int  $offset
     * @param  string  $group
     * @return int|false
     */
    public function decr($key, int $offset = 1, string $group = 'default')
    {
        if (! $id = $this->id($key, $group)) {
            return false;
        }

        if ($this->isNonPersistentGroup($group)) {
            if (! $this->hasInMemory($id, $group)) {
                return false;
            }

            $value = $this->getFromMemory($id, $group);
            $value = $this->decrement($value, $offset);

            $this->storeInMemory($id, $value, $group);

            return $value;
        }

        try {
            $this->storeReads++;
            $value = $this->connection->get($id);

            if ($value === false) {
                return false;
            }

            $this->storeWrites++;
            $value = $this->decrement($value, $offset);
            $result = $this->connection->set($id, $value);

            if ($result) {
                $this->storeInMemory($id, $value, $group);
            }

            return $value;
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }
    }

    /**
     * Removes the cache contents matching key and group from the runtime memory cache.
     *
     * @param  int|string  $key
     * @param  string  $group
     * @return bool
     */
    public function deleteFromMemory($key, string $group = 'default')
    {
        if ($deleted = parent::deleteFromMemory($key, $group)) {
            unset($this->prefetch[$group][$key]);
        }

        return $deleted;
    }

    /**
     * Removes the cache contents matching key and group.
     *
     * @param  int|string  $key
     * @param  string  $group
     * @return bool
     */
    public function delete($key, string $group = 'default'): bool
    {
        if (! $id = $this->id($key, $group)) {
            return false;
        }

        $deletedFromMemory = $this->deleteFromMemory($key, $group);

        if ($this->isNonPersistentGroup($group)) {
            return $deletedFromMemory;
        }

        try {
            if ($this->isAllOptionsId($id)) {
                return $this->deleteAllOptions($id);
            }

            $this->storeWrites++;

            return (bool) $this->connection->del($id);
        } catch (Throwable $exception) {
            $this->error($exception);
        }

        return false;
    }

    /**
     * Deletes multiple values from the cache in one call.
     *
     * @param  array<int|string>  $keys
     * @param  string  $group
     * @return array<int|string, bool>
     */
    public function delete_multiple(array $keys, string $group = 'default'): array
    {
        if (empty($keys)) {
            return [];
        }

        $results = [];

        if ($this->isNonPersistentGroup($group)) {
            foreach ($keys as $key) {
                $results[$key] = $this->deleteFromMemory($key, $group);
            }

            return $results;
        }

        foreach ($keys as $key) {
            $results[$key] = $this->id($key, $group);
        }

        $deletes = [];
        $command = $this->config->async_flush ? 'UNLINK' : 'DEL';

        try {
            $pipe = $this->connection->pipeline();

            foreach ($results as $key => $id) {
                if (! $id) {
                    continue;
                }

                unset($this->cache[$group][$id]);
                unset($this->prefetch[$group][$key]);

                if ($this->isAllOptionsId($id)) {
                    $allOptionsId = $id;
                    $allOptionsKey = $key;
                    continue;
                }

                $deletes[] = $id;
                $pipe->{$command}($id);
            }

            $this->storeWrites++;
            $deletes = array_combine($deletes, array_map('boolval', $pipe->exec()));
        } catch (Throwable $exception) {
            $this->error($exception);

            return array_combine($keys, array_fill(0, count($keys), false));
        }

        if (isset($allOptionsId, $allOptionsKey)) {
            $results[$allOptionsKey] = $this->deleteAllOptions($allOptionsId);
        }

        return array_map(function ($result) use ($deletes) {
            return is_string($result) ? $deletes[$result] : $result;
        }, $results);
    }

    /**
     * Removes all items from Redis, the runtime cache and gathered prefetches.
     *
     * @return bool
     */
    public function flush(): bool
    {
        $this->flush_runtime();

        if ($this->isMultisite && $this->handleBlogFlush()) {
            return $this->flushBlog(get_current_blog_id());
        }

        if ($this->config->analytics->enabled && $this->config->analytics->persist) {
            return $this->flushWithoutAnalytics();
        }

        try {
            return $this->connection->flushdb();
        } catch (Throwable $exception) {
            $this->error($exception);
        }

        return false;
    }

    /**
     * Removes all cache items from the in-memory runtime cache.
     *
     * @return bool
     */
    public function flush_runtime(): bool
    {
        $this->cache = [];
        $this->prefetch = [];

        return true;
    }

    /**
     * Removes all cache items in given group.
     *
     * @param  string  $group
     * @return bool
     */
    public function flush_group(string $group): bool
    {
        unset($this->cache[$group]);
        unset($this->prefetch[$group]);

        if ($this->isNonPersistentGroup($group)) {
            return true;
        }

        $pattern = $this->id('*', $group);

        if ($pattern === false) {
            return false;
        }

        if ($this->isMultisite && ! $this->isGlobalGroup($group)) {
            $pattern = str_replace("{$this->blogId}:", '*:', (string) $pattern);
        }

        try {
            $this->deleteByPattern($pattern);
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }

        return true;
    }

    /**
     * Retrieves the cache contents from the cache by key and group.
     *
     * @param  int|string  $key
     * @param  string  $group
     * @param  bool  $force
     * @param  bool  &$found
     * @return mixed|false
     */
    public function get($key, string $group = 'default', bool $force = false, &$found = null)
    {
        if (! $id = $this->id($key, $group)) {
            $found = false;

            return false;
        }

        $cachedInMemory = $this->hasInMemory($id, $group);

        if ($this->isNonPersistentGroup($group)) {
            if (! $cachedInMemory) {
                $found = false;
                $this->misses += 1;

                return false;
            }

            $found = true;
            $this->hits += 1;

            return $this->getFromMemory($id, $group);
        }

        if ($this->prefetched) {
            $this->prefetch[$group][$key] = true;
        }

        if ($cachedInMemory && ! $force) {
            $found = true;
            $this->hits += 1;

            return $this->getFromMemory($id, $group);
        }

        $found = false;

        try {
            if ($this->isAllOptionsId($id)) {
                $data = $this->getAllOptions($id);
            } else {
                $this->storeReads++;
                $data = $this->connection->get($id);
            }
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }

        if ($data === false) {
            $this->misses += 1;
            $this->storeMisses += 1;

            return false;
        }

        $found = true;
        $this->hits += 1;
        $this->storeHits += 1;

        $this->storeInMemory($id, $data, $group);

        return $data;
    }

    /**
     * Retrieves multiple values from the cache in one call.
     *
     * @param  array<int|string>  $keys
     * @param  string  $group
     * @param  bool  $force
     * @return array<int|string, mixed>
     */
    public function get_multiple(array $keys, string $group = 'default', bool $force = false)
    {
        if (empty($keys)) {
            return [];
        }

        $values = [];

        if ($this->isNonPersistentGroup($group)) {
            foreach ($keys as $key) {
                $id = $this->id($key, $group);

                if ($id && $this->hasInMemory($id, $group)) {
                    $this->hits += 1;
                    $values[$key] = $this->getFromMemory($id, $group);
                } else {
                    $this->misses += 1;
                    $values[$key] = false;
                }
            }

            return $values;
        }

        if ($this->prefetched) {
            foreach ($keys as $key) {
                $this->prefetch[$group][$key] = true;
            }
        }

        $remainingKeys = [];

        foreach ($keys as $key) {
            $values[$key] = false;

            if ($id = $this->id($key, $group)) {
                if (! $force && $this->hasInMemory($id, $group)) {
                    $this->hits += 1;
                    $values[$key] = $this->getFromMemory($id, $group);
                } else {
                    $remainingKeys[] = $key;
                }
            }
        }

        if (empty($remainingKeys)) {
            return $values;
        }

        $ids = array_map(function ($key) use ($group) {
            return (string) $this->id($key, $group);
        }, $remainingKeys);

        try {
            $this->storeReads++;
            $data = $this->connection->mget($ids);

            foreach ($remainingKeys as $index => $key) {
                $values[$key] = $data[$index];

                if ($data[$index] === false) {
                    $this->misses += 1;
                    $this->storeMisses += 1;

                    continue;
                }

                $this->hits += 1;
                $this->storeHits += 1;

                if ($this->config->prefetch && ! $this->prefetched) {
                    $this->prefetches++;
                }

                $this->storeInMemory($ids[$index], $data[$index], $group);
            }
        } catch (Throwable $exception) {
            $this->error($exception);
        }

        return $values;
    }

    /**
     * Whether the key exists in the cache.
     *
     * @param  int|string  $key
     * @param  string  $group
     * @return bool
     */
    public function has($key, string $group = 'default'): bool
    {
        if (! $id = $this->id($key, $group)) {
            return false;
        }

        if ($this->hasInMemory($id, $group)) {
            return true;
        }

        if ($this->isNonPersistentGroup($group)) {
            return false;
        }

        try {
            $this->storeReads++;

            return (bool) $this->connection->exists($id);
        } catch (Throwable $exception) {
            $this->error($exception);
        }

        return false;
    }

    /**
     * Increment numeric cache item's value.
     *
     * @param  int|string  $key
     * @param  int  $offset
     * @param  string  $group
     * @return int|false
     */
    public function incr($key, int $offset = 1, string $group = 'default')
    {
        if (! $id = $this->id($key, $group)) {
            return false;
        }

        if ($this->isNonPersistentGroup($group)) {
            if (! $this->hasInMemory($id, $group)) {
                return false;
            }

            $value = $this->getFromMemory($id, $group);
            $value = $this->increment($value, $offset);

            $this->storeInMemory($id, $value, $group);

            return $value;
        }

        try {
            $this->storeReads++;
            $value = $this->connection->get($id);

            if ($value === false) {
                return false;
            }

            $this->storeWrites++;
            $value = $this->increment($value, $offset);
            $result = $this->connection->set($id, $value);

            if ($result) {
                $this->storeInMemory($id, $value, $group);
            }

            return $value;
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }
    }

    /**
     * Replaces the contents of the cache with new data.
     *
     * @param  int|string  $key
     * @param  mixed  $data
     * @param  string  $group
     * @param  int  $expire
     * @return bool
     */
    public function replace($key, $data, string $group = 'default', int $expire = 0): bool
    {
        if (! $id = $this->id($key, $group)) {
            return false;
        }

        if ($this->isNonPersistentGroup($group)) {
            if (! $this->hasInMemory($id, $group)) {
                return false;
            }

            $this->storeInMemory($id, $data, $group);

            return true;
        }

        try {
            $result = (bool) $this->write($id, $data, $expire, 'XX');

            if ($result) {
                $this->storeInMemory($id, $data, $group);
            }

            return $result;
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }
    }

    /**
     * Saves the data to the cache.
     *
     * @param  int|string  $key
     * @param  mixed  $data
     * @param  string  $group
     * @param  int  $expire
     * @return bool
     */
    public function set($key, $data, string $group = 'default', int $expire = 0): bool
    {
        if (! $id = $this->id($key, $group)) {
            return false;
        }

        if ($this->isNonPersistentGroup($group)) {
            $this->storeInMemory($id, $data, $group);

            return true;
        }

        try {
            $result = (bool) $this->write($id, $data, $expire);

            if ($result) {
                $this->storeInMemory($id, $data, $group);
            }

            return $result;
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }
    }

    /**
     * Sets multiple values to the cache in one call.
     *
     * @param  array<int|string, mixed>  $data
     * @param  string  $group
     * @param  int  $expire
     * @return array<int|string, bool>
     */
    public function set_multiple(array $data, string $group = 'default', int $expire = 0): array
    {
        if (empty($data)) {
            return [];
        }

        if ($this->isNonPersistentGroup($group)) {
            $results = [];

            foreach ($data as $key => $value) {
                $id = $this->id($key, $group);
                $results[$key] = \is_string($id);

                if ($results[$key]) {
                    $this->storeInMemory((string) $id, $value, $group);
                }
            }

            return $results;
        }

        try {
            $results = $this->multiwrite($data, $group, $expire);
        } catch (Throwable $exception) {
            $this->error($exception);

            return array_combine(array_keys($data), array_fill(0, count($data), false));
        }

        foreach ($results as $key => $result) {
            if ($result['id'] && $result['response']) {
                $this->storeInMemory($result['id'], $data[$key], $group);
            }

            $results[$key] = $result['response'];
        }

        return $results;
    }

    /**
     * Switches the internal blog ID.
     *
     * @param  int $blog_id
     * @return bool
     */
    public function switch_to_blog(int $blog_id): bool
    {
        if ($this->isMultisite) {
            $this->setBlogId($blog_id);

            return true;
        }

        return false;
    }

    /**
     * Writes the given key to Redis and enforces the `maxttl` configuration option.
     *
     * @param  string  $id
     * @param  mixed  $data
     * @param  int  $expire
     * @param  string  $option
     * @return bool
     */
    protected function write(string $id, $data, int $expire = 0, $option = null): bool
    {
        if ($expire < 0) {
            $expire = 0;
        }

        $maxttl = $this->config->maxttl;

        if ($maxttl && ($expire === 0 || $expire > $maxttl)) {
            $expire = $maxttl;
        }

        if ($this->isAllOptionsId($id)) {
            return $this->syncAllOptions($id, $data);
        }

        $this->storeWrites++;

        if ($expire && $option) {
            return $this->connection->set($id, $data, [$option, 'EX' => $expire]);
        }

        if ($expire) {
            return $this->connection->setex($id, $expire, $data);
        }

        if ($option) {
            return $this->connection->set($id, $data, [$option]);
        }

        return $this->connection->set($id, $data);
    }

    /**
     * Writes the given keys to Redis and enforces the `maxttl` configuration option.
     *
     * @param  array<int|string, mixed>  $data
     * @param  int  $expire
     * @param  string  $option
     * @return array<int|string, array{id: string|false, response: mixed}>
     */
    protected function multiwrite(array $data, string $group, int $expire = 0, $option = null): array
    {
        if ($expire < 0) {
            $expire = 0;
        }

        $maxttl = $this->config->maxttl;

        if ($maxttl && ($expire === 0 || $expire > $maxttl)) {
            $expire = $maxttl;
        }

        $results = [];

        $pipe = $this->connection->pipeline();

        foreach ($data as $key => $value) {
            if (! $id = $this->id($key, $group)) {
                $results[$key] = ['id' => false, 'response' => false];
                continue;
            }

            if ($this->isAllOptionsId($id)) {
                throw new ObjectCacheException('Unable to multi-write `alloptions` key');
            }

            $results[$key] = ['id' => $id, 'response' => false];

            if ($expire && $option) {
                $pipe->set($id, $value, [$option, 'EX' => $expire]);
                continue;
            }

            if ($expire) {
                $pipe->setex($id, $expire, $value);
                continue;
            }

            if ($option) {
                $pipe->set($id, $value, [$option]);
                continue;
            }

            $pipe->set($id, $value);
        }

        $keys = array_keys($results);

        $this->storeWrites++;

        foreach ($pipe->exec() as $i => $result) {
            $results[$keys[$i]]['response'] = $result;
        }

        return $results;
    }

    /**
     * Returns various information about the object cache.
     *
     * @return \RedisCachePro\Support\PhpRedisObjectCacheInfo
     */
    public function info()
    {
        $server = $this->connection->memoize('info');

        /** @var \RedisCachePro\Support\PhpRedisObjectCacheInfo $info */
        $info = parent::info();

        $info->status = (bool) $this->connection->memoize('ping');
        $info->prefetches = $this->config->prefetch ? $this->prefetches : null;
        $info->storeReads = $this->storeReads;
        $info->storeWrites = $this->storeWrites;
        $info->storeHits = $this->storeHits;
        $info->storeMisses = $this->storeMisses;

        $info->meta = array_filter([
            'Redis Version' => $server['redis_version'],
            'Redis Memory' => size_format($server['used_memory'], 2),
            'Redis Eviction' => $server['maxmemory_policy'] ?? null,
            'Cache' => (new ReflectionClass($this))->getShortName(),
            'Connector' => (new ReflectionClass($this->config->connector))->getShortName(),
            'Connection' => (new ReflectionClass($this->connection))->getShortName(),
            'Logger' => (new ReflectionClass($this->log))->getShortName(),
        ]);

        return $info;
    }

    /**
     * Returns metrics about the object cache.
     *
     * @param  bool  $extended
     * @return \RedisCachePro\Support\PhpRedisObjectCacheMetrics
     */
    public function metrics($extended = false)
    {
        /** @var \RedisCachePro\Support\PhpRedisObjectCacheMetrics $metrics */
        $metrics = parent::metrics($extended);

        $metrics->prefetches = $this->config->prefetch ? $this->prefetches : null;
        $metrics->storeReads = $this->storeReads;
        $metrics->storeWrites = $this->storeWrites;
        $metrics->storeHits = $this->storeHits;
        $metrics->storeMisses = $this->storeMisses;

        return $metrics;
    }

    /**
     * Delete keys by patterns in chunks.
     *
     * @internal
     * @param  string|string[]  $patterns
     * @return void
     */
    protected function deleteByPattern($patterns)
    {
        $command = $this->config->async_flush ? 'UNLINK' : 'DEL';
        $script = file_get_contents(__DIR__ . '/scripts/chunked-scan.lua');

        if (! is_array($patterns)) {
            $patterns = [$patterns];
        }

        $this->connection->withoutTimeout(function ($connection) use ($script, $patterns, $command) {
            $connection->eval(
                $script,
                array_merge($patterns, [$command]),
                count($patterns)
            );
        });

        $this->storeWrites++;
    }
}
