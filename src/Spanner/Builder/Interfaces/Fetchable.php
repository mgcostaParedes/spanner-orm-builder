<?php

namespace MgCosta\Spanner\Builder\Interfaces;

use Google\Cloud\Spanner\Timestamp;
use Illuminate\Support\Collection;
use MgCosta\Spanner\Builder\Builder;

interface Fetchable
{
    public function take($value): Builder;
    public function find($id, $columns = ['*']): array;
    public function value($column);
    public function first(array $columns = ['*']);
    public function get(array $columns = ['*']): Collection;
    public function update(array $values);
    public function delete($id = null): Timestamp;
}
