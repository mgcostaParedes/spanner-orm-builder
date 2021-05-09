<?php

namespace Tests\unit;

use Google\Cloud\Spanner\Database;
use MgCosta\Spanner\Builder\Builder;
use MgCosta\Spanner\Facade\SpannerDB;
use Codeception\Test\Unit;
use MgCosta\Spanner\Manager\Manager;
use Mockery as m;

class FacadeSpannerDBTest extends Unit
{
    private $facade;

    public function setUp(): void
    {
        parent::setUp();
        new Manager(m::mock(Database::class));
        $this->facade = new SpannerDB();
    }

    public function testShouldReturnAQueryBuilderInstanceWhenCallingWithinMethodTable()
    {
        $this->assertInstanceOf(Builder::class, $this->facade->table('test'));
    }
}