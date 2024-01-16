<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql\Internal;

use Nsfisis\Albatross\Exceptions\InvalidSqlException;
use Nsfisis\Albatross\Sql\QueryBuilder;

final class InsertFromSelect
{
    /**
     * @var ?list<string>
     */
    private ?array $fields;

    private ?Select $from;

    /**
     * @internal
     */
    public function __construct(
        private readonly QueryBuilder $sql,
        private readonly string $table,
    ) {
    }

    /**
     * @param list<string> $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function from(Select $from): self
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @param array<string, string|int> $params
     */
    public function execute(array $params = []): void
    {
        $this->sql->_executeInsertFromSelect($this, $params);
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
     * @return list<string>
     */
    public function _getFields(): array
    {
        if (!isset($this->fields)) {
            throw new InvalidSqlException('INSERT SELECT: $fields must be set before calling execute()');
        }
        return $this->fields;
    }

    public function _getFrom(): Select
    {
        if (!isset($this->from)) {
            throw new InvalidSqlException('INSERT SELECT: $from must be set before calling execute()');
        }
        return $this->from;
    }
}
