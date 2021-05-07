<?php

namespace Tests\unit;

use Google\Cloud\Spanner\Database;
use MgCosta\Spanner\Builder\Builder;
use Codeception\Test\Unit;
use MgCosta\Spanner\Builder\Grammar\Grammatical;
use MgCosta\Spanner\Builder\JoinClause;
use Mockery as m;
use Tests\unit\stubs\DummyModel;

class JoinClauseTest extends Unit
{
    private $builder;
    private $database;

    public function setUp(): void
    {
        $this->builder = m::mock(Builder::class);
        $mockGrammar = m::mock(Grammatical::class);
        $this->database = m::mock(Database::class);
        $this->builder->shouldReceive('getGrammar')->andReturn($mockGrammar)->once();
        $this->builder->shouldReceive('getConnection')->andReturn($this->database)->once();
    }

    public function testShouldAssignProperBindingsWhenCallingJoinClassWithParentQuery()
    {
        $join = new JoinClause($this->builder, 'inner', 'testA');
        $join->on('TestA.id', '=', 'TestB.id');

        $expectedWhere[] = [
            'type' => 'Column',
            'first' => 'TestA.id',
            'operator' => '=',
            'second' => 'TestB.id',
            'boolean' => 'and'
        ];

        $this->assertEquals($expectedWhere, $join->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingJoinClassWithClosureOnFirstAndOrOn()
    {
        $builder = new Builder($this->database);
        $mockModel = m::mock(DummyModel::class);
        $mockModel->shouldReceive('getTable')->andReturn('dummymodel');
        $builder->setModel($mockModel);
        $builder->from = 'TestA';
        $join = new JoinClause($builder, 'inner', 'testA');
        $join->from = 'TestB';
        $join->on(function($query) {
            $query->on('TestB.Id', '=', 'TestA.Id')->orOn('TestB.Age', '=', 'TestA.Age');
        });

        $this->assertInstanceOf(JoinClause::class, $join->wheres[0]['query']);
        $this->assertEquals('Nested', $join->wheres[0]['type']);
    }

    public function testShouldAssignProperBindingsWhenCallingJoinClassWithOrOn()
    {
        $builder = new Builder($this->database);
        $mockModel = m::mock(DummyModel::class);
        $mockModel->shouldReceive('getTable')->andReturn('dummymodel');
        $builder->setModel($mockModel);
        $builder->from = 'TestA';
        $join = new JoinClause($builder, 'inner', 'testA');
        $join->from = 'TestB';
        $join->on(function($query) {
            $query->on('TestB.Id', '=', 'TestA.Id')->whereExists(function($subQuery) {
                $subQuery->select(1)->from('TestB')->whereColumn('ColumnA', 'A');
            });
        });
        $expectedWhere = [
            'type' => 'Column',
            'first' => 'TestB.Id',
            'operator' => '=',
            'second' => 'TestA.Id',
            'boolean' => 'and'
        ];
        $this->assertEquals('Nested', $join->wheres[0]['type']);
        $this->assertEquals($expectedWhere, $join->wheres[0]['query']->wheres[0]);
        $this->assertEquals('Exists', $join->wheres[0]['query']->wheres[1]['type']);
        $this->assertInstanceOf(Builder::class, $join->wheres[0]['query']->wheres[1]['query']);
    }
}