<?php

namespace Tests\unit;

use Codeception\Test\Unit;
use DateTimeImmutable;
use Google\Cloud\Core\Timestamp;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Result;
use MgCosta\Spanner\Builder\Builder;
use MgCosta\Spanner\Model\Model;
use Tests\unit\stubs\DummyErrorModel;
use Tests\unit\stubs\DummyModel;
use Mockery as m;
use ArrayIterator;
use InvalidArgumentException;

class ModelTest extends Unit
{
    private $model;
    private $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = m::mock(Database::class);
        Model::setConnectionDatabase($this->connection);
        $this->model = new DummyModel();
    }

    public function testShouldGetTheClassnameAsTableNameWhenTheresNoTableNameSpecified()
    {
        $this->assertEquals('dummymodel', $this->model->getTable());
    }

    public function testShouldGetTheIdAsPrimaryKeyWhenTheresNoPrimaryKeySpecified()
    {
        $this->assertEquals('id', $this->model->getPrimaryKey());
    }

    public function testShouldCallyMagicallyTheMethodFromANewBuildWhenAccessItStatically()
    {
        $this->assertInstanceOf(Builder::class, DummyModel::where('id', 1));
    }

    public function testShouldReturnTrueWhenCallingSaveMethodOnObjectProperly()
    {
        $this->model->name = 'test';

        $date = new DateTimeImmutable(date('Y-m-d'));
        $timestamp = new Timestamp($date);

        $data = new ArrayIterator([
            [
                'id' => 1,
                'name' => 'test'
            ]
        ]);
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn($data);

        $this->connection->shouldReceive('execute')->andReturn($mockResult);
        $this->connection->shouldReceive('insertOrUpdate')->andReturn($timestamp)->once();

        $this->assertEquals(true, $this->model->save());
    }

    public function testShouldReturnThrowAnExceptionWhenCallingSaveMethodWithAnInvalidStrategy()
    {
        $this->expectException(InvalidArgumentException::class);

        (new DummyErrorModel)->save();
    }
}
