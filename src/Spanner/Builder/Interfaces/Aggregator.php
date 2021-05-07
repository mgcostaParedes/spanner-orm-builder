<?php

namespace MgCosta\Spanner\Builder\Interfaces;

use MgCosta\Spanner\Builder\Builder;

interface Aggregator
{
    public function aggregate($function, $columns = ['*']);
    public function count($columns = '*'): int;
    public function max($column);
    public function min($column);
    public function sum($column);
    public function avg($column);

    public function having($column, $operator = null, $value = null, $boolean = 'and'): Builder;
    public function orHaving($column, $operator = null, $value = null): Builder;
}
