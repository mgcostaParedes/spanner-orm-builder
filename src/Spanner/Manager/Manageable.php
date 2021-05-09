<?php

namespace MgCosta\Spanner\Manager;

interface Manageable
{
    public function boot();
    public static function getConnection();
}
