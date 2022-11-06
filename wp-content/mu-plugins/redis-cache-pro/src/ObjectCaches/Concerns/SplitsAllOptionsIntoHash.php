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

namespace RedisCachePro\ObjectCaches\Concerns;

/**
 * When the `split_alloptions` configuration option is enabled, the `alloptions` cache key is stored
 * in a Redis hash, instead of a single key. For some setups this helps to reduce data transfer
 * and will minimize race conditions when several processes update options simultaneously.
 */
trait SplitsAllOptionsIntoHash
{
    /**
     * Returns `true` when `alloptions` splitting is enabled
     * and the given `$id` is the `alloptions` cache key.
     *
     * @param  string  $id
     * @return bool
     */
    protected function isAllOptionsId(string $id): bool
    {
        if (! $this->config->split_alloptions) {
            return false;
        }

        return $id === $this->id('alloptions', 'options');
    }

    /**
     * Returns a single `alloptions` array from the Redis hash.
     *
     * @param  string  $id
     * @return array<mixed>|false
     */
    protected function getAllOptions(string $id)
    {
        $this->storeReads++;
        $alloptions = $this->connection->hgetall("{$id}:hash");

        return empty($alloptions) ? false : $alloptions;
    }

    /**
     * Keeps the `alloptions` Redis hash in sync.
     *
     * 1. All keys present in memory, but not in given data, will be deleted
     * 2. All keys present in data, but not in memory, or with a different value will be set
     *
     * @param  string  $id
     * @param  mixed  $data
     * @return bool
     */
    protected function syncAllOptions(string $id, $data): bool
    {
        $runtimeCache = $this->hasInMemory($id, 'options')
            ? $this->getFromMemory($id, 'options')
            : [];

        $removedOptions = array_keys(array_diff_key($runtimeCache, $data));

        if (! empty($removedOptions)) {
            $this->storeWrites++;
            $this->connection->hdel("{$id}:hash", ...$removedOptions);
        }

        $changedOptions = array_diff_assoc($data, $runtimeCache);

        if (! empty($changedOptions)) {
            $this->storeWrites++;
            $this->connection->hmset("{$id}:hash", $changedOptions);
        }

        return true;
    }

    /**
     * Deletes the `alloptions` hash.
     *
     * @param  string  $id
     * @return bool
     */
    protected function deleteAllOptions(string $id): bool
    {
        $this->storeWrites++;

        $command = $this->config->async_flush ? 'UNLINK' : 'DEL';

        return (bool) $this->connection->{$command}("{$id}:hash");
    }
}
