<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Builder;

use Google\Cloud\Spanner\Result;
use Google\Cloud\Spanner\Timestamp;
use Google\Cloud\Spanner\Transaction;
use Illuminate\Support\Collection;
use MgCosta\Spanner\Builder\Grammar\SpannerGrammar;
use MgCosta\Spanner\Builder\Grammar\Grammatical;
use MgCosta\Spanner\Builder\Interfaces\Aggregator;
use MgCosta\Spanner\Builder\Interfaces\Fetchable;
use MgCosta\Spanner\Builder\Interfaces\Operator;
use MgCosta\Spanner\Model\Model;
use Google\Cloud\Spanner\Database;
use Closure;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class Builder implements Operator, Aggregator, Fetchable
{
    /**
     * The database cloud spanner instance
     *
     * @var Database
     */
    public $connection;

    /**
     * The transpiler to cloud spanner sql language
     *
     * @var Grammatical|SpannerGrammar
     */
    public $grammar;

    /**
     * @var Model
     */
    protected $model;

    /**
     * The columns that should be returned
     *
     * @var array
     */
    public $columns;

    /**
     * Table which query is targeting
     *
     * @var string
     */
    public $from;

    /**
     * Table joins for the query
     *
     * @var array
     */
    public $joins;

    /**
     * The where constraints which query is targeting
     *
     * @var array
     */
    public $wheres = [];

    /**
     * The group constraints which query is targeting
     *
     * @var array
     */
    public $groups;

    /**
     * The having constraints which query is targeting
     *
     * @var array
     */
    public $havings;

    /**
     * The orders constraints which query is targeting
     *
     * @var array
     */
    public $orders;

    /**
     * The maximum number of results to return.
     *
     * @var int
     */
    public $limit;

    /**
     * The number of results to skip.
     *
     * @var int
     */
    public $offset;

    /**
     * The query union statements.
     *
     * @var array
     */
    public $unions;

    public $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'groupBy' => [],
        'having' => [],
        'order' => [],
    ];

    public $distinct = false;

    public $aggregate;

    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'not like',
        '&', '|', '^', '<<', '>>',
    ];

    public function __construct(Database $connection, Grammatical $grammar = null)
    {
        $this->connection = $connection;
        $this->grammar = $grammar ?? new SpannerGrammar();
    }

    public function getBindings(): array
    {
        return Arr::collapse($this->bindings);
    }

    public function getRawBindings()
    {
        return $this->bindings;
    }

    public function getConnection(): Database
    {
        return $this->connection;
    }

    public function getGrammar(): Grammatical
    {
        return $this->grammar;
    }

    public function newQuery(): self
    {
        return new static($this->connection, $this->grammar);
    }

    public function setModel(Model $model): self
    {
        $this->model = $model;

        return $this->from($model->getTable());
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function from(string $table, string $as = null): self
    {
        $this->from = $as ? "{$table} as {$as}" : $table;
        return $this;
    }

    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false): self
    {
        $join = $this->newJoinClause($this, $type, $table);

        // if is a closure the dev is trying to build  a join with complex "on" clause
        if ($first instanceof Closure) {
            $first($join);
            $this->joins[] = $join;
        } else {
            $method = $where ? 'where' : 'on';
            $this->joins[] = $join->$method($first, $operator, $second);
        }
        $this->addBinding($join->getBindings(), 'join');
        return $this;
    }

    public function joinWhere($table, $first, $operator, $second, $type = 'inner'): self
    {
        return $this->join($table, $first, $operator, $second, $type, true);
    }

    public function rightJoin($table, $first, $operator = null, $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function leftJoin($table, $first, $operator = null, $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function crossJoin($table, $first = null, $operator = null, $second = null): self
    {
        if ($first) {
            return $this->join($table, $first, $operator, $second, 'cross');
        }

        $this->joins[] = $this->newJoinClause($this, 'cross', $table);

        return $this;
    }

    public function select($columns = ['*']): self
    {
        $this->columns = [];
        $this->bindings['select'] = [];
        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->isQueryable($column)) {
                $this->selectSub($column, $as);
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    public function selectSub($query, $as): self
    {
        [$query, $bindings] = $this->createSub($query);

        return $this->selectRaw('(' . $query . ') as ' . $as, $bindings);
    }

    public function selectRaw($expression, array $bindings = []): self
    {
        $this->addSelect(new Expression($expression));

        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    public function addSelect($column): self
    {
        $columns = is_array($column) ? $column : func_get_args();

        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->isQueryable($column)) {
                if (is_null($this->columns)) {
                    $this->select($this->from . '.*');
                }

                $this->selectSub($column, $as);
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        if ($column instanceof Closure && is_null($operator)) {
            return $this->whereNested($column, $boolean);
        }
        $type = 'Basic';
        $parameter = $this->getParameterKey();
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean', 'parameter');
        $this->addBinding([$parameter => $value]);
        return $this;
    }

    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and'): self
    {
        if ($this->invalidOperator($operator)) {
            [$second, $operator] = [$operator, '='];
        }

        $type = 'Column';

        $this->wheres[] = compact('type', 'first', 'operator', 'second', 'boolean');

        return $this;
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false): self
    {
        $type = $not ? 'NotIn' : 'In';

        $isQueryable = $this->isQueryable($values);
        if ($isQueryable) {
            [$query, $bindings] = $this->createSub($values);

            $values = [new Expression($query)];

            $this->addBinding($bindings);
        }

        $parameter = $this->getParameterKey();
        $this->wheres[] = ($isQueryable) ?
            compact('type', 'column', 'values', 'boolean') :
            compact('type', 'column', 'values', 'boolean', 'parameter');

        if (!$isQueryable) {
            $this->addBinding([$parameter => $this->cleanBindings($values)]);
        }

        return $this;
    }

    public function whereNotIn($column, $values, $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereIn($column, $values): self
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function orWhereNotIn($column, $values): self
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->where($column, $operator, $value, 'or');
    }

    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false): self
    {
        $type = 'between';

        $paramMin = $this->getParameterKey();
        $paramMax = $this->getParameterKey();
        $parameters = [$paramMin, $paramMax];
        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not', 'parameters');
        $this->addBinding([$paramMin => reset($values)]);
        $this->addBinding([$paramMax => end($values)]);

        return $this;
    }

    public function whereNested(Closure $callback, $boolean = 'and'): self
    {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }

    public function addNestedWhereQuery($query, $boolean = 'and'): self
    {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');
            $this->addBinding($query->getRawBindings()['where']);
        }

        return $this;
    }

    public function whereNull($columns, $boolean = 'and', $not = false): self
    {
        $type = $not ? 'NotNull' : 'Null';

        foreach (Arr::wrap($columns) as $column) {
            $this->wheres[] = compact('type', 'column', 'boolean');
        }

        return $this;
    }

    public function orWhereNull($column): self
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull($columns, $boolean = 'and'): self
    {
        return $this->whereNull($columns, $boolean, true);
    }

    public function orWhereNotNull($column): self
    {
        return $this->whereNotNull($column, 'or');
    }

    public function forNestedWhere(): self
    {
        return $this->newQuery()->from($this->from);
    }

    public function whereExists(Closure $callback, $boolean = 'and', $not = false): self
    {
        $query = $this->forSubQuery();

        // create the sub query
        call_user_func($callback, $query);

        return $this->addWhereExistsQuery($query, $boolean, $not);
    }

    public function orWhereExists(Closure $callback, $not = false): self
    {
        return $this->whereExists($callback, 'or', $not);
    }

    public function whereNotExists(Closure $callback, $boolean = 'and'): self
    {
        return $this->whereExists($callback, $boolean, true);
    }

    public function orWhereNotExists(Closure $callback): self
    {
        return $this->orWhereExists($callback, true);
    }

    protected function addWhereExistsQuery(Builder $query, $boolean = 'and', $not = false): self
    {
        $type = $not ? 'NotExists' : 'Exists';

        $this->wheres[] = compact('type', 'query', 'boolean');
        $this->addBinding($query->getBindings());

        return $this;
    }

    public function orderBy($column, $direction = 'asc'): self
    {
        if ($this->isQueryable($column)) {
            [$query, $bindings] = $this->createSub($column);

            $column = new Expression('(' . $query . ')');

            $this->addBinding($bindings, $this->unions ? 'unionOrder' : 'order');
        }

        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc"');
        }

        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    public function orderByDesc($column): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function groupBy(...$groups): self
    {
        foreach ($groups as $group) {
            $this->groups = array_merge((array) $this->groups, Arr::wrap($group));
        }

        return $this;
    }

    public function having($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        $type = 'Basic';

        [$value, $operator] = $this->prepareValueAndOperator($value, $operator, func_num_args() === 2);

        $parameter = $this->getParameterKey();
        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean', 'parameter');

        if (!$value instanceof Expression) {
            $this->addBinding([$parameter => $this->flattenValue($value)], 'having');
        }

        return $this;
    }

    public function orHaving($column, $operator = null, $value = null): self
    {
        [$value, $operator] = $this->prepareValueAndOperator($value, $operator, func_num_args() === 2);

        return $this->having($column, $operator, $value, 'or');
    }

    public function aggregate($function, $columns = ['*'])
    {
        $results = $this->cloneWithout($this->unions ? [] : ['columns'])
            ->cloneWithoutBindings($this->unions ? [] : ['select'])
            ->setAggregate($function, $columns)
            ->get($columns);

        if (! $results->isEmpty()) {
            return array_change_key_case((array) $results[0])['aggregate'];
        }
        return null;
    }

    public function cloneWithout(array $properties)
    {
        return $this->tap($this->clone(), function ($clone) use ($properties) {
            foreach ($properties as $property) {
                $clone->{$property} = null;
            }
        });
    }
    public function cloneWithoutBindings(array $except)
    {
        return $this->tap($this->clone(), function ($clone) use ($except) {
            foreach ($except as $type) {
                $clone->bindings[$type] = [];
            }
        });
    }

    protected function setAggregate($function, $columns): self
    {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->groups)) {
            $this->orders = null;

            $this->bindings['order'] = [];
        }

        return $this;
    }

    public function clone(): self
    {
        return clone $this;
    }

    public function offset($value): self
    {
        $property = $this->unions ? 'unionOffset' : 'offset';
        $this->$property = max(0, $value);

        return $this;
    }

    public function take($value): self
    {
        return $this->limit($value);
    }

    public function limit($value): self
    {
        $property = $this->unions ? 'unionLimit' : 'limit';

        if ($value >= 0) {
            $this->$property = $value;
        }

        return $this;
    }

    public function count($columns = '*'): int
    {
        return (int) $this->aggregate(__FUNCTION__, Arr::wrap($columns));
    }

    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    public function sum($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]) ?: 0;
    }

    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    public function distinct(): self
    {
        $columns = func_get_args();

        if (count($columns) > 0) {
            $this->distinct = is_array($columns[0]) || is_bool($columns[0]) ? $columns[0] : $columns;
        } else {
            $this->distinct = true;
        }

        return $this;
    }

    /**
     * @param $column
     * @return false|mixed|null
     */
    public function value($column)
    {
        $result = (array) $this->first([$column]);
        return count($result) > 0 ? reset($result) : null;
    }

    /**
     * @param string[] $columns
     * @return mixed
     */
    public function first(array $columns = ['*'])
    {
        return $this->take(1)->get($columns)->first();
    }

    public function get(array $columns = ['*']): Collection
    {
        $result = collect($this->onceWithColumns(Arr::wrap($columns), function () {
            return $this->runSelect();
        }));

        if ($this->model) {
            $models = $this->hydrate($result->toArray())->all();
            return $this->getModel()->newCollection($models);
        }

        return $result;
    }

    public function find($id, $columns = ['*']): array
    {
        return $this->where($this->model->getPrimaryKey() ?? 'id', '=', $id)->first($columns);
    }

    public function update(array $values): Timestamp
    {
        $valuesWithParams = [];
        foreach ($values as $key => $value) {
            $parameter = $this->getValueKey();
            $valuesWithParams[$key] = [
                'parameter' => $parameter,
                'value' => $value
            ];
            $this->addBinding([$parameter => $value]);
        }
        $sql = $this->grammar->compileUpdate($this, $valuesWithParams);

        $query = $this;
        return $this->connection->runTransaction(function (Transaction $t) use ($sql, $query) {
            $t->executeUpdate($sql, [
                'parameters' => $query->getBindings()
            ]);
            return $t->commit();
        });
    }

    public function delete($id = null): Timestamp
    {
        if (!is_null($id)) {
            $this->where($this->from . '.' . $this->model->getPrimaryKey(), '=', $id);
        }

        $query = $this;
        return $this->connection->runTransaction(function (Transaction $t) use ($query) {
            $t->executeUpdate($query->grammar->compileDelete($query), [
                'parameters' => $query->getBindings()
            ]);
            return $t->commit();
        });
    }

    public function raw($value): Expression
    {
        return new Expression($value);
    }

    public function addBinding($parameter, $type = 'where'): self
    {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        foreach ($parameter as $key => $value) {
            $key = str_replace("@", "", $key);
            $this->bindings[$type][$key] = $value;
        }

        return $this;
    }

    public function toSql(): string
    {
        return $this->grammar->compile($this);
    }

    protected function hydrate(array $items): Collection
    {
        $instance = $this->model->newInstance();

        return $instance->newCollection(array_map(function ($item) use ($instance) {
            return $instance->newFromBuilder($item);
        }, $items));
    }

    protected function invalidOperatorOrValue($operator, $value): bool
    {
        if (is_null($value) && is_null($operator)) {
            return false;
        }
        return is_null($value) || ! in_array($operator, $this->operators);
    }

    protected function prepareValueAndOperator($value, $operator, $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        }

        if ($this->invalidOperatorOrValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator or value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Creates a sub query and parse it
     *
     * @param $query
     * @return mixed
     */
    protected function createSub($query): array
    {
        if ($query instanceof Closure) {
            $callback = $query;

            $callback($query = $this->forSubQuery());
        }

        return $this->parseSub($query);
    }

    protected function newJoinClause(self $query, $type, $table): JoinClause
    {
        return new JoinClause($query, $type, $table);
    }

    /**
     * Parse the sub query into SQL and bindings.
     * @param $query
     * @return array
     */
    protected function parseSub($query): array
    {
        if ($query instanceof self) {
            return [$query->toSql(), $query->getBindings()];
        }
        if (is_string($query)) {
            return [$query, []];
        }

        throw new InvalidArgumentException(
            'A sub query must be a query builder instance, a Closure, or a string.'
        );
    }

    protected function isQueryable($value): bool
    {
        return $value instanceof self || $value instanceof Closure;
    }

    protected function forSubQuery(): self
    {
        return $this->newQuery();
    }

    protected function invalidOperator($operator): bool
    {
        return ! in_array(strtolower($operator), $this->operators, true) &&
            ! in_array(strtolower($operator), $this->operators, true);
    }

    protected function runSelect(): Result
    {
        ParamCounter::flushCounter();
        return $this->connection->execute($this->toSql(), ['parameters' => $this->getBindings()]);
    }

    protected function cleanBindings(array $bindings): array
    {
        return array_values(array_filter($bindings, function ($binding) {
            return ! $binding instanceof Expression;
        }));
    }

    protected function onceWithColumns($columns, $callback)
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $result = $callback();

        $this->columns = $original;

        return $result;
    }

    private function flattenValue($value)
    {
        return is_array($value) ? head(Arr::flatten($value)) : $value;
    }

    private function tap($value, $callback)
    {
        $callback($value);
        return $value;
    }

    private function getParameterKey(): string
    {
        return '@param' . ParamCounter::getKeyParam();
    }

    private function getValueKey(): string
    {
        return '@value' . ParamCounter::getKeyValue();
    }
}
