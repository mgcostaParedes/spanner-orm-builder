<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Traits;

use BadMethodCallException;
use Error;

trait ForwardCall
{
    public function forwardCallTo($object, $method, $parameters)
    {
        try {
            return $object->{$method}(...$parameters);
        } catch (Error | BadMethodCallException $e) {
            throw new BadMethodCallException(
                sprintf(
                    'Call to undefined method %s::%s()',
                    static::class,
                    $method
                )
            );
        }
    }
}
