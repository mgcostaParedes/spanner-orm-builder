<?php

namespace Tests\unit;

use MgCosta\Spanner\Manager\Manager;
use Codeception\Test\Unit;
use Google\Cloud\Spanner\Database;
use Mockery as m;
use Tests\unit\stubs\DummyModel;

class ManagerTest extends Unit
{
    private $connection;
    private $manager;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = m::mock(Database::class);
        $this->manager = new Manager($this->connection);
    }

    public function testShouldBootConnectionProperlyToBaseModelWhenCallingBootWithValidConnectionToSpanner()
    {
        $this->manager->boot();
        $model = new DummyModel();
        $this->assertInstanceOf(Database::class, $model->getConnection());
    }
}
