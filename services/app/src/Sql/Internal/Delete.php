<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql\Internal;

use Nsfisis\Albatross\Sql\QueryBuilder;

final class Delete
{
    private string $where = '';

    /**
     * @internal
     */
    public function __construct(
        private readonly QueryBuilder $sql,
        private readonly string $table,
    ) {
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
        $this->sql->_executeDelete($this, $params);
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
}
