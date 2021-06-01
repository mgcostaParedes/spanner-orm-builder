<?php

namespace MgCosta\Spanner\Builder\Grammar;

use MgCosta\Spanner\Builder\Builder;

interface Grammatical
{
    public function compile(Builder $query): string;
    public function compileUpdate(Builder $query, array $values): string;
    public function compileDelete(Builder $query): string;
    public function wrapTable($table);
}
