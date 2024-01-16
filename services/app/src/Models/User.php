<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Models;

final class User
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $username,
        public readonly bool $is_admin,
    ) {
    }

    public static function create(
        string $username,
        bool $is_admin,
    ): self {
        return new self(
            user_id: 0,
            username: $username,
            is_admin: $is_admin,
        );
    }
}
