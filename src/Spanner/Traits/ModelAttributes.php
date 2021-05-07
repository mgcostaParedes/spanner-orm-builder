<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Traits;

use ReflectionObject;
use ReflectionProperty;

trait ModelAttributes
{
    public function getAttributes(): array
    {
        $reflect = new ReflectionObject($this);
        $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);

        $attributes = [];
        array_walk($props, function ($item) use (&$attributes) {
            if ($item->class === get_class($this)) {
                $attributes[$item->name] = $this->{$item->name};
            }
        });

        return $attributes;
    }
}
