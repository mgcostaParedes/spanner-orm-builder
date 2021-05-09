<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Manager;

use MgCosta\Spanner\Model\Model;
use Google\Cloud\Spanner\Database;
use MgCosta\Spanner\Model\Strategies\StrategyFactory;

class Manager implements Manageable
{
    protected static $connection;

    public function __construct(Database $connection)
    {
        static::$connection = $connection;
    }

    public function boot()
    {
        Model::setConnectionDatabase(static::$connection);
        Model::setStrategyFactory(new StrategyFactory());
    }

    public static function getConnection(): Database
    {
        return static::$connection;
    }
}
