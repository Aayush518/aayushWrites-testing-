<?php

declare(strict_types=1);

namespace RedisCachePro\Exceptions;

use RedisCachePro\Connectors\PhpRedisConnector;

class PhpRedisOutdatedException extends ObjectCacheException
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
            $message = sprintf(
                'Object Cache Pro requires PhpRedis %s or newer. This environment (%s) was loaded with PhpRedis %s.',
                PhpRedisConnector::RequiredVersion,
                PHP_SAPI,
                phpversion('redis')
            );
        }

        parent::__construct($message, $code, $previous);
    }
}
