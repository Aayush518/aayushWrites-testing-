<?php

declare(strict_types=1);

class RedisCachePro_DebugBar_Insights extends RedisCachePro_DebugBar_Panel
{
    /**
     * Holds the object cache information object.
     *
     * @var object
     */
    protected $info;

    /**
     * Holds the object cache metrics object.
     *
     * @var object
     */
    protected $metrics;

    /**
     * Create a new insights panel instance.
     *
     * @param  object  $info
     * @param  object  $metrics
     */
    public function __construct($info, $metrics)
    {
        $this->info = $info;
        $this->metrics = $metrics;
    }

    /**
     * The title of the panel.
     *
     * @return string
     */
    public function title()
    {
        return 'Object Cache';
    }

    /**
     * Whether the panel is visible.
     *
     * @return bool
     */
    public function is_visible()
    {
        return true;
    }

    /**
     * Render the panel.
     *
     * @return void
     */
    public function render()
    {
        require __DIR__ . '/templates/insights.phtml';
    }
}
