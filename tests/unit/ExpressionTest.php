<?php

namespace Tests\unit;

use MgCosta\Spanner\Builder\Expression;
use Codeception\Test\Unit;

class ExpressionTest extends Unit
{
    public function testShouldSetExpressionSuccessfullyWhenObjectIsInstancedProperly()
    {
        $expression = 'count(*) as total';
        $this->assertEquals($expression, new Expression($expression));
    }
}
