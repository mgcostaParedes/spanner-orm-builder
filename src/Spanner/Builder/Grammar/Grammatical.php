<?php

namespace MgCosta\Spanner\Builder\Grammar;

use MgCosta\Spanner\Builder\Builder;

interface Grammatical
{
    public function compile(Builder $query): string;
}
