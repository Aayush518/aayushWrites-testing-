<?php

declare(strict_types=1);

namespace RedisCachePro\Loggers;

class BacktraceLogger extends Logger
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
        \error_log(
            \sprintf(
                'objectcache.%s: %s [%s]',
                $level,
                $message,
                $context['backtrace_summary']
                    ?? 'Backtrace not available, enable the `save_commands` configuration option'
            )
        );
    }
}
