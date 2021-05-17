<?php

namespace Tests\unit;

use DateTimeImmutable;
use Google\Cloud\Spanner\Result;
use Google\Cloud\Spanner\Timestamp;
use Google\Cloud\Spanner\Transaction;
use Illuminate\Support\Collection;
use MgCosta\Spanner\Builder\Builder;
use MgCosta\Spanner\Builder\ParamCounter;
use MgCosta\Spanner\Builder\Expression;
use Codeception\Test\Unit;
use Google\Cloud\Spanner\Database;
use MgCosta\Spanner\Builder\JoinClause;
use MgCosta\Spanner\Model\Model;
use Mockery as m;
use Tests\unit\stubs\DummyModel;
use ArrayIterator;
use Closure;

class BuilderTest extends Unit
{
    private $builder;
    private $mockedModel;
    private $database;

    public function setUp(): void
    {
        parent::setUp();
        $this->database = m::mock(Database::class);
        $this->builder = new Builder($this->database);
        $this->mockedModel = m::mock(DummyModel::class);
        $this->mockedModel->shouldReceive('getTable')->andReturn('dummymodel');
        $this->builder->setModel($this->mockedModel);
        ParamCounter::flushCounter();
    }

    public function testShouldGetModelWhenCallingGetModelProperly()
    {
        $this->assertInstanceOf(Model::class, $this->builder->getModel());
    }

    public function testShouldAssignFromAndModelWhenCallingSetModelProperly()
    {
        $this->assertEquals('dummymodel', $this->builder->from);
    }


    public function testShouldThrowAnInvalidArgumentExceptionModelWhenCallingAddBindingWithInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->addBinding(['@param1' => 'dummy'], 'errorBinding');
    }

    public function testShouldAssignProperBindingsWhenCallingLimitAndOffsetMethodsProperly()
    {
        $this->builder->limit(1)->offset(2);

        $this->assertEquals(1, $this->builder->limit);
        $this->assertEquals(2, $this->builder->offset);
    }

    public function testShouldAssignProperBindingsWhenCallingSelectSubMethodWithAnotherBuildQuery()
    {
        $builderSub = new Builder(m::mock(Database::class));
        $builderSub->from('persons')->select('name')->where('age', '<=', 30)->take(1);
        $this->builder->selectSub($builderSub, 'total');

        $this->assertInstanceOf(Expression::class, $this->builder->columns[0]);
    }

    public function testShouldAssignProperBindingsWhenCallingSelectSubMethodWithString()
    {
        $this->builder->selectSub('select count(*) from TestB', 'columnB');
        $this->assertInstanceOf(Expression::class, $this->builder->columns[0]);
    }

    public function testShouldThrowAnInvalidArgumentExceptionWhenCallingSelectSubMethodWithWrongType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->selectSub([50, 100], 'columnB');
    }

    public function testShouldAssignProperBindingsWhenCallingSelectWithClosureMethod()
    {
        $this->builder->select(['columnA' => function($query) {
            $query->select('columnB')->from('testB')->whereColumn('testB.id', 'testA.id');
        }]);

        $this->assertInstanceOf(Expression::class, $this->builder->columns[0]);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereMethodWithTwoParams()
    {
        $this->builder->where('name', 'test');
        $expectedWhere = [
            'type' => 'Basic',
            'column' => 'name',
            'operator' => '=',
            'value' => 'test',
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals(['param1' => 'test'], $this->builder->bindings['where']);
        $this->assertEquals($expectedWhere, $this->builder->wheres[0]);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereMethodWithDefinedOperator()
    {
        $this->builder->where('name', 'like', '%test%');
        $expectedWhere = [
            'type' => 'Basic',
            'column' => 'name',
            'operator' => 'like',
            'value' => '%test%',
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals(['param1' => '%test%'], $this->builder->bindings['where']);
        $this->assertEquals($expectedWhere, $this->builder->wheres[0]);
    }

    public function testShouldAssignProperBindingsWhenCallingNestedWhereMethodProperly()
    {
        $this->builder->from('persons')->where(function($query) {
            $query->where('name', 'test')->where('age', 25);
        });
        $expectedNestedWhere = [[
            'type' => 'Basic',
            'column' => 'name',
            'operator' => '=',
            'value' => 'test',
            'boolean' => 'and',
            'parameter' => '@param1'
        ], [
            'type' => 'Basic',
            'column' => 'age',
            'operator' => '=',
            'value' => 25,
            'boolean' => 'and',
            'parameter' => '@param2'
        ]];
        $this->assertInstanceOf(Builder::class, $this->builder->wheres[0]['query']);
        $this->assertEquals('Nested', $this->builder->wheres[0]['type']);
        $this->assertEquals($expectedNestedWhere, $this->builder->wheres[0]['query']->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingOrWhereMethodProperly()
    {
        $this->builder->where('name', 'test')->orWhere('age', 25);
        $expectedWhere = [[
            'type' => 'Basic',
            'column' => 'name',
            'operator' => '=',
            'value' => 'test',
            'boolean' => 'and',
            'parameter' => '@param1'
        ], [
            'type' => 'Basic',
            'column' => 'age',
            'operator' => '=',
            'value' => 25,
            'boolean' => 'or',
            'parameter' => '@param2'
        ]];
        $this->assertEquals('test', $this->builder->bindings['where']['param1']);
        $this->assertEquals(25, $this->builder->bindings['where']['param2']);
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldThrowAnInvalidArgumentExceptionModelWhenCallingWhereWithInvalidOperator()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->where('name', '//', 'test');
    }

    public function testShouldAssignProperBindingsWhenCallingWhereColumnMethodProperly()
    {
        $this->builder->whereColumn('updated_at', '>',  'created_at');
        $expectedWhere[] = [
            'type' => 'Column',
            'first' => 'updated_at',
            'operator' => '>',
            'second' => 'created_at',
            'boolean' => 'and'
        ];
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereColumnMethodWIthInvalidOperator()
    {
        $this->builder->whereColumn('updated_at', 'created_at');
        $expectedWhere[] = [
            'type' => 'Column',
            'first' => 'updated_at',
            'operator' => '=',
            'second' => 'created_at',
            'boolean' => 'and'
        ];
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingAddSelectWithSubQueryMethodProperly()
    {
        $this->builder->addSelect(['number' => function($query) {
            $query->select('name')->from('test')->whereColumn('id', 'test.id')->limit(1);
        }]);
        $this->assertInstanceOf(Expression::class, $this->builder->columns[1]);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereNullMethod()
    {
        $this->builder->whereNull('columnA');
        $expectedWhere = [
            'type' => 'Null',
            'column' => 'columnA',
            'boolean' => 'and'
        ];
        $this->assertEquals($expectedWhere, $this->builder->wheres[0]);
    }

    public function testShouldAssignProperBindingsWhenCallingOrWhereNullMethod()
    {
        $this->builder->whereNull('columnA')->orWhereNull('columnB');
        $expectedWhere = [
            [
                'type' => 'Null',
                'column' => 'columnA',
                'boolean' => 'and'
            ],
            [
                'type' => 'Null',
                'column' => 'columnB',
                'boolean' => 'or'
            ],
        ];
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereNotNullMethod()
    {
        $this->builder->whereNotNull('columnA');
        $expectedWhere = [
            [
                'type' => 'NotNull',
                'column' => 'columnA',
                'boolean' => 'and'
            ]
        ];
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingOrWhereNotNullMethod()
    {
        $this->builder->whereNotNull('columnA')->orWhereNotNull('columnB');
        $expectedWhere = [
            [
                'type' => 'NotNull',
                'column' => 'columnA',
                'boolean' => 'and'
            ],
            [
                'type' => 'NotNull',
                'column' => 'columnB',
                'boolean' => 'or'
            ],
        ];
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereInMethodWithinArrayOfValues()
    {
        $values = [25, 30];
        $this->builder->whereIn('age', $values);
        $expectedWhere[] = [
            'type' => 'In',
            'column' => 'age',
            'values' => $values,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals($values, $this->builder->bindings['where']['param1']);
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereInMethodWithinSubQuery()
    {
        $this->builder->whereIn('age', function($query) {
            $query->from('persons')->where('age', 25);
        });
        $this->assertEquals(['param1' => 25], $this->builder->bindings['where']);
        $this->assertEquals('In', $this->builder->wheres[0]['type']);
        $this->assertEquals('age', $this->builder->wheres[0]['column']);
        $this->assertInstanceOf(Expression::class, $this->builder->wheres[0]['values'][0]);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereNotInMethodProperly()
    {
        $values = [25, 30];
        $this->builder->whereNotIn('age', $values);
        $expectedWhere[] = [
            'type' => 'NotIn',
            'column' => 'age',
            'values' => $values,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals(['param1' => $values], $this->builder->bindings['where']);
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingOrWhereInMethodWithinArrayOfValues()
    {
        $values = [25, 30];
        $this->builder->whereIn('age', $values)->orWhereIn('age2', $values);
        $expectedWhere = [
            [
                'type' => 'In',
                'column' => 'age',
                'values' => $values,
                'boolean' => 'and',
                'parameter' => '@param1'
            ],
            [
                'type' => 'In',
                'column' => 'age2',
                'values' => $values,
                'boolean' => 'or',
                'parameter' => '@param2'
            ]
        ];

        $this->assertEquals($values, $this->builder->bindings['where']['param1']);
        $this->assertEquals($values, $this->builder->bindings['where']['param2']);
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingOrWhereNotInMethodWithinArrayOfValues()
    {
        $values = [25, 30];
        $values2 = [35, 40];
        $this->builder->whereIn('age', $values)->orWhereNotIn('age2', $values2);
        $expectedWhere = [
            [
                'type' => 'In',
                'column' => 'age',
                'values' => $values,
                'boolean' => 'and',
                'parameter' => '@param1'
            ],
            [
                'type' => 'NotIn',
                'column' => 'age2',
                'values' => $values2,
                'boolean' => 'or',
                'parameter' => '@param2'
            ]
        ];

        $this->assertEquals($values, $this->builder->bindings['where']['param1']);
        $this->assertEquals($values2, $this->builder->bindings['where']['param2']);
        $this->assertEquals($expectedWhere, $this->builder->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereExistsMethodWithinProperlySubQuery()
    {
        $this->builder->whereExists(function($query) {
            $query->select(1)->from('TestB')->whereColumn('ColumnA', 'A');
        });

        $this->assertInstanceOf(Builder::class, $this->builder->wheres[0]['query']);
        $this->assertEquals('Exists', $this->builder->wheres[0]['type']);
        $this->assertEquals('and', $this->builder->wheres[0]['boolean']);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereNotExistsMethodWithinProperlySubQuery()
    {
        $this->builder->whereNotExists(function($query) {
            $query->select(1)->from('TestB')->whereColumn('ColumnA', 'A');
        });

        $this->assertInstanceOf(Builder::class, $this->builder->wheres[0]['query']);
        $this->assertEquals('NotExists', $this->builder->wheres[0]['type']);
        $this->assertEquals('and', $this->builder->wheres[0]['boolean']);
    }

    public function testShouldAssignProperBindingsWhenCallingOrWhereExistsMethodWithinProperlySubQuery()
    {
        $this->builder->orWhereExists(function($query) {
            $query->select(1)->from('TestB')->whereColumn('ColumnA', 'A');
        });

        $this->assertInstanceOf(Builder::class, $this->builder->wheres[0]['query']);
        $this->assertEquals('Exists', $this->builder->wheres[0]['type']);
        $this->assertEquals('or', $this->builder->wheres[0]['boolean']);
    }

    public function testShouldAssignProperBindingsWhenCallingOrWhereNotExistsMethodWithinProperlySubQuery()
    {
        $this->builder->orWhereNotExists(function($query) {
            $query->select(1)->from('TestB')->whereColumn('ColumnA', 'A');
        });

        $this->assertInstanceOf(Builder::class, $this->builder->wheres[0]['query']);
        $this->assertEquals('NotExists', $this->builder->wheres[0]['type']);
        $this->assertEquals('or', $this->builder->wheres[0]['boolean']);
    }

    public function testShouldAssignProperBindingsWhenCallingWhereBetweenMethodProperly()
    {
        $this->builder->whereBetween('age', [25, 30]);
        $expectedWhere = [
            'type' => 'between',
            'column' => 'age',
            'values' => [25, 30],
            'boolean' => 'and',
            'not' => false,
            'parameters' => ['@param1', '@param2']
        ];
        $this->assertEquals($expectedWhere, $this->builder->wheres[0]);
        $this->assertEquals(['param1' => 25, 'param2' => 30], $this->builder->bindings['where']);
    }

    public function testShouldAssignProperBindingsWhenCallingJoinMethodProperly()
    {
        $this->builder->join('TestB', 'TestB.Id', '=', 'TestA.Id');
        $expectedWhere = [
            'type' => 'Column',
            'first' => 'TestB.Id',
            'operator' => '=',
            'second' => 'TestA.Id',
            'boolean' => 'and'
        ];
        $this->assertInstanceOf(JoinClause::class, $this->builder->joins[0]);
        $this->assertEquals($expectedWhere, $this->builder->joins[0]->wheres[0]);
        $this->assertEquals('inner', $this->builder->joins[0]->type);
    }

    public function testShouldAssignProperBindingsWhenCallingJoinClosureMethodProperly()
    {
        $this->builder->join('TestB',  function($query) {
           $query->on('TestB.Id', '=', 'TestA.Id')->where('age', 25);
        });
        $expectedWhere = [
            [
                'type' => 'Column',
                'first' => 'TestB.Id',
                'operator' => '=',
                'second' => 'TestA.Id',
                'boolean' => 'and'
            ],
            [
                'type' => 'Basic',
                'column' => 'age',
                'operator' => '=',
                'value' => 25,
                'boolean' => 'and',
                'parameter' => '@param1'
            ]
        ];
        $this->assertInstanceOf(JoinClause::class, $this->builder->joins[0]);
        $this->assertEquals($expectedWhere, $this->builder->joins[0]->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingJoinWhereMethodProperly()
    {
        $this->builder->joinWhere('TestB', 'TestB.Id', '=', 'TestA.Id');
        $expectedWhere = [
            'type' => 'Basic',
            'column' => 'TestB.Id',
            'operator' => '=',
            'value' => 'TestA.Id',
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertInstanceOf(JoinClause::class, $this->builder->joins[0]);
        $this->assertEquals($expectedWhere, $this->builder->joins[0]->wheres[0]);
        $this->assertEquals('inner', $this->builder->joins[0]->type);
    }

    public function testShouldAssignProperBindingsWhenCallingRightJoinMethodProperly()
    {
        $this->builder->rightJoin('TestB', 'TestB.Id', '=', 'TestA.Id');
        $expectedWhere = [
            'type' => 'Column',
            'first' => 'TestB.Id',
            'operator' => '=',
            'second' => 'TestA.Id',
            'boolean' => 'and'
        ];
        $this->assertInstanceOf(JoinClause::class, $this->builder->joins[0]);
        $this->assertEquals($expectedWhere, $this->builder->joins[0]->wheres[0]);
        $this->assertEquals('right', $this->builder->joins[0]->type);
    }

    public function testShouldAssignProperBindingsWhenCallingLeftJoinMethodProperly()
    {
        $this->builder->leftJoin('TestB', 'TestB.Id', '=', 'TestA.Id');
        $expectedWhere = [
            'type' => 'Column',
            'first' => 'TestB.Id',
            'operator' => '=',
            'second' => 'TestA.Id',
            'boolean' => 'and'
        ];
        $this->assertInstanceOf(JoinClause::class, $this->builder->joins[0]);
        $this->assertEquals($expectedWhere, $this->builder->joins[0]->wheres[0]);
        $this->assertEquals('left', $this->builder->joins[0]->type);
    }

    public function testShouldAssignProperBindingsWhenCallingCrossJoinMethodProperly()
    {
        $this->builder->select('TestA.*', 'TestB.*')->crossJoin('TestB');

        $this->assertInstanceOf(JoinClause::class, $this->builder->joins[0]);
        $this->assertEquals('cross', $this->builder->joins[0]->type);
        $this->assertEquals('TestB', $this->builder->joins[0]->table);
    }

    public function testShouldAssignProperBindingsWhenCallingCrossJoinWithinClosureMethodProperly()
    {
        $this->builder->select('TestA.*', 'TestB.*')->crossJoin('TestB', function($join) {
            $join->on('Test.A.Id', '=', 'TestB.Id')->where('TestB.age', '>', 25);
        });

        $this->assertInstanceOf(JoinClause::class, $this->builder->joins[0]);
        $this->assertEquals('cross', $this->builder->joins[0]->type);
        $this->assertEquals('TestB', $this->builder->joins[0]->table);
        $this->assertNotEmpty($this->builder->joins[0]->wheres);
    }

    public function testShouldAssignProperBindingsWhenCallingSimpleOrderByMethod()
    {
        $this->builder->orderBy('ColumnA');
        $expectedOrders[] = [
            'column' => 'ColumnA',
            'direction' => 'asc'
        ];
        $this->assertEquals($expectedOrders, $this->builder->orders);
    }

    public function testShouldAssignProperBindingsWhenCallingSubQueryOrderByMethod()
    {
        $this->builder->orderBy(function($query) {
            $query->from('testB')->select('ColumnB')->whereColumn('testB.id', 'testA.id');
        });

        $this->assertEquals('asc', $this->builder->orders[0]['direction']);
        $this->assertInstanceOf(Expression::class, $this->builder->orders[0]['column']);
    }

    public function testShouldThrowInvalidArgumentExceptionBindingsWhenOrderByWithUnknownDirectionMethod()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->orderBy('ColumnA', 'error');
    }

    public function testShouldAssignProperBindingsWhenCallingSimpleOrderByDescMethod()
    {
        $this->builder->orderByDesc('ColumnA');
        $expectedOrders[] = [
            'column' => 'ColumnA',
            'direction' => 'desc'
        ];
        $this->assertEquals($expectedOrders, $this->builder->orders);
    }

    public function testShouldAssignProperBindingsWhenCallingGroupByMethod()
    {
        $this->builder->groupBy('groupA', 'groupB');
        $expectedGroups = ['groupA', 'groupB'];
        $this->assertEquals($expectedGroups, $this->builder->groups);
    }

    public function testShouldAssignProperBindingsWhenCallingHavingMethod()
    {
        $this->builder->groupBy('columnA')->having('columnA', '>', 30);
        $expectedHaving = [
            'type' => 'Basic',
            'column' => 'columnA',
            'operator' => '>',
            'value' => 30,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals($expectedHaving, $this->builder->havings[0]);
        $this->assertEquals(['param1' => 30], $this->builder->bindings['having']);
    }

    public function testShouldThrowAnInvalidArgumentExceptionWhenCallingHavingMethodWithInvalidOperator()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->having('columnA', '%', 30);
    }

    public function testShouldAssignProperBindingsWhenCallingOrHavingMethod()
    {
        $this->builder->groupBy('columnA')->orHaving('columnA', '>', 30);
        $expectedHaving = [
            'type' => 'Basic',
            'column' => 'columnA',
            'operator' => '>',
            'value' => 30,
            'boolean' => 'or',
            'parameter' => '@param1'
        ];
        $this->assertEquals($expectedHaving, $this->builder->havings[0]);
        $this->assertEquals(['param1' => 30], $this->builder->bindings['having']);
    }

    public function testShouldReturnNullWhenCallingAggregateMethodWithoutResults()
    {
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn(new ArrayIterator([]));
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->mockedModel->shouldReceive('newInstance')->andReturnSelf();
        $this->mockedModel->shouldReceive('newCollection')->andReturn(new Collection([]));
        $this->database->shouldReceive('execute')->andReturn($mockResult);
        $result = $this->builder->where('age', '>', 35)->count();
        $this->assertEquals(null, $result);
    }

    public function testShouldReturnAnIntegerWhenCallingCountMethod()
    {
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn(new ArrayIterator([['aggregate' => 39]]));
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->mockedModel->shouldReceive('newInstance')->andReturnSelf();
        $this->mockedModel->shouldReceive('newFromBuilder')->andReturnSelf();
        $this->mockedModel->shouldReceive('newCollection')->andReturn(new Collection($mockResult));
        $this->database->shouldReceive('execute')->andReturn($mockResult);
        $result = $this->builder->where('age', '>', 35)->count();
        $this->assertEquals(39, $result);
    }

    public function testShouldReturnAnIntegerWhenCallingMaxMethod()
    {
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn(new ArrayIterator([['aggregate' => 200]]));
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->mockedModel->shouldReceive('newInstance')->andReturnSelf();
        $this->mockedModel->shouldReceive('newFromBuilder')->andReturnSelf();
        $this->mockedModel->shouldReceive('newCollection')->andReturn(new Collection($mockResult));
        $this->database->shouldReceive('execute')->andReturn($mockResult);
        $result = $this->builder->where('age', '>', 35)->max('age');
        $this->assertEquals(200, $result);
    }

    public function testShouldReturnAnIntegerWhenCallingMinMethod()
    {
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn(new ArrayIterator([['aggregate' => 20]]));
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->mockedModel->shouldReceive('newInstance')->andReturnSelf();
        $this->mockedModel->shouldReceive('newFromBuilder')->andReturnSelf();
        $this->mockedModel->shouldReceive('newCollection')->andReturn(new Collection($mockResult));
        $this->database->shouldReceive('execute')->andReturn($mockResult);
        $result = $this->builder->where('age', '>', 35)->min('age');
        $this->assertEquals(20, $result);
    }

    public function testShouldReturnAnIntegerWhenCallingSumMethod()
    {
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn(new ArrayIterator([['aggregate' => 333]]));
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->mockedModel->shouldReceive('newInstance')->andReturnSelf();
        $this->mockedModel->shouldReceive('newFromBuilder')->andReturnSelf();
        $this->mockedModel->shouldReceive('newCollection')->andReturn(new Collection($mockResult));
        $this->database->shouldReceive('execute')->andReturn($mockResult);
        $result = $this->builder->where('age', '>', 35)->sum('age');
        $this->assertEquals(333, $result);
    }

    public function testShouldReturnAFloatWhenCallingAvgMethod()
    {
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn(new ArrayIterator([['aggregate' => 333.67]]));
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->mockedModel->shouldReceive('newInstance')->andReturnSelf();
        $this->mockedModel->shouldReceive('newFromBuilder')->andReturnSelf();
        $this->mockedModel->shouldReceive('newCollection')->andReturn(new Collection($mockResult));
        $this->database->shouldReceive('execute')->andReturn($mockResult);
        $result = $this->builder->where('age', '>', 35)->avg('age');
        $this->assertEquals(333.67, $result);
    }

    public function testShouldReturnAValueWhenCallingValueMethod()
    {
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn(new ArrayIterator([['columnA' => 'A', 'columnB' => 'B']]));
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->mockedModel->shouldReceive('newInstance')->andReturnSelf();
        $this->mockedModel->shouldReceive('newFromBuilder')->andReturnSelf();
        $this->mockedModel->shouldReceive('newCollection')->andReturn(new Collection($mockResult));
        $this->database->shouldReceive('execute')->andReturn($mockResult);
        $result = $this->builder->where('age', '>', 35)->value('columnA');
        $this->assertEquals('A', $result);
    }
    
    public function testShouldReturnACollectionWhenCallingFindMethodProperly()
    {
        $mockResult = m::mock(Result::class);
        $mockResult->shouldReceive('getIterator')->andReturn($this->getRandomData(55));
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->mockedModel->shouldReceive('newInstance')->andReturnSelf();
        $this->mockedModel->shouldReceive('newFromBuilder')->andReturnSelf();
        $this->mockedModel->shouldReceive('newCollection')->andReturn(new Collection($mockResult));
        $this->database->shouldReceive('execute')->andReturn($mockResult);
        $result = $this->builder->find('55');
        $this->assertIsArray($result);
        $this->assertEquals(55, $result['DummyID']);
    }

    public function testShouldReturnATimestampWhenCallingDeleteMethodProperly()
    {
        $date = new DateTimeImmutable(date('Y-m-d'));
        $timestamp = new Timestamp($date);
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');
        $this->database->shouldReceive('runTransaction')->once()->with(
           m::on(function(Closure $transaction) use($timestamp) {
               $mockTransaction = m::mock(Transaction::class);
               $mockTransaction->shouldReceive('executeUpdate')->andReturn(1)->once();
               $mockTransaction->shouldReceive('commit')->andReturn($timestamp)->once();
               $this->assertSame($timestamp, $transaction($mockTransaction));
               return true;
           })
        )->andReturn($timestamp);
        $result = $this->builder->delete(7);
        $this->assertEquals($timestamp, $result);
    }

    public function testShouldReturnATimestampWhenCallingUpdatedMethodProperly()
    {
        $date = new DateTimeImmutable(date('Y-m-d'));
        $timestamp = new Timestamp($date);
        $this->mockedModel->shouldReceive('getPrimaryKey')->andReturn('DummyID');

        $this->database->shouldReceive('runTransaction')->once()->with(
            m::on(function(Closure $transaction) use($timestamp) {
                $mockTransaction = m::mock(Transaction::class);
                $mockTransaction->shouldReceive('executeUpdate')->andReturn(1)->once();
                $mockTransaction->shouldReceive('commit')->andReturn($timestamp)->once();
                $this->assertSame($timestamp, $transaction($mockTransaction));
                return true;
            })
        )->andReturn($timestamp);

        $result = $this->builder->where('id', 1)->update(['age' => 30]);
        $this->assertEquals($timestamp, $result);
    }

    public function testShouldReturnAnExpressionWhenCallingRawMethod()
    {
        $result = $this->builder->raw('select count(*) from testA');
        $this->assertInstanceOf(Expression::class, $result);
    }

    public function testShouldAssignDistinctPropertyWhenCallingDistinctMethod()
    {
        $this->builder->select(['columnA', 'columnB'])->distinct();
        $this->assertEquals(true, $this->builder->distinct);
    }

    public function testShouldAssignDistinctPropertyWhenCallingDistinctMethodWithColumns()
    {
        $this->builder->select(['columnA', 'columnB'])->distinct('ColumnA');
        $this->assertEquals(['ColumnA'], $this->builder->distinct);
    }

    private function getRandomData(int $id = null): ArrayIterator
    {
        $rand = rand(1, 20);
        $array = [];
        for($i = 0; $i <= $rand; $i++) {
            $newArray = [
                'DummyID' => $id ?? rand(1, 100),
                'ColumnA' => 'DataA',
                'ColumnB' => 'DataB'
            ];
            $array[] = $newArray;
        }
        return new ArrayIterator($array);
    }
}
