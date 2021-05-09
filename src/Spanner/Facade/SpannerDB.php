<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Facade;

use Google\Cloud\Spanner\Database;
use MgCosta\Spanner\Builder\Builder;
use MgCosta\Spanner\Manager\Manager;

class SpannerDB
{
    /**
     * The spanner connection instance.
     *
     * @var Database;
     */
    protected $connection;

    public function __construct(Database $connection = null)
    {
        $this->connection = $connection ?? Manager::getConnection();
    }

    public function table(string $name): Builder
    {
        return (new Builder($this->connection))->from($name);
    }
}
