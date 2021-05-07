<?php

declare(strict_types = 1);

namespace MgCosta\Spanner\Model;

use Illuminate\Support\Collection;
use MgCosta\Spanner\Builder\Builder;
use MgCosta\Spanner\Model\Strategies\StrategyFactory;
use MgCosta\Spanner\Traits\ForwardCall;
use Google\Cloud\Spanner\Database;
use MgCosta\Spanner\Traits\ModelAttributes;
use InvalidArgumentException;

abstract class Model
{
    use ForwardCall, ModelAttributes;
    /**
     * The connection resolver instance.
     *
     * @var Database;
     */
    protected static $connection;

    protected static $strategyFactory;

    protected $table;

    protected $primaryKey = 'id';

    protected $keyStrategy = 'uuid4';

    public $strategies = ['uuid4', 'increment', false];

    public function getTable(): string
    {
        $parseClassName = explode('\\', get_class($this));
        return $this->table ?? strtolower($parseClassName[count($parseClassName) - 1]);
    }

    public function setTable($table): self
    {
        $this->table = $table;
        return $this;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getConnection(): Database
    {
        return static::$connection;
    }

    public static function setConnectionDatabase(Database $connection)
    {
        static::$connection = $connection;
    }

    public static function setStrategyFactory($factory)
    {
        static::$strategyFactory = $factory;
    }

    public function getStrategyFactory(): StrategyFactory
    {
        return static::$strategyFactory;
    }

    public function newQuery(): Builder
    {
        return $this->newBuilder($this->getConnection())->setModel($this);
    }

    public function newBuilder(Database $connection): Builder
    {
        return new Builder($connection);
    }

    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    public static function __callStatic($method, $parameters)
    {
        return (new static())->$method(...$parameters);
    }

    public static function query(): Builder
    {
        return (new static)->newQuery();
    }

    public static function all($columns = ['*']): Collection
    {
        return static::query()->get(
            is_array($columns) ? $columns : func_get_args()
        );
    }

    public function save(): bool
    {
        $attributes = $this->getAttributes();

        if (empty($attributes[$this->getPrimaryKey()]) && $this->isValidStrategy()) {
            $attributes[$this->getPrimaryKey()] = $this->getStrategyFactory()->generateKey(
                $this->keyStrategy,
                $this
            );
        }

        $this->getConnection()->insertOrUpdate($this->getTable(), $attributes);
        return true;
    }

    private function isValidStrategy(): bool
    {
        if (!in_array($this->keyStrategy, $this->strategies)) {
            throw new InvalidArgumentException("Invalid strategy provided on the model " . get_class($this));
        }
        return true;
    }
}
