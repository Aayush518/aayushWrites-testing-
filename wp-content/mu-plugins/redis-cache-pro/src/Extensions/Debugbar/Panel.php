<?php

declare(strict_types=1);

abstract class RedisCachePro_DebugBar_Panel
{
    /**
     * Whether the panel is visible.
     *
     * @return bool
     */
    abstract public function is_visible();

    /**
     * Render the panel.
     *
     * @return void
     */
    abstract public function render();

    /**
     * Pre-render the panel.
     *
     * @return void
     */
    public function prerender()
    {
        //
    }

    /**
     * The title of the panel.
     *
     * @return string
     */
    abstract public function title();
}
