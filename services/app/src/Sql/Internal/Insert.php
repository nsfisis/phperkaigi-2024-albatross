<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql\Internal;

use Nsfisis\Albatross\Exceptions\InvalidSqlException;
use Nsfisis\Albatross\Sql\QueryBuilder;

final class Insert
{
    /**
     * @var ?array<string, string|int|Select> $values
     */
    private ?array $values;

    /**
     * @internal
     */
    public function __construct(
        private readonly QueryBuilder $sql,
        private readonly string $table,
    ) {
    }

    /**
     * @param array<string, string|int|Select> $values
     */
    public function values(array $values): self
    {
        $this->values = $values;
        return $this;
    }

    /**
     * @return positive-int
     */
    public function execute(): int
    {
        return $this->sql->_executeInsert($this);
    }

    /**
     * @internal
     */
    public function _getTable(): string
    {
        return $this->table;
    }

    /**
     * @internal
     * @return array<string, string|int|Select>
     */
    public function _getValues(): array
    {
        if (!isset($this->values)) {
            throw new InvalidSqlException('INSERT: $values must be set before calling execute()');
        }
        return $this->values;
    }
}
