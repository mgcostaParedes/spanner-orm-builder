<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Builder;

class ParamCounter
{
    protected static $counter = 0;

    public static function getKey(): int
    {
        static::$counter++;
        return static::$counter;
    }

    public static function flushCounter(): void
    {
        static::$counter = 0;
    }
}
