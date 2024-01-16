<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql\Internal;

use Nsfisis\Albatross\Exceptions\InvalidSqlException;
use Nsfisis\Albatross\Sql\QueryBuilder;

final class Update
{
    /**
     * @var ?array<string, string|int>
     */
    private ?array $set;

    private string $where = '';

    /**
     * @internal
     */
    public function __construct(
        private readonly QueryBuilder $sql,
        private readonly string $table,
    ) {
    }

    /**
     * @param array<string, string|int> $set
     */
    public function set(array $set): self
    {
        $this->set = $set;
        return $this;
    }

    public function where(string $where): self
    {
        $this->where = $where;
        return $this;
    }

    /**
     * @param array<string, string|int> $params
     */
    public function execute(array $params = []): void
    {
        $this->sql->_executeUpdate($this, $params);
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
     */
    public function _getWhere(): string
    {
        return $this->where;
    }

    /**
     * @internal
     * @return array<string, string|int>
     */
    public function _getSet(): array
    {
        if (!isset($this->set)) {
            throw new InvalidSqlException('UPDATE: $set must be set before calling execute()');
        }
        return $this->set;
    }
}
