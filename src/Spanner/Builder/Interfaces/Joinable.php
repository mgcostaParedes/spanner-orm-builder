<?php

namespace MgCosta\Spanner\Builder\Interfaces;

use MgCosta\Spanner\Builder\JoinClause;

interface Joinable
{
    public function on($first, $operator = null, $second = null, $boolean = 'and'): JoinClause;
    public function orOn($first, $operator = null, $second = null): JoinClause;
}
