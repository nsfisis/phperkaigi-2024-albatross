<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql\Internal;

/**
 * @internal
 */
final class SelectFirst
{
    public function __construct(
        private readonly Select $inner,
    ) {
    }

    /**
     * @param array<string, string|int> $params
     * @return ?array<string, string>
     */
    public function execute(array $params = []): ?array
    {
        $result = $this->inner->execute($params);
        return $result[0] ?? null;
    }
}
