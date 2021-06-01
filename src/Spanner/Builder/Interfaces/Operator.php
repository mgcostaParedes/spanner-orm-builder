<?php

namespace MgCosta\Spanner\Builder\Interfaces;

use Closure;
use MgCosta\Spanner\Builder\Builder;

interface Operator
{
    public function from($table, string $as = null): Builder;
    public function fromSub($query, $as): Builder;
    public function fromRaw($expression, $bindings = []): Builder;

    public function select($columns = ['*']): Builder;
    public function selectSub($query, $as): Builder;
    public function selectRaw($expression, array $bindings = []): Builder;
    public function addSelect($column): Builder;

    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false): Builder;

    public function where($column, $operator = null, $value = null, $boolean = 'and'): Builder;
    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and'): Builder;
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false): Builder;
    public function whereNested(Closure $callback, $boolean = 'and'): Builder;
    public function addNestedWhereQuery($query, $boolean = 'and'): Builder;
    public function orWhere($column, $operator = null, $value = null): Builder;
    public function whereNull($columns, $boolean = 'and', $not = false): Builder;
    public function orWhereNull($column): Builder;
    public function whereNotNull($columns, $boolean = 'and'): Builder;
    public function orWhereNotNull($column): Builder;
    public function whereIn($column, $values, $boolean = 'and', $not = false): Builder;
    public function whereNotIn($column, $values, $boolean = 'and'): Builder;
    public function orWhereIn($column, $values): Builder;
    public function orWhereNotIn($column, $values): Builder;
    public function whereExists(Closure $callback, $boolean = 'and', $not = false): Builder;
    public function whereNotExists(Closure $callback, $boolean = 'and'): Builder;
    public function orWhereExists(Closure $callback, $not = false): Builder;
    public function orWhereNotExists(Closure $callback): Builder;

    public function offset($value): Builder;
    public function limit($value): Builder;

    public function joinWhere($table, $first, $operator, $second, $type = 'inner'): Builder;
    public function rightJoin($table, $first, $operator = null, $second = null): Builder;
    public function leftJoin($table, $first, $operator = null, $second = null): Builder;
    public function crossJoin($table, $first = null, $operator = null, $second = null): Builder;

    public function orderBy($column, $direction = 'asc'): Builder;
    public function orderByDesc($column): Builder;

    public function groupBy(array ...$groups): Builder;
}
