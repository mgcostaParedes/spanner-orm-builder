<?php

namespace Tests\unit;

use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Result;
use MgCosta\Spanner\Model\Model;
use MgCosta\Spanner\Model\Strategies\IncrementStrategy;
use Codeception\Test\Unit;
use Mockery as m;
use ArrayIterator;

class IncrementStrategyTest extends Unit
{
    private $strategy;
    private $model;
    private $connection;

    public function setUp(): void
    {
        $this->model = $this->getMockForAbstractClass(Model::class);
        $this->connection = m::mock(Database::class);
        Model::setConnectionDatabase($this->connection);
        $this->strategy = new IncrementStrategy();
    }

    public function testShouldGetAnIncrementedValueWhenCallingGenerateKeyAndFetchAnValidResultFromDatabase()
    {
        $data = new ArrayIterator([
            [
                'id' => 1,
                'name' => 'test'
            ]
        ]);
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn($data);


        $this->connection->shouldReceive('execute')->andReturn($mockResult);
        $key = $this->strategy->generateKey($this->model);
        $this->assertEquals(2, $key);
    }

    public function testShouldGetTheStartIncrementValueAs1WhenCallingGenerateKeyAndFetchAnEmptyResultFromDatabase()
    {
        $data = new ArrayIterator([
            []
        ]);
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn($data);


        $this->connection->shouldReceive('execute')->andReturn($mockResult);
        $key = $this->strategy->generateKey($this->model);
        $this->assertEquals(1, $key);
    }
}
