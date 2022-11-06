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

use RedisCachePro\Plugin;
use const RedisCachePro\Version;

abstract class Page
{
    /**
     * The plugin instance.
     *
     * @var \RedisCachePro\Plugin
     */
    protected $plugin;

    /**
     * Creates a new instance.
     *
     * @param  \RedisCachePro\Plugin  $plugin
     * @return void
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Returns the page title.
     *
     * @return string
     */
    abstract public function title();

    /**
     * Returns the page slug.
     *
     * @return string
     */
    public function slug()
    {
        return strtolower(substr((string) strrchr(get_called_class(), '\\'), 1));
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    abstract public function render();

    /**
     * Boot the settings page and its components.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Enqueue the settings script.
     *
     * @return void
     */
    public function enqueueScript()
    {
        \wp_register_script('objectcache', false);
        \wp_enqueue_script('objectcache');

        \wp_localize_script(
            'objectcache',
            'objectcache',
            array_merge([
                'rest' => [
                    'nonce' => \wp_create_nonce('wp_rest'),
                    'url'  => \rest_url(),
                ],
                'gmt_offset' => \get_option('gmt_offset'),
            ], $this->enqueueScriptExtra())
        );
    }

    /**
     * Returns extra data to be attached to `window.objectcache`.
     *
     * @return array<mixed>
     */
    protected function enqueueScriptExtra()
    {
        return [];
    }

    /**
     * Enqueues the form script.
     *
     * @return void
     */
    protected function enqueueOptionsScript()
    {
        $script = $this->plugin->asset('js/options.js');

        \wp_register_script('objectcache-options', $script, ['jquery', 'wp-util', 'objectcache'], Version);
        \wp_enqueue_script('objectcache-options');

        if (! $script) {
            \wp_add_inline_script('objectcache-options', $this->plugin->inlineAsset('js/options.js'));
        }
    }

    /**
     * Returns the page's URL.
     *
     * @return string
     */
    public function url()
    {
        return network_admin_url(
            sprintf('%s&subpage=%s', $this->plugin->baseurl(), $this->slug())
        );
    }

    /**
     * Whether this page is enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Whether this is the current page.
     *
     * @return bool
     */
    public function isCurrent()
    {
        return ($_GET['subpage'] ?? '') === $this->slug();
    }
}
