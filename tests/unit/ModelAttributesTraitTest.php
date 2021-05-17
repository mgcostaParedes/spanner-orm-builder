<?php

namespace Tests\unit;

use MgCosta\Spanner\Traits\ModelAttributes;
use Codeception\Test\Unit;
use Tests\unit\stubs\StubWithAttributesTrait;

class ModelAttributesTraitTest extends Unit
{
    private $trait;

    public function setUp(): void
    {
        parent::setUp();
        $this->trait = new StubWithAttributesTrait();
    }

    public function testShouldGetAllPropertiesAsAnArrayWhenCallingGetAttributesMethod()
    {
        $this->trait->id = 1;
        $this->trait->firstName = 'test';
        $this->trait->lastName = 'test';
        $this->trait->age = 30;

        $expectedArray = [
            'id' => 1,
            'firstName' => 'test',
            'lastName' => 'test',
            'age' => 30
        ];
        $this->assertEquals($expectedArray, $this->trait->getAttributes());
    }

    public function testShouldAssignClassPropertiesWhenCallingSetRawAttributesWithinAValidArray()
    {
        $this->trait->setRawAttributes([
            'id' => 1,
            'firstName' => 'Michael',
            'lastName' => 'Brandon',
            'age' => 30
        ]);
        $this->assertEquals(1, $this->trait->id);
        $this->assertEquals('Michael', $this->trait->firstName);
        $this->assertEquals('Brandon', $this->trait->lastName);
        $this->assertEquals(30, $this->trait->age);
    }
}
