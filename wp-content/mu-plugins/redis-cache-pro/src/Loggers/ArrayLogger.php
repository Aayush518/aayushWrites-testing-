<?php

declare(strict_types=1);

namespace RedisCachePro\Loggers;

class ArrayLogger extends ErrorLogLogger
{
    /**
     * Holds all logged messages.
     *
     * @var array<int, array<string, mixed>>
     */
    protected $messages = [];

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
        $this->messages[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        parent::log($level, $message, $context);
    }

    /**
     * Return all logged messages as array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
