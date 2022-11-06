<?php

declare(strict_types=1);

namespace RedisCachePro\Loggers;

class ErrorLogLogger extends Logger
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
        if ($this->levels && ! \in_array($level, $this->levels)) {
            return;
        }

        \error_log("objectcache.{$level}: {$message}");
    }
}
