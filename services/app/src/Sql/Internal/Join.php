<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql\Internal;

final class Join
{
    /**
     * @param 'LEFT JOIN' $type
     */
    public function __construct(
        public readonly string $type,
        public readonly string $table,
        public readonly string $on,
    ) {
    }
}
