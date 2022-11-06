<?php

declare(strict_types=1);

namespace RedisCachePro\Loggers;

class CallbackLogger extends Logger
{
    /**
     * The logger's callback function.
     *
     * @var mixed
     */
    protected $callback;

    /**
     * Creates a new callback logger instance.
     *
     * @param  callable  $callback
     * @return void
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

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

        ($this->callback)($level, $message, $context);
    }
}
