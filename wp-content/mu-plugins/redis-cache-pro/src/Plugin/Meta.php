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

namespace RedisCachePro\Plugin;

use RedisCachePro\Plugin;
use RedisCachePro\Diagnostics\Diagnostics;

/**
 * @mixin \RedisCachePro\Plugin
 */
trait Meta
{
    /**
     * Boot Meta component and register hooks.
     *
     * @return void
     */
    public function bootMeta()
    {
        add_filter('plugins_api', [$this, 'pluginInformation'], 10, 3);

        add_filter('plugin_row_meta', [$this, 'pluginRowMeta'], 10, 4);

        add_filter('plugin_action_links_object-cache.php', [$this, 'actionLinks']);
        add_filter("plugin_action_links_{$this->basename}", [$this, 'actionLinks']);
        add_filter("network_admin_plugin_action_links_{$this->basename}", [$this, 'actionLinks']);

        add_filter('manage_sites_action_links', [$this, 'siteActionLinks'], 10, 2);
    }

    /**
     * Adds useful links to the meta row of the plugin, must-use plugin and drop-in.
     *
     * @param  array<string>  $links
     * @param  string  $file
     * @param  array<string, string>  $data
     * @param  string  $status
     * @return array<string>
     */
    public function pluginRowMeta($links, $file, $data, $status)
    {
        if (! preg_match('/(Object|Redis) Cache Pro/', $data['Name'])) {
            return $links;
        }

        if ($file !== $this->basename && $file !== 'object-cache.php') {
            return $links;
        }

        $append = [];

        if ($file === 'object-cache.php' || $status === 'mustuse') {
            $append[] = sprintf(
                '<a href="%s" class="thickbox open-plugin-details-modal">View details</a>',
                self_admin_url('plugin-install.php?' . http_build_query([
                    'tab' => 'plugin-information',
                    'plugin' => $this->slug(),
                    'section' => 'changelog',
                    'TB_iframe' => 'true',
                    'width' => '600',
                    'height' => '800',
                ]))
            );
        }

        $append[] = sprintf('<a href="%s" target="_blank">Docs</a>', 'https://objectcache.pro/docs/');

        return array_merge($links, $append);
    }

    /**
     * Adds useful links to the plugin action link list.
     *
     * @param  array<string>  $links
     * @return array<string>
     */
    public function actionLinks($links)
    {
        global $wp_version;

        if (version_compare($wp_version, '5.2', '>=') && ! is_network_admin()) {
            $links = array_merge([
                sprintf('<a href="%s">Health</a>', admin_url('site-health.php')),
            ], $links);
        }

        if (current_user_can(Plugin::Capability)) {
            $links = array_merge([
                sprintf('<a href="%s">Settings</a>', network_admin_url($this->baseurl)),
            ], $links);
        }

        return $links;
    }

    /**
     * Adds a "Flush" link to sites in "Network Admin -> Sites".
     *
     * @param  array<string>  $actions
     * @param  int  $blog_id
     * @return array<string>
     */
    public function siteActionLinks($actions, $blog_id)
    {
        if (! $this->config->cluster) {
            return $actions;
        }

        if (! $this->blogFlushingEnabled()) {
            return $actions;
        }

        if (! current_user_can(Plugin::Capability) || ! current_user_can('manage_sites')) {
            return $actions;
        }

        if (! $this->diagnostics()->ping()) {
            return $actions;
        }

        array_splice($actions, 1, 0, [
            sprintf(
                '<a href="%s" title="Flush Object Cache">Flush</a>',
                esc_url(wp_nonce_url(
                    network_admin_url("sites.php?action=flush-blog-object-cache&id={$blog_id}"),
                    "flushblog_{$blog_id}"
                ))
            ),
        ]);

        return $actions;
    }

    /**
     * Fetch plugin information for update modal.
     *
     * @param  object|array<mixed>|false  $result
     * @param  string  $action
     * @param  object  $args
     * @return object|array<mixed>|false
     */
    public function pluginInformation($result, $action = null, $args = null)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if ($this->slug() === ($args->slug ?? null)) {
            $info = $this->pluginInfoRequest();

            if (is_wp_error($info)) {
                return false;
            }

            if (Diagnostics::isMustUse()) {
                $info->download_link = null;
            }

            $info->icons = (array) $info->icons;
            $info->banners = (array) $info->banners;
            $info->sections = (array) $info->sections;

            if (isset($info->contributors)) {
                $info->contributors = array_map('get_object_vars', get_object_vars($info->contributors));
            }

            return $info;
        }

        return $result;
    }
}
