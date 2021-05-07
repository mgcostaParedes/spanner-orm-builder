<?php

namespace Tests\unit;

use MgCosta\Spanner\Builder\Builder;
use MgCosta\Spanner\Builder\Expression;
use MgCosta\Spanner\Builder\Grammar\SpannerGrammar;
use Codeception\Test\Unit;
use MgCosta\Spanner\Builder\JoinClause;
use Mockery as m;

class GrammarTest extends Unit
{
    private $grammar;
    private $builder;

    public function setUp(): void
    {
        parent::setUp();
        $this->builder = m::mock(Builder::class);
        $this->grammar = new SpannerGrammar();
    }

    public function testShouldCompileFromSuccessfullyWhenPassBuilderWithFromProperly()
    {
        $this->builder->from = 'test';
        $this->assertEquals('select * from test', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileFromSuccessfullyWhenPassBuilderWithExpressionFromProperly()
    {
        $mockExpression = m::mock(Expression::class);
        $mockExpression->shouldReceive('getValue')->andReturn('testZ');
        $this->builder->from = $mockExpression;
        $this->assertEquals('select * from testZ', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileBasicSelectSuccessfullyWhenPassBuilderWithSelectProperly()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'age',
            'operator' => '>',
            'value' => 25,
            'boolean' => 'and'
        ];
        $this->assertEquals('select * from test where age > 25', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileNestedSelectSuccessfullyWhenPassBuilderWithNestedSelectProperly()
    {
        $nestedQuery = m::mock(Builder::class);
        $nestedQuery->wheres[] = [
            'type' => 'Basic',
            'column' => 'age',
            'operator' => '=',
            'value' => 25,
            'boolean' => 'and'
        ];
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'Nested',
            'query' => $nestedQuery,
            'boolean' => 'and'
        ];
        $this->assertEquals('select * from test where (age = 25)', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereNullSuccessfullyWhenPassBuilderWithWhereNullProperly()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'Null',
            'column' => 'Address',
            'boolean' => 'and'
        ];
        $this->assertEquals('select * from test where Address is null', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereNotNullSuccessfullyWhenPassBuilderWithWhereNotNullProperly()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'NotNull',
            'column' => 'Address',
            'boolean' => 'and'
        ];
        $this->assertEquals('select * from test where Address is not null', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereInSuccessfullyWhenPassBuilderWhereInProperly()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'In',
            'column' => 'age',
            'values' => [25, 30],
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals('select * from test where age in UNNEST(@param1)', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereInSuccessfullyWhenPassBuilderWhereInWithoutValuesProperly()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'In',
            'column' => 'age',
            'values' => [],
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals('select * from test where 0 = 1', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereNotInSuccessfullyWhenPassBuilderWhereInWithoutValuesProperly()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'NotIn',
            'column' => 'age',
            'values' => [25, 30],
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals('select * from test where age not in UNNEST(@param1)', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereNotInSuccessfullyWhenPassBuilderWhereNotInWithoutValuesProperly()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'NotIn',
            'column' => 'age',
            'values' => [],
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals('select * from test where 1 = 1', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereExistsSuccessfullyWhenPassBuilderWhereExistsWithValuesProperly()
    {
        $this->builder->from = 'test';
        $nestedQuery = m::mock(Builder::class);
        $nestedQuery->from = 'testB';
        $nestedQuery->wheres[] = [
            'type' => 'Column',
            'first' => 'test.ColumnA',
            'operator' => '=',
            'second' => 'testB.ColumnA',
            'boolean' => 'and'
        ];
        $this->builder->wheres[] = [
            'type' => 'Exists',
            'query' => $nestedQuery,
            'boolean' => 'and',
        ];
        $this->assertEquals('select * from test where exists (select * from testB where test.ColumnA = testB.ColumnA)', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereExistsSuccessfullyWhenPassBuilderWhereNotExistsWithValuesProperly()
    {
        $this->builder->from = 'test';
        $nestedQuery = m::mock(Builder::class);
        $nestedQuery->from = 'testB';
        $nestedQuery->wheres[] = [
            'type' => 'Column',
            'first' => 'test.ColumnA',
            'operator' => '=',
            'second' => 'testB.ColumnA',
            'boolean' => 'and'
        ];
        $this->builder->wheres[] = [
            'type' => 'NotExists',
            'query' => $nestedQuery,
            'boolean' => 'and',
        ];
        $this->assertEquals('select * from test where not exists (select * from testB where test.ColumnA = testB.ColumnA)', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileWhereBetweenSuccessfullyWhenPassBuilderWhereBetweenWithValuesProperly()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'between',
            'column' => 'columnA',
            'values' => [25, 30],
            'boolean' => 'and',
            'not' => false,
            'parameters' => ['@param1', '@param2']
        ];
        $this->assertEquals('select * from test where columnA between @param1 and @param2', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileOffsetAndLimitSuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->offset = 2;
        $this->builder->limit = 1;
        $this->assertEquals('select * from test limit 1 offset 2', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileOrdersSuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->orders[] = [
            'column' => 'ColumnA',
            'direction' => 'desc'
        ];
        $this->assertEquals('select * from test order by ColumnA desc', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileMultipleOrdersSuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->orders = [
            [
                'column' => 'ColumnA',
                'direction' => 'desc'
            ],
            [
                'column' => 'ColumnB',
                'direction' => 'asc'
            ],
        ];
        $this->assertEquals('select * from test order by ColumnA desc, ColumnB asc', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileEmptyOrdersSuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->orders = [];
        $this->assertEquals('select * from test', $this->grammar->compile($this->builder));
    }

    public function testShouldCompileGroupsBySuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->groups = ['groupA', 'groupB'];
        $this->assertEquals(
            'select * from test group by groupA, groupB',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldCompileHavingSuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->columns = ['ColumnA'];
        $this->builder->groups = ['ColumnA'];
        $this->builder->havings[] = [
            'type' => 'Basic',
            'column' => 'ColumnA',
            'operator' => '>',
            'value' => 30,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals(
            'select ColumnA from test group by ColumnA having ColumnA > @param1',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldCompileAggregationsSuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'age',
            'operator' => '>',
            'value' => 30,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->builder->aggregate = [
            'function' => 'avg',
            'columns' => ['age']
        ];
        $this->assertEquals(
            'select avg(age) as aggregate from test where age > @param1',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldCompileAggregationsWithDistinctSuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'age',
            'operator' => '>',
            'value' => 30,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->builder->aggregate = [
            'function' => 'avg',
            'columns' => ['age']
        ];
        $this->builder->distinct = true;
        $this->assertEquals(
            'select avg(distinct age) as aggregate from test where age > @param1',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldCompileAggregationsWithDistinctArraySuccessfullyWhenPassBuilderWithTheseProperties()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'age',
            'operator' => '>',
            'value' => 30,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->builder->aggregate = [
            'function' => 'avg',
            'columns' => ['age']
        ];
        $this->builder->distinct = ['age'];
        $this->assertEquals(
            'select avg(distinct age) as aggregate from test where age > @param1',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldCompileInnerJoinSuccessfullyWhenPassBuilderWithJoinProperties()
    {
        $joinClause = m::mock(JoinClause::class);
        $joinClause->type = 'inner';
        $joinClause->table = 'TestB';
        $joinClause->wheres[] = [
            'type' => 'Column',
            'first' => 'TestA.id',
            'operator' => '=',
            'second' => 'TestB.id',
            'boolean' => 'and'
        ];
        $this->builder->from = 'TestA';
        $this->builder->joins[0] = $joinClause;

        $this->assertEquals(
            'select * from TestA inner join TestB on TestA.id = TestB.id',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldCompileDeleteSuccessfullyWhenPassBuilderWithBasicDeleteProperties()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'id',
            'operator' => '=',
            'value' => 1,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals(
            'delete from test where id = @param1',
            $this->grammar->compileDelete($this->builder)
        );
    }

    public function testShouldCompileDeleteSuccessfullyWhenPassBuilderWithJoinDeleteProperties()
    {
        $joinClause = m::mock(JoinClause::class);
        $joinClause->type = 'inner';
        $joinClause->table = 'TestB';
        $joinClause->wheres[] = [
            'type' => 'Column',
            'first' => 'TestA.id',
            'operator' => '=',
            'second' => 'TestB.id',
            'boolean' => 'and'
        ];
        $this->builder->from = 'TestA';
        $this->builder->joins[0] = $joinClause;
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'TestA',
            'operator' => '=',
            'value' => 1,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals(
            'delete TestA from TestA inner join TestB on TestA.id = TestB.id where TestA = @param1',
            $this->grammar->compileDelete($this->builder)
        );
    }

    public function testShouldCompileUpdateSuccessfullyWhenPassBuilderWithUpdateProperties()
    {
        $this->builder->from = 'test';
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'id',
            'operator' => '=',
            'value' => 1,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];

        $values = [
            'age' => ['parameter' => '@value1', 'value' => 30]
        ];

        $this->assertEquals(
            'update test set age = @value1 where id = @param1',
            $this->grammar->compileUpdate($this->builder, $values)
        );
    }

    public function testShouldCompileUpdateSuccessfullyWhenPassBuilderWithJoinDeleteProperties()
    {
        $joinClause = m::mock(JoinClause::class);
        $joinClause->type = 'inner';
        $joinClause->table = 'TestB';
        $joinClause->wheres[] = [
            'type' => 'Column',
            'first' => 'TestA.id',
            'operator' => '=',
            'second' => 'TestB.id',
            'boolean' => 'and'
        ];
        $this->builder->from = 'TestA';
        $this->builder->joins[0] = $joinClause;
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'id',
            'operator' => '=',
            'value' => 1,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];

        $values = [
            'age' => ['parameter' => '@value1', 'value' => 30]
        ];

        $this->assertEquals(
            'update TestA inner join TestB on TestA.id = TestB.id set age = @value1 where id = @param1',
            $this->grammar->compileUpdate($this->builder, $values)
        );
    }

    public function testShouldReturnAnExpressionValueWhenGettingParameterWithExpression()
    {
        $this->builder->from = 'TestA';
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => 'age',
            'operator' => '=',
            'value' => new Expression(30),
            'boolean' => 'and',
            'parameter' => '@param1'
        ];

        $this->assertEquals(
            'select * from TestA where age = 30',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldReturnAnExpressionValueWhenGettingMethodWhichWrapsAValue()
    {
        $this->builder->from = 'TestA';
        $this->builder->wheres[] = [
            'type' => 'Basic',
            'column' => new Expression('RandomValue44'),
            'operator' => '=',
            'value' => 30,
            'boolean' => 'and',
            'parameter' => '@param1'
        ];
        $this->assertEquals(
            'select * from TestA where RandomValue44 = @param1',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldReturnAnAliasedValueWhenGettingMethodWhichWrapsAnAliasValue()
    {
        $this->builder->from = 'TestA';
        $this->builder->columns = ['age as PersonAge'];
        $this->assertEquals(
            'select age as PersonAge from TestA',
            $this->grammar->compile($this->builder)
        );
    }

    public function testShouldReturnAnAliasedTableValueWhenGettingMethodWhichWrapsAnAliasToTheTable()
    {
        $this->builder->from = 'TestA as S';
        $this->assertEquals(
            'select * from TestA as S',
            $this->grammar->compile($this->builder)
        );
    }
}
