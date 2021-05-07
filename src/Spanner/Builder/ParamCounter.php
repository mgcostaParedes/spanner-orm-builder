<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Builder;

class ParamCounter
{
    protected static $counterParams = 0;
    protected static $counterValues = 0;

    public static function getKeyParam(): int
    {
        static::$counterParams++;
        return static::$counterParams;
    }

    public static function getKeyValue(): int
    {
        static::$counterValues++;
        return static::$counterValues;
    }

    public static function flushCounter(): void
    {
        static::$counterParams = 0;
        static::$counterValues = 0;
    }
}
