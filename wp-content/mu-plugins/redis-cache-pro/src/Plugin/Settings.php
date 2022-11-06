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

/**
 * @mixin \RedisCachePro\Plugin
 */
trait Settings
{
    /**
     * Holds the plugin basename.
     *
     * @var string
     */
    protected $baseurl;

    /**
     * The settings page screen identifier.
     *
     * @var string
     */
    protected $screenId;

    /**
     * Instances of all setting pages.
     *
     * @var \RedisCachePro\Plugin\Pages\Pages
     */
    private $pages;

    /**
     * Boot settings component.
     *
     * @return void
     */
    public function bootSettings()
    {
        add_action('init', [$this, 'registerOptionsSetting']);

        add_action('rest_api_init', [new Api\Groups, 'register_routes']);
        add_action('rest_api_init', [new Api\Latency, 'register_routes']);
        add_action('rest_api_init', [new Api\Options($this), 'register_routes']);

        add_action('admin_init', [$this, 'maybeRedirectToSettings']);
        add_action('current_screen', [$this, 'setUpSettingsScreen']);

        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueuePointer']);

        add_filter('option_page_capability_objectcache', [$this, 'screenCapability']);
        add_filter('set_screen_option_objectcache_screen_options', [$this, 'sanitizeScreenOptions'], 10, 3);

        if (is_multisite()) {
            add_action('network_admin_menu', [$this, 'registerMenu']);
            add_action('current_screen', [$this, 'maybeRedirectToNetworkSettings'], -1);

            $this->baseurl = 'settings.php?page=objectcache';
            $this->screenId = 'settings_page_objectcache-network';
        } else {
            add_action('admin_menu', [$this, 'registerMenu']);

            $this->baseurl = 'options-general.php?page=objectcache';
            $this->screenId = 'settings_page_objectcache';
        }
    }

    /**
     * Returns the plugin's base URL.
     *
     * @return string
     */
    public function baseurl()
    {
        return $this->baseurl;
    }

    /**
     * Returns the plugin's screen identifier.
     *
     * @return string
     */
    public function screenId()
    {
        return $this->screenId;
    }

    /**
     * Return the capability needed to access the settings page.
     *
     * @return string
     */
    public function screenCapability()
    {
        return Plugin::Capability;
    }

    /**
     * Set up the settings page, if the current screen matches.
     *
     * @param  \WP_Screen  $screen
     * @return void
     */
    public function setUpSettingsScreen($screen)
    {
        if ($screen->id !== $this->screenId) {
            return;
        }

        add_action('in_admin_header', [$this, 'renderNavbar']);
        add_action('in_admin_header', [$this, 'removeForeignNotices'], PHP_INT_MAX);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);

        add_filter('update_footer', [$this, 'settingsFooterUpdate'], PHP_INT_MAX);
        add_filter('admin_footer_text', [$this, 'settingsFooterText'], PHP_INT_MAX);

        $this->pages = new Pages\Pages($this);

        if (! $this->pages->current()->isEnabled()) {
            wp_die('Sorry, you are not allowed to access this page.', 403);
        }
    }

    /**
     * Register the menu item.
     *
     * @return void
     */
    public function registerMenu()
    {
        global $wp_version;

        $isMultisite = is_multisite();
        $inNetworkAdmin = is_network_admin();

        $update = get_site_transient('objectcache_update');
        $updateAvailable = version_compare($update->version ?? '', '12.0', '>');

        $parameters = [
            $isMultisite && $inNetworkAdmin
                ? 'settings.php'
                : 'options-general.php',
            'Object Cache Pro',
            sprintf(
                'Object Cache %s',
                $updateAvailable ? '<span class="update-plugins count-1"><span class="update-count">1</span></span>' : ''
            ),
            $isMultisite ? 'manage_network_options' : Plugin::Capability,
            'objectcache',
            function () {
                $this->pages->current()->render();
            },
        ];

        if (version_compare($wp_version, '5.3', '>=')) {
            $parameters[] = 7;
        }

        add_submenu_page(...$parameters);
    }

    /**
     * Register the plugin `objectcache_options` setting.
     *
     * @return void
     */
    public function registerOptionsSetting()
    {
        register_setting(
            'objectcache',
            'objectcache_options',
            [
                'default' => $this->defaultOptions(),
                'sanitize_callback' => [$this, 'sanitizeOptions'],
            ]
        );

        add_action('update_option_objectcache_options', function ($old_value, $value) {
            $this->optionsUpdated($value, $old_value);
        }, 10, 2);

        add_action('update_site_option_objectcache_options', function ($option, $value, $old_value) {
            $this->optionsUpdated($value, $old_value);
        }, 10, 3);
    }

    /**
     * Called when the `objectcache_options` option has been changed.
     *
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $old
     * @return void
     */
    protected function optionsUpdated($new, $old)
    {
        $defaults = $this->defaultOptions();
        $new = wp_parse_args($new, $defaults);
        $old = wp_parse_args($old, $defaults);

        if ($new['channel'] !== $old['channel']) {
            wp_clean_plugins_cache();
        }
    }

    /**
     * Returns all options and their values or default values.
     *
     * @return array<string, mixed>
     */
    public function options()
    {
        $defaults = $this->defaultOptions();
        $options = get_site_option('objectcache_options', $defaults);

        if (is_array($options)) {
            return wp_parse_args($options, $defaults);
        }

        return $defaults;
    }

    /**
     * Returns the option's value or its default value.
     *
     * @param  string  $name
     * @return mixed
     */
    public function option($name)
    {
        return $this->options()[$name] ?? null;
    }

    /**
     * Returns the default options.
     *
     * @return array<string, mixed>
     */
    public function defaultOptions()
    {
        $defaults = [
            'channel' => 'stable',
            'flushlog' => false,
        ];

        /**
         * Filters the default options.
         *
         * @param  array  $defaults  Array of default options.
         */
        $filteredDefaults = (array) apply_filters('objectcache_default_options', $defaults);

        return array_filter(
            wp_parse_args($filteredDefaults, $defaults),
            function ($name) use ($defaults) {
                return array_key_exists($name, $defaults);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Sanitize the `objectcache_options` option values.
     * This will provide some safety when options are changed using WP CLI.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitizeOptions($input)
    {
        $defaults = $this->defaultOptions();
        $sanitizer = new Options\Sanitizer;

        $input = array_filter((array) $input, function ($name) use ($defaults) {
            return array_key_exists($name, $defaults);
        }, ARRAY_FILTER_USE_KEY);

        array_walk($input, function (&$value, $name) use ($sanitizer) {
            $value = $sanitizer->{$name}($value);
        });

        return $input;
    }

    /**
     * Sanitize the screen options before saving.
     *
     * @see set_screen_options()
     *
     * @param  false  $screen_option
     * @param  string  $option
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    public function sanitizeScreenOptions($screen_option, $option, $value)
    {
        return [
            'interval' => (int) ($value['analytics_interval'] ?? 0),
            'refresh' => (bool) ($value['analytics_refresh'] ?? false),
        ];
    }

    /**
     * Maybe redirect to the settings page if the plugin was just activated.
     *
     * @return void
     */
    public function maybeRedirectToSettings()
    {
        $nonce = \get_transient('objectcache_activated');

        if (! $nonce) {
            return;
        }

        if (! \wp_verify_nonce($nonce, 'objectcache-activated')) {
            return;
        }

        if (! \current_user_can(Plugin::Capability)) {
            return;
        }

        \delete_transient('objectcache_activated');

        \wp_safe_redirect(\network_admin_url($this->baseurl), 302, 'Object Cache Pro');
        exit;
    }

    /**
     * In multisite environments redirect setting pages to the Network Admin.
     *
     * @param  \WP_Screen  $screen
     * @return void
     */
    public function maybeRedirectToNetworkSettings($screen)
    {
        if ($screen->id !== 'settings_page_objectcache') {
            return;
        }

        wp_safe_redirect(network_admin_url($this->baseurl), 302, 'Object Cache Pro');
        exit;
    }

    /**
     * Enqueue the settings page stylesheet.
     *
     * @see setUpSettingsScreen()
     *
     * @return void
     */
    public function enqueueAdminStyles()
    {
        $styles = $this->asset('css/settings.css');

        if ($styles) {
            wp_enqueue_style('objectcache-settings', $styles, [], $this->version);
        } else {
            wp_add_inline_style('common', $this->inlineAsset('css/settings.css'));
        }
    }

    /**
     * Renders the settings navigation bar.
     *
     * @see setUpSettingsScreen()
     *
     * @return void
     */
    public function renderNavbar()
    {
        require __DIR__ . '/templates/navbar.phtml';
    }

    /**
     * Returns the `admin_footer_text` content.
     *
     * @see setUpSettingsScreen()
     *
     * @return string
     */
    public function settingsFooterText()
    {
        ob_start();

        require __DIR__ . '/templates/footer.phtml';

        return (string) ob_get_clean();
    }

    /**
     * Returns the `update_footer` content.
     *
     * @see setUpSettingsScreen()
     *
     * @return string
     */
    public function settingsFooterUpdate()
    {
        return sprintf('Version %s', $this->version);
    }

    /**
     * Maybe enqueue pointer.
     *
     * @param  string  $hook_suffix
     * @return void
     */
    public function maybeEnqueuePointer($hook_suffix)
    {
        /**
         * Filters whether the settings page pointer is shown.
         *
         * @param  bool  $omit  Whether to omit showing the settings page pointer.
         */
        if ((bool) apply_filters('objectcache_omit_settings_pointer', false)) {
            return;
        }

        if (! current_user_can(Plugin::Capability)) {
            return;
        }

        if (! in_array($hook_suffix, ['index.php', 'plugins.php'], true)) {
            return;
        }

        if (is_multisite() && ! is_network_admin()) {
            return;
        }

        $current_user = get_current_user_id();
        $dismissed = explode(',', (string) get_user_meta($current_user, 'dismissed_wp_pointers', true));

        if (in_array('objectcache-setting-pointer', $dismissed, true)) {
            return;
        }

        wp_register_script('objectcache-pointer', false, ['jquery', 'wp-pointer']);
        wp_enqueue_script('objectcache-pointer');

        $content = sprintf(
            'You can now access Object Cache Pro more easily. Open %s to see cache analytics and configure the various features included in the plugin.',
            sprintf(
                '<a href="%s">%s</a>',
                network_admin_url($this->baseurl),
                'Settings > Object Cache'
            )
        );

        wp_localize_script('objectcache-pointer', 'objectcache_pointer', [
            'heading' => 'Object Cache Pro',
            'content' => wp_kses($content, ['a' => ['href' => []]]),
        ]);

        wp_add_inline_script('objectcache-pointer', $this->inlineAsset('js/pointer.js'));
    }

    /**
     * STFU!
     *
     * @return void
     */
    public function removeForeignNotices()
    {
        global $wp_filter;

        foreach ([
            'admin_notices',
            'all_admin_notices',
            'user_admin_notices',
            'network_admin_notices',
        ] as $hook) {
            foreach ($wp_filter[$hook] ?? [] as $priority => $callbacks) {
                foreach ($callbacks as $idx => $callback) {
                    if (
                        is_array($callback['function']) &&
                        is_object($callback['function'][0]) &&
                        preg_match('/^(Redis|Object)CachePro\\\/', get_class($callback['function'][0]))
                    ) {
                        continue;
                    }

                    unset($wp_filter[$hook]->callbacks[$priority][$idx]);
                }
            }
        }
    }
}
