<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql;

use Nsfisis\Albatross\Sql\Internal\Delete;
use Nsfisis\Albatross\Sql\Internal\Insert;
use Nsfisis\Albatross\Sql\Internal\InsertFromSelect;
use Nsfisis\Albatross\Sql\Internal\Select;
use Nsfisis\Albatross\Sql\Internal\Update;
use PDO;
use PDOStatement;

final class QueryBuilder
{
    /**
     * @var array<string, PDOStatement>
     */
    private array $stmtCache = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly PDO $conn,
    ) {
    }

    public function select(string|Select $table): Select
    {
        return new Select($this, $table);
    }

    public function insert(string $table): Insert
    {
        return new Insert($this, $table);
    }

    public function insertFromSelect(string $table): InsertFromSelect
    {
        return new InsertFromSelect($this, $table);
    }

    public function update(string $table): Update
    {
        return new Update($this, $table);
    }

    public function delete(string $table): Delete
    {
        return new Delete($this, $table);
    }

    public function schema(string $sql): void
    {
        $this->conn->exec($sql);
    }

    public function raw(string $sql): void
    {
        $this->conn->exec($sql);
    }

    /**
     * @internal
     * @param Select $select
     * @param array<string, string|int> $params
     * @return list<array<string, string>>
     */
    public function _executeSelect(Select $select, array $params): array
    {
        $stmt = $this->loadCacheOrPrepare($this->compileSelect($select));
        $ok = $stmt->execute($params);
        assert($ok);
        /** @var list<array<string, string>> */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * @param Select $select
     */
    private function compileSelect(Select $select): string
    {
        $table = $select->_getTable();
        $join = $select->_getJoin();
        $fields = $select->_getFields();
        $where = $select->_getWhere();
        $orderBy = $select->_getOrderBy();
        $limit = $select->_getLimit();

        return "SELECT " .
            implode(', ', $fields) .
            (
                $table instanceof Select
                    ? " FROM (" . $this->compileSelect($table) . ")"
                    : " FROM $table"
            ) .
            ($join !== null ? " $join->type $join->table ON $join->on" : '') .
            ($where !== '' ? " WHERE $where" : '') .
            (
                0 < count($orderBy)
                ? " ORDER BY " . implode(', ', array_map(fn ($field_and_order) => "{$field_and_order[0]} {$field_and_order[1]}", $orderBy))
                : ''
            ) .
            ($limit !== null ? " LIMIT $limit" : '');
    }

    /**
     * @internal
     * @return positive-int
     */
    public function _executeInsert(Insert $insert): int
    {
        $stmt = $this->loadCacheOrPrepare($this->compileInsert($insert));
        $ok = $stmt->execute(array_filter($insert->_getValues(), fn ($v) => !$v instanceof Select));
        assert($ok);
        return $this->lastInsertId();
    }

    private function compileInsert(Insert $insert): string
    {
        $table = $insert->_getTable();
        $values = $insert->_getValues();
        $columns = array_keys($values);

        if (count($columns) === 0) {
            return "INSERT INTO $table DEFAULT VALUES";
        }

        return "INSERT INTO $table (" .
            implode(', ', $columns) .
            ') VALUES (' .
            implode(
                ', ',
                array_map(
                    fn ($c) => (
                        $values[$c] instanceof Select
                            ? '(' . $this->compileSelect($values[$c]) . ')'
                            : ":$c"
                    ),
                    $columns,
                ),
            ) .
            ')';
    }

    /**
     * @internal
     * @param array<string, string|int> $params
     */
    public function _executeInsertFromSelect(InsertFromSelect $insert, array $params): void
    {
        $stmt = $this->loadCacheOrPrepare($this->compileInsertFromSelect($insert));
        $ok = $stmt->execute($params);
        assert($ok);
    }

    private function compileInsertFromSelect(InsertFromSelect $insert): string
    {
        $table = $insert->_getTable();
        $fields = $insert->_getFields();
        $from = $insert->_getFrom();

        return "INSERT INTO $table (" .
            implode(', ', $fields) .
            ') ' .
            $this->compileSelect($from);
    }

    /**
     * @internal
     * @param array<string, string|int> $params
     */
    public function _executeUpdate(Update $update, array $params): void
    {
        $stmt = $this->loadCacheOrPrepare($this->compileUpdate($update));
        $ok = $stmt->execute($params + $update->_getSet());
        assert($ok);
    }

    private function compileUpdate(Update $update): string
    {
        $table = $update->_getTable();
        $set = $update->_getSet();
        $columns = array_keys($set);
        $where = $update->_getWhere();

        return "UPDATE $table SET " .
            implode(', ', array_map(fn ($c) => "$c = :$c", $columns)) .
            ($where !== '' ? " WHERE $where" : '');
    }

    /**
     * @internal
     * @param array<string, string|int> $params
     */
    public function _executeDelete(Delete $delete, array $params): void
    {
        $stmt = $this->loadCacheOrPrepare($this->compileDelete($delete));
        $ok = $stmt->execute($params);
        assert($ok);
    }

    private function compileDelete(Delete $delete): string
    {
        $table = $delete->_getTable();
        $where = $delete->_getWhere();

        return "DELETE FROM $table" .
            ($where !== '' ? " WHERE $where" : '');
    }

    private function loadCacheOrPrepare(string $sql): PDOStatement
    {
        $cache = $this->stmtCache[$sql] ?? null;
        if ($cache !== null) {
            return $cache;
        }
        return $this->stmtCache[$sql] = $this->conn->prepare($sql);
    }

    /**
     * @return positive-int
     */
    private function lastInsertId(): int
    {
        $inserted_id = (int) $this->conn->lastInsertId();
        assert(0 < $inserted_id);
        return $inserted_id;
    }
}
