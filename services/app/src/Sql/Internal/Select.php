<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql\Internal;

use Nsfisis\Albatross\Exceptions\InvalidSqlException;
use Nsfisis\Albatross\Sql\QueryBuilder;

/**
 * @internal
 */
final class Select
{
    /**
     * @var ?list<string>
     */
    private ?array $fields;

    private ?Join $join = null;

    private string $where = '';

    /**
     * @var list<array{string, string}>
     */
    private array $orderBy = [];

    /**
     * @var ?positive-int
     */
    private ?int $limit = null;

    public function __construct(
        private readonly QueryBuilder $sql,
        private readonly string $table,
    ) {
    }

    public function leftJoin(string $table, string $on): self
    {
        $this->join = new Join('LEFT JOIN', $table, $on);
        return $this;
    }

    /**
     * @param list<string> $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function where(string $where): self
    {
        $this->where = $where;
        return $this;
    }

    /**
     * @param list<array{string, string}> $orderBy
     */
    public function orderBy(array $orderBy): self
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * @param positive-int $limit
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function first(): SelectFirst
    {
        $this->limit = 1;
        return new SelectFirst($this);
    }

    /**
     * @param array<string, string|int> $params
     * @return list<array<string, string>>
     */
    public function execute(array $params = []): array
    {
        return $this->sql->_executeSelect($this, $params);
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
            throw new InvalidSqlException('SELECT: $fields must be set before calling execute()');
        }
        return $this->fields;
    }

    /**
     * @internal
     */
    public function _getJoin(): ?Join
    {
        return $this->join;
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
     * @return list<array{string, string}>
     */
    public function _getOrderBy(): array
    {
        return $this->orderBy;
    }

    /**
     * @return ?positive-int
     */
    public function _getLimit(): ?int
    {
        return $this->limit;
    }
}
