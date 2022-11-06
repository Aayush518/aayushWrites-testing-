<?php

declare(strict_types=1);

namespace RedisCachePro\Exceptions;

use Exception;
use Throwable;

class ObjectCacheException extends Exception
{
    /**
     * Creates a new exception from the given exception.
     *
     * @param  \Throwable  $exception
     * @return self
     */
    public static function from(Throwable $exception)
    {
        if ($exception instanceof self) {
            return $exception;
        }

        return new static( // @phpstan-ignore-line
            $exception->getMessage(),
            $exception->getCode(),
            $exception
        );
    }
}
