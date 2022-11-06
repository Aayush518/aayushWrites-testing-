<?php

declare(strict_types=1);

namespace RedisCachePro\Extensions\QueryMonitor;

use QM_Output_Html;

class ObjectCacheHtmlOutput extends QM_Output_Html
{
    /**
     * Creates a new instance.
     *
     * @param  \QM_Collector  $collector
     */
    public function __construct($collector)
    {
        parent::__construct($collector);

        add_filter('qm/output/menus', [$this, 'admin_menu'], 30);
        add_filter('qm/output/panel_menus', [$this, 'panel_menu']);
    }

    /**
     * Returns the name of the QM outputter.
     *
     * @return string
     */
    public function name()
    {
        return 'Object Cache';
    }

    /**
     * Registers the Admin Bar menu item.
     *
     * @param  array<string, mixed>  $menu
     * @return array<string, mixed>
     */
    public function admin_menu(array $menu)
    {
        $data = $this->collector->get_data();

        $title = $data['valid-dropin']
            ? sprintf('%s (%s%%)', $this->name(), $data['cache_hit_percentage'] ?? 0)
            : $this->name();

        $args = [
            'title' => esc_html($title),
        ];

        if (empty($data['status'])) {
            $args['meta']['classname'] = 'qm-alert';
        }

        if (! empty($data['errors'])) {
            $args['meta']['classname'] = 'qm-warning';
        }

        $menu[$this->collector->id()] = $this->menu($args);

        return $menu;
    }

    /**
     * Injects the panel right before QM's "Request" menu item.
     *
     * @param  array<string, mixed>  $menu
     * @return array<string, mixed>
     */
    public function panel_menu(array $menu)
    {
        $ids = array_keys($menu);
        $request = array_search('qm-request', $ids);
        $position = $request === false ? count($menu) : $request;

        $item = [
            $this->collector->id() => $this->menu(['title' => $this->name()]),
        ];

        return array_merge(
            array_slice($menu, 0, $position),
            $item,
            array_slice($menu, $position)
        );
    }

    /**
     * Prints the QM panel's content.
     *
     * @return void
     */
    public function output()
    {
        $data = $this->collector->get_data();

        if (! $data['has-dropin']) {
            $this->before_non_tabular_output();

            echo $this->build_notice(implode(' ', [
                'The Object Cache Pro object cache drop-in is not installed.',
                'Use the Dashboard widget or WP CLI to enable the object cache drop-in.',
            ]));

            $this->after_non_tabular_output();

            return;
        }

        if (! $data['valid-dropin']) {
            $this->before_non_tabular_output();

            echo $this->build_notice(implode(' ', [
                'WordPress is using a foreign object cache drop-in and Object Cache Pro is not being used.',
                'Use the Dashboard widget or WP CLI to enable the object cache drop-in.',
            ]));

            $this->after_non_tabular_output();

            return;
        }

        require __DIR__ . '/templates/object-cache.phtml';
    }
}
