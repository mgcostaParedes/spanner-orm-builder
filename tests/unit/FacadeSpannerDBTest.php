<?php

namespace Tests\unit;

use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Result;
use MgCosta\Spanner\Builder\Builder;
use MgCosta\Spanner\Facade\SpannerDB;
use Codeception\Test\Unit;
use MgCosta\Spanner\Manager\Manager;
use ArrayIterator;
use Mockery as m;

class FacadeSpannerDBTest extends Unit
{
    private $facade;
    private $connection;

    public function setUp(): void
    {
        parent::setUp();
        $database = m::mock(Database::class);
        new Manager($database);
        $this->connection = $database;
        $this->facade = new SpannerDB();
    }

    public function testShouldReturnAQueryBuilderInstanceWhenCallingWithinMethodTable()
    {
        $this->assertInstanceOf(Builder::class, $this->facade->table('test'));
    }

    public function testShouldReturnACollectionOfArrayWhenCallingMethodToFetchResultFromFacade()
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
        $result = $this->facade->table('test')->where('id', 1)->first();
        $this->assertIsArray($result);
    }
}
