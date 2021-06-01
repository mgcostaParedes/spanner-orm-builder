<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Builder\Grammar;

use MgCosta\Spanner\Builder\Builder;
use MgCosta\Spanner\Builder\Expression;
use MgCosta\Spanner\Builder\JoinClause;

class SpannerGrammar implements Grammatical
{
    protected $tablePrefix = '';

    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
    ];

    public function compile(Builder $query): string
    {
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $sql = trim($this->concatenate($this->compileComponents($query)));

        $query->columns = $original;

        return $sql;
    }

    public function compileUpdate(Builder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = $this->compileUpdateColumns($query, $values);

        $where = $this->compileWheres($query);

        return trim(
            isset($query->joins)
                ? $this->compileUpdateWithJoins($query, $table, $columns, $where)
                : $this->compileUpdateWithoutJoins($query, $table, $columns, $where)
        );
    }

    public function compileDelete(Builder $query): string
    {
        $table = $this->wrapTable($query->from);

        $where = $this->compileWheres($query);

        return trim(
            isset($query->joins)
                ? $this->compileDeleteWithJoins($query, $table, $where)
                : $this->compileDeleteWithoutJoins($query, $table, $where)
        );
    }

    public function wrapTable($table)
    {
        if (!$this->isExpression($table)) {
            return $this->wrap($this->tablePrefix . $table, true);
        }

        return $table->getValue($table);
    }

    protected function compileComponents(Builder $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (isset($query->$component)) {
                $method = 'compile' . ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    protected function compileFrom(Builder $query, $table): string
    {
        return 'from ' . $this->wrapTable($table);
    }

    protected function compileColumns(Builder $query, $columns): string
    {
        if (! is_null($query->aggregate)) {
            return '';
        }

        $select = ($query->distinct) ? 'select distinct ' : 'select ';

        return $select . $this->columnize($columns);
    }

    protected function compileWheres(Builder $query): string
    {
        if (count($sql = $this->compileWheresToArray($query)) > 0) {
            return $this->concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    protected function compileJoins(Builder $query, $joins): string
    {
        return collect($joins)->map(function ($join) use ($query) {
            $table = $this->wrapTable($join->table);

            $nestedJoins = is_null($join->joins) ? '' : ' ' . $this->compileJoins($query, $join->joins);

            $tableAndNestedJoins = is_null($join->joins) ? $table : '(' . $table . $nestedJoins . ')';

            return trim("{$join->type} join {$tableAndNestedJoins} {$this->compileWheres($join)}");
        })->implode(' ');
    }

    protected function compileLimit(Builder $query, $limit): string
    {
        return 'limit ' . (int) $limit;
    }

    protected function compileOffset(Builder $query, $offset): string
    {
        return 'offset ' . (int) $offset;
    }

    protected function compileOrders(Builder $query, $orders): string
    {
        if (!empty($orders)) {
            return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    protected function compileOrdersToArray(Builder $query, $orders): array
    {
        return array_map(function ($order) {
            return $order['sql'] ?? $this->wrap($order['column']) . ' ' . $order['direction'];
        }, $orders);
    }

    protected function compileAggregate(Builder $query, $aggregate): string
    {
        $column = $this->columnize($aggregate['columns']);

        if (is_array($query->distinct)) {
            $column = 'distinct ' . $this->columnize($query->distinct);
        } elseif ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    protected function compileGroups(Builder $query, $groups): string
    {
        return 'group by ' . $this->columnize($groups);
    }

    protected function compileHavings(Builder $query, $havings): string
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having ' . $this->removeLeadingBoolean($sql);
    }

    protected function compileHaving(array $having): string
    {
        return $this->compileBasicHaving($having);
    }

    protected function compileBasicHaving($having): string
    {
        $column = $this->wrap($having['column']);
        $parameter = $this->parameter($having);

        return $having['boolean'] . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
    }

    protected function compileUpdateColumns(Builder $query, array $values): string
    {
        return collect($values)->map(function ($value, $key) {
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        })->implode(', ');
    }

    protected function compileUpdateWithoutJoins(Builder $query, $table, $columns, $where): string
    {
        return "update {$table} set {$columns} {$where}";
    }

    protected function compileUpdateWithJoins(Builder $query, $table, $columns, $where): string
    {
        $joins = $this->compileJoins($query, $query->joins);

        return "update {$table} {$joins} set {$columns} {$where}";
    }

    protected function compileDeleteWithoutJoins(Builder $query, $table, $where): string
    {
        return "delete from {$table} {$where}";
    }

    protected function compileDeleteWithJoins(Builder $query, $table, $where): string
    {
        $alias = last(explode(' as ', $table));

        $joins = $this->compileJoins($query, $query->joins);

        return "delete {$alias} from {$table} {$joins} {$where}";
    }

    protected function whereNested(Builder $query, $where)
    {
        $offset = $query instanceof JoinClause ? 3 : 6;

        return '(' . substr($this->compileWheres($where['query']), $offset) . ')';
    }

    protected function compileWheresToArray($query): array
    {
        return collect($query->wheres)->map(function ($where) use ($query) {
            return $where['boolean'] . ' ' . $this->{"where{$where['type']}"}($query, $where);
        })->all();
    }

    protected function concatenateWhereClauses($query, $sql): string
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'where';

        return $conjunction . ' ' . $this->removeLeadingBoolean(implode(' ', $sql));
    }

    protected function whereBasic(Builder $query, array $where): string
    {
        $value = $this->parameter($where);

        $operator = str_replace('?', '??', $where['operator']);

        return $this->wrap($where['column']) . ' ' . $operator . ' ' . $value;
    }

    protected function whereNull(Builder $query, $where): string
    {
        return $this->wrap($where['column']) . ' is null';
    }

    protected function whereNotNull(Builder $query, $where): string
    {
        return $this->wrap($where['column']) . ' is not null';
    }

    protected function whereIn(Builder $query, $where): string
    {
        if (! empty($where['values'])) {
            $where['value'] = $where['values'];
            unset($where['values']);
            return $this->wrap($where['column']) . ' in UNNEST(' . $this->parameter($where) . ')';
        }
        return '0 = 1';
    }

    protected function whereNotIn(Builder $query, $where): string
    {
        if (! empty($where['values'])) {
            $where['value'] = $where['values'];
            unset($where['values']);
            return $this->wrap($where['column']) . ' not in UNNEST(' . $this->parameter($where) . ')';
        }

        return '1 = 1';
    }

    protected function whereColumn(Builder $query, $where): string
    {
        return $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
    }

    protected function whereBetween(Builder $query, $where): string
    {
        $between = $where['not'] ? 'not between' : 'between';

        $where['value'] = reset($where['values']);
        $where['parameter'] = reset($where['parameters']);
        $min = $this->parameter($where);

        $where['value'] = end($where['values']);
        $where['parameter'] = end($where['parameters']);
        $max = $this->parameter($where);

        return $this->wrap($where['column']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    protected function whereExists(Builder $query, $where): string
    {
        return 'exists (' . $this->compile($where['query']) . ')';
    }

    protected function whereNotExists(Builder $query, $where): string
    {
        return 'not exists (' . $this->compile($where['query']) . ')';
    }

    protected function concatenate($segments): string
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string)$value !== '';
        }));
    }

    protected function wrap($value, $prefixAlias = false)
    {
        if ($this->isExpression($value)) {
            return $value->getValue($value);
        }

        if (stripos($value, ' as ') !== false) {
            return $this->wrapAliasedValue($value, $prefixAlias);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    protected function wrapAliasedValue($value, $prefixAlias = false): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        if ($prefixAlias) {
            $segments[1] = $this->tablePrefix . $segments[1];
        }

        return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
    }

    protected function wrapValue($value): string
    {
        if ($value !== '*') {
            return str_replace('"', '""', $value);
        }

        return $value;
    }

    protected function wrapSegments($segments): string
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return $key == 0 && count($segments) > 1
                ? $this->wrapTable($segment)
                : $this->wrapValue($segment);
        })->implode('.');
    }

    private function isExpression($value): bool
    {
        return $value instanceof Expression;
    }

    private function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    private function removeLeadingBoolean($value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    private function parameter($where)
    {
        if ($this->isExpression($where['value'])) {
            return $where['value']->getValue();
        }

        if (isset($where['parameter'])) {
            return $where['parameter'];
        }

        return is_array($where['value']) ? implode(",", $where['value']) : $where['value'];
    }
}
