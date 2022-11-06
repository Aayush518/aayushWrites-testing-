<?php

declare(strict_types=1);

namespace RedisCachePro\Exceptions;

class RelayMissingException extends ObjectCacheException
{
    /**
     * Creates a new exception.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  \Throwable|null  $previous
     * @return void
     */
    public function __construct($message = '', $code = 0, $previous = null)
    {
        if (empty($message)) {
            $sapi = PHP_SAPI;

            $message = implode(' ', [
                "Object Cache Pro was configured to use Relay, but the Relay extension for PHP is not loaded in this environment ({$sapi}).",
                'If it was installed, be sure to load the extension in your php.ini and to restart your PHP and web server processes.',
            ]);
        }

        parent::__construct($message, $code, $previous);
    }
}
