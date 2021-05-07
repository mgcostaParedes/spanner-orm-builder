<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Model\Strategies;

use MgCosta\Spanner\Model\Model;
use Ramsey\Uuid\Uuid;

class Uuid4Strategy implements StrategicalGenerator
{
    public function generateKey(Model $model): string
    {
        return Uuid::uuid4()->toString();
    }
}
