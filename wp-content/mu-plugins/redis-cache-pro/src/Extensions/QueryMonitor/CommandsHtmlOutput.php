<?php

declare(strict_types=1);

namespace RedisCachePro\Extensions\QueryMonitor;

use QM_Output_Html;

class CommandsHtmlOutput extends QM_Output_Html
{
    /**
     * Creates a new instance.
     *
     * @param  \QM_Collector  $collector
     */
    public function __construct($collector)
    {
        parent::__construct($collector);

        add_filter('qm/output/panel_menus', function ($menu) {
            $menu['qm-cache']['children'][] = $this->menu(['title' => $this->name()]);

            return $menu;
        });
    }

    /**
     * Returns the name of the QM outputter.
     *
     * @return string
     */
    public function name()
    {
        return 'Commands';
    }

    /**
     * Prints the QM panel's content.
     *
     * @return void
     */
    public function output()
    {
        $data = $this->collector->get_data();

        if (! isset($data['commands'])) {
            $this->before_non_tabular_output();

            echo $this->build_notice(
                'The current object cache drop-in does not support listing commands.'
            );

            $this->after_non_tabular_output();

            return;
        }

        if (empty($data['commands'])) {
            $this->before_non_tabular_output();

            echo $this->build_notice(
                (string) file_get_contents(__DIR__ . '/templates/no-log.phtml')
            );

            $this->after_non_tabular_output();

            return;
        }

        require __DIR__ . '/templates/commands.phtml';
    }
}
