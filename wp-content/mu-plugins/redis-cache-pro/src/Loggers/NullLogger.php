<?php

declare(strict_types=1);

namespace RedisCachePro\Loggers;

class NullLogger extends Logger
{
    /**
     * Logs with an arbitrary level.
     *
     * @param  mixed  $level
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        //
    }
}
