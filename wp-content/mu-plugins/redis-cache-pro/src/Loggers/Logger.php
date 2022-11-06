<?php

declare(strict_types=1);

namespace RedisCachePro\Loggers;

abstract class Logger implements LoggerInterface
{
    /**
     * System is unusable.
     *
     * @var string
     */
    const EMERGENCY = 'emergency';

    /**
     * Action must be taken immediately.
     *
     * @var string
     */
    const ALERT = 'alert';

    /**
     * Critical conditions.
     *
     * @var string
     */
    const CRITICAL = 'critical';

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @var string
     */
    const ERROR = 'error';

    /**
     * Exceptional occurrences that are not errors.
     *
     * @var string
     */
    const WARNING = 'warning';

    /**
     * Normal but significant events.
     *
     * @var string
     */
    const NOTICE = 'notice';

    /**
     * Interesting events.
     *
     * @var string
     */
    const INFO = 'info';

    /**
     * Detailed debug information.
     *
     * @var string
     */
    const DEBUG = 'debug';

    /**
     * Logged levels.
     *
     * @var array<mixed>
     */
    protected $levels;

    /**
     * System is unusable.
     *
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param  mixed  $level
     * @param  string  $message
     * @param  array<mixed>  $context
     * @return void
     */
    abstract public function log($level, $message, array $context = []);

    /**
     * Set the logged levels.
     *
     * @param  array<mixed> $levels
     * @return void
     */
    public function setLevels(array $levels)
    {
        $this->levels = $levels;
    }
}
