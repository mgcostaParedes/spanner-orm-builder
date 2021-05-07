<?php

namespace Tests\unit\stubs;

use MgCosta\Spanner\Model\Model;

class DummyModel extends Model
{
    protected $keyStrategy = 'increment';
}
