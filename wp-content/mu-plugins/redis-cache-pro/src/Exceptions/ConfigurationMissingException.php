<?php

declare(strict_types=1);

namespace RedisCachePro\Exceptions;

class ConfigurationMissingException extends ObjectCacheException
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
                'The `WP_REDIS_CONFIG` constant has not been defined.',
                'If it was defined, try moving it closer to the beginning of the `wp-config.php` file.',
                'For more information see: https://objectcache.pro/docs/configuration/',
            ]);
        }

        parent::__construct($message, $code, $previous);
    }
}
