<?php

namespace Tests\unit\stubs;

use MgCosta\Spanner\Traits\ModelAttributes;

class StubWithAttributesTrait
{
    use ModelAttributes;

    public $id;
    public $firstName;
    public $lastName;
    public $age;
}
