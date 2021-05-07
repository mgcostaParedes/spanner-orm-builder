<?php

namespace Tests\integration;

use Codeception\Test\Unit;
use MgCosta\Spanner\Model\Strategies\Uuid4Strategy;
use Tests\unit\stubs\DummyModel;

class Uuid4Test extends Unit
{
    private $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new Uuid4Strategy();
    }

    public function testShouldGetAnValidUuid4WhenCallingGenerateKeyMethod()
    {
        $model = new DummyModel();
        $key = $this->service->generateKey($model);
        $this->assertIsString($key);
        $this->assertEquals(36, strlen($key));
    }
}
