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

use Throwable;
use LogicException;

use RedisCachePro\Configuration\Configuration;

/**
 * In non-multisite environments and when the `flush_network` configuration option is set to `all`,
 * the `FLUSHDB` command is executed when `wp_cache_flush()` is called.
 *
 * When `flush_network` is set to `site`, only the current blog's cache is cleared using a Lua script.
 *
 * When `flush_network` is set to `global`, in addition to the
 * current blog's cache all global groups are flushed as well.
 */
trait FlushesNetworks
{
    /**
     * Returns `true` when the current blog is not the network's main site.
     *
     * @return bool
     */
    protected function handleBlogFlush(): bool
    {
        if (! in_array($this->config->flush_network, [
            Configuration::NETWORK_FLUSH_SITE,
            Configuration::NETWORK_FLUSH_GLOBAL,
        ])) {
            return false;
        }

        return ! \is_main_site();
    }

    /**
     * Removes all cache items for a single blog in multisite environments,
     * otherwise defaults to flushing the entire database.
     *
     * Unless the `$flush_network` parameter is given this method
     * will default to `flush_network` configuration option.
     *
     * @param  int  $siteId
     * @param  string|null  $flush_network
     * @return bool
     */
    public function flushBlog(int $siteId, string $flush_network = null): bool
    {
        if (is_null($flush_network)) {
            $flush_network = $this->config->flush_network;
        }

        if (! $this->isMultisite || $flush_network === Configuration::NETWORK_FLUSH_ALL) {
            return $this->flush();
        }

        if ($this->config->cluster) {
            throw new LogicException('Redis Cluster does not support blog flushing');
        }

        $originalBlogId = $this->blogId;
        $this->blogId = $siteId;

        $patterns = [
            str_replace(':deadf00d', '', (string) $this->id('*', dechex(3735941133))),
        ];

        if ($flush_network === Configuration::NETWORK_FLUSH_GLOBAL) {
            array_push($patterns, ...array_map(function ($group) {
                return $this->id('*', $group);
            }, $this->globalGroups()));
        }

        $this->blogId = $originalBlogId;

        try {
            $this->deleteByPattern(array_filter($patterns));
        } catch (Throwable $exception) {
            $this->error($exception);

            return false;
        }

        return parent::flushBlog($siteId, $flush_network);
    }
}
