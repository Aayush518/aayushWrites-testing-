<?php

declare(strict_types=1);

namespace RedisCachePro\Exceptions;

class InvalidCacheKeyTypeException extends ObjectCacheException
{
    /**
     * Creates an "invalid cache key value/type" exception for given key.
     *
     * @param  mixed  $key
     * @return InvalidCacheKeyTypeException
     */
    public static function forKey($key)
    {
        $type = strtolower(gettype($key));

        if ($type === 'string' && trim($key) === '') {
            $type = 'empty string';
        }

        /** @var string $backtrace */
        $backtrace = function_exists('wp_debug_backtrace_summary')
            ? wp_debug_backtrace_summary(__CLASS__, 4)
            : 'backtrace unavailable';

        if (strpos($backtrace, ', wp_cache_')) {
            $backtrace = strstr($backtrace, ', wp_cache_', true);
        }

        return new static( // @phpstan-ignore-line
            "Cache key must be integer or non-empty string, {$type} given ({$backtrace})"
        );
    }
}
