<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Model\Strategies;

use MgCosta\Spanner\Model\Model;

interface StrategicalGenerator
{
    public function generateKey(Model $model);
}
