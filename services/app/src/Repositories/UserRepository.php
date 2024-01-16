<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Repositories;

use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Exceptions\EntityValidationException;
use Nsfisis\Albatross\Models\User;
use PDOException;

final class UserRepository
{
    public function __construct(
        private readonly Connection $conn,
    ) {
    }

    /**
     * @return User[]
     */
    public function listAll(): array
    {
        $result = $this->conn
            ->query()
            ->select('users')
            ->fields(['user_id', 'username', 'is_admin'])
            ->orderBy([['user_id', 'ASC']])
            ->execute();
        return array_map($this->mapRawRowToUser(...), $result);
    }

    public function findById(int $user_id): ?User
    {
        $result = $this->conn
            ->query()
            ->select('users')
            ->fields(['user_id', 'username', 'is_admin'])
            ->where('user_id = :user_id')
            ->first()
            ->execute(['user_id' => $user_id]);
        return isset($result) ? $this->mapRawRowToUser($result) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $result = $this->conn
            ->query()
            ->select('users')
            ->fields(['user_id', 'username', 'is_admin'])
            ->where('username = :username')
            ->first()
            ->execute(['username' => $username]);
        return isset($result) ? $this->mapRawRowToUser($result) : null;
    }

    /**
     * @return positive-int
     */
    public function create(
        string $username,
        bool $is_admin,
    ): int {
        $user = User::create(
            username: $username,
            is_admin: $is_admin,
        );

        try {
            return $this->conn
                ->query()
                ->insert('users')
                ->values([
                    'username' => $user->username,
                    'is_admin' => +$user->is_admin,
                ])
                ->execute();
        } catch (PDOException $e) {
            throw new EntityValidationException(
                message: 'ユーザの作成に失敗しました',
                previous: $e,
            );
        }
    }

    public function update(
        int $user_id,
        bool $is_admin,
    ): void {
        $this->conn
            ->query()
            ->update('users')
            ->set([
                'is_admin' => +$is_admin,
            ])
            ->where('user_id = :user_id')
            ->execute(['user_id' => $user_id]);
    }

    public function delete(int $user_id): void
    {
        $this->conn
            ->query()
            ->delete('users')
            ->where('user_id = :user_id')
            ->execute(['user_id' => $user_id]);
    }

    /**
     * @param array<string, string> $row
     */
    private function mapRawRowToUser(array $row): User
    {
        assert(isset($row['user_id']));
        assert(isset($row['username']));
        assert(isset($row['is_admin']));

        $user_id = (int) $row['user_id'];
        $is_admin = (bool) $row['is_admin'];

        return new User(
            user_id: $user_id,
            username: $row['username'],
            is_admin: $is_admin,
        );
    }
}
