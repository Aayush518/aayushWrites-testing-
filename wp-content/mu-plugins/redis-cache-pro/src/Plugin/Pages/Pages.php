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

namespace RedisCachePro\Plugin\Pages;

use Traversable;
use ArrayIterator;
use IteratorAggregate;

use RedisCachePro\Plugin;

/**
 * @implements \IteratorAggregate<\RedisCachePro\Plugin\Pages\Page>
 */
class Pages implements IteratorAggregate
{
    /**
     * The page instances.
     *
     * @var array<\RedisCachePro\Plugin\Pages\Page>
     */
    protected $pages;

    /**
     * Creates a new instance.
     *
     * @param  \RedisCachePro\Plugin  $plugin
     * @return void
     */
    public function __construct(Plugin $plugin)
    {
        $this->pages = [
            new Dashboard($plugin),
            new Updates($plugin),
            new Tools($plugin),
        ];

        if (! $this->current()) {
            $_GET['subpage'] = 'dashboard';
        }

        foreach ($this->pages as $page) {
            $page->boot();
        }
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator<int, \RedisCachePro\Plugin\Pages\Page>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->pages);
    }

    /**
     * Returns the current page, if available.
     *
     * @return \RedisCachePro\Plugin\Pages\Page|false
     */
    public function current()
    {
        $pages = array_filter($this->pages, function ($page) {
            return $page->isCurrent();
        });

        return reset($pages);
    }
}
