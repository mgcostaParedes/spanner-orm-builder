<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Model\Strategies;

use MgCosta\Spanner\Model\Model;

class IncrementStrategy implements StrategicalGenerator
{
    public function generateKey(Model $model): int
    {
        // find latest result on database
        $lastResult = $model->newQuery()->orderBy($model->getPrimaryKey(), 'desc')->first();
        if (!empty($lastResult)) {
            return $lastResult->{$model->getPrimaryKey()} + 1;
        }
        return 1;
    }
}
