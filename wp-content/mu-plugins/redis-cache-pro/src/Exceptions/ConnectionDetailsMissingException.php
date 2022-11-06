<?php

declare(strict_types=1);

namespace RedisCachePro\Exceptions;

class ConnectionDetailsMissingException extends ConfigurationException
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
            $message = implode(' ', [
                'Your `WP_REDIS_CONFIG` is missing connection information about your Redis server(s).',
                'Try setting a host and port, socket path, or cluster nodes.',
                'For more information see: https://objectcache.pro/docs/configuration/',
            ]);
        }

        parent::__construct($message, $code, $previous);
    }
}
