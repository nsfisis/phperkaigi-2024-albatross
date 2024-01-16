<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Database;

use Exception;
use LogicException;
use Nsfisis\Albatross\Sql\QueryBuilder;
use PDO;
use PDOException;

final class Connection
{
    private readonly PDO $conn;

    public function __construct(
        string $driver,
        string $host,
        int $port,
        string $name,
        string $user,
        string $password,
        int $max_tries = 10,
        int $sleep_sec = 3,
    ) {
        if ($driver !== 'pgsql') {
            throw new LogicException('Only pgsql is supported');
        }
        $this->conn = self::tryConnect(
            "$driver:host=$host;port=$port;dbname=$name;user=$user;password=$password",
            $max_tries,
            $sleep_sec,
        );
    }

    public static function tryConnect(
        string $dsn,
        int $max_tries,
        int $sleep_sec,
    ): PDO {
        $tries = 0;
        while (true) {
            try {
                return self::connect($dsn);
            } catch (PDOException $e) {
                if ($max_tries <= $tries) {
                    throw $e;
                }
                sleep($sleep_sec);
            }
            $tries++;
        }
    }

    public function query(): QueryBuilder
    {
        return new QueryBuilder($this->conn);
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $this->conn->beginTransaction();
        try {
            $result = $fn();
            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * @throws PDOException
     */
    private static function connect(string $dsn): PDO
    {
        return new PDO(
            dsn: $dsn,
            options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true,
            ],
        );
    }
}
