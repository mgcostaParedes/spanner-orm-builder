<?php

namespace Tests\unit\stubs;

use MgCosta\Spanner\Model\Model;

class DummyErrorModel extends Model
{
    protected $keyStrategy = 'error';
}
