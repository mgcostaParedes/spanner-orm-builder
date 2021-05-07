<?php

declare(strict_types=1);

namespace MgCosta\Spanner\Builder;

use Closure;
use MgCosta\Spanner\Builder\Interfaces\Joinable;

class JoinClause extends Builder implements Joinable
{
    public $type;

    public $table;

    protected $parentConnection;

    protected $parentGrammar;

    protected $parentClass;

    public function __construct(Builder $parentQuery, $type, string $table)
    {
        $this->type = $type;
        $this->table = $table;
        $this->parentClass = get_class($parentQuery);
        $this->parentGrammar = $parentQuery->getGrammar();
        $this->parentConnection = $parentQuery->getConnection();

        parent::__construct($this->parentConnection, $this->parentGrammar);
    }

    public function on($first, $operator = null, $second = null, $boolean = 'and'): self
    {
        if ($first instanceof Closure) {
            return $this->whereNested($first, $boolean);
        }

        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    public function orOn($first, $operator = null, $second = null): self
    {
        return $this->on($first, $operator, $second, 'or');
    }

    public function newQuery(): Builder
    {
        return new static($this->newParentQuery(), $this->type, $this->table);
    }

    protected function forSubQuery(): Builder
    {
        return $this->newParentQuery()->newQuery();
    }

    protected function newParentQuery(): Builder
    {
        $class = $this->parentClass;

        return new $class($this->parentConnection, $this->parentGrammar);
    }
}
