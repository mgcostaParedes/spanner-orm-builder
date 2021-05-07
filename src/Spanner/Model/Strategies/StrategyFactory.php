<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Model\Strategies;

use MgCosta\Spanner\Model\Model;

class StrategyFactory
{
    public function generateKey(string $strategy, Model $model)
    {
        return ($this->getStrategical($strategy))->generateKey($model);
    }

    private function getStrategical(string $strategy): StrategicalGenerator
    {
        $classname = 'MgCosta\\Spanner\\Model\\Strategies\\' . ucfirst($strategy) . 'Strategy';
        return new $classname();
    }
}
