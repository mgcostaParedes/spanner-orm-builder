<?php

namespace Tests\unit;

use MgCosta\Spanner\Traits\ForwardCall;
use Codeception\Test\Unit;
use BadMethodCallException;

class ForwardCallTraitTest extends Unit
{
    private $trait;

    public function setUp(): void
    {
        parent::setUp();
        $this->trait = $this->getMockForTrait(ForwardCall::class);
    }

    public function testShouldForwardCallSuccessfullyWhenCallExistentMethod()
    {
        $this->assertEquals(4, $this->trait->forwardCallTo(new DummyObject(), 'dummyMethod', [2, 2]));
    }

    public function testShouldFailForwardCallyWhenCallNoExistentMethod()
    {
        $this->expectException(BadMethodCallException::class);
        $this->assertEquals(4, $this->trait->forwardCallTo(new DummyObject(), 'testMethod2', [2, 2]));
    }
}

class DummyObject
{
    public function dummyMethod($a, $b)
    {
        return $a + $b;
    }
}
