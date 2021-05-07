<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Manager;

use MgCosta\Spanner\Model\Model;
use Google\Cloud\Spanner\Database;
use MgCosta\Spanner\Model\Strategies\StrategyFactory;

class Manager implements Manageable
{
    protected $connection;

    public function __construct(Database $connection)
    {
        $this->connection = $connection;
    }

    public function boot()
    {
        Model::setConnectionDatabase($this->connection);
        Model::setStrategyFactory(new StrategyFactory());
    }
}
