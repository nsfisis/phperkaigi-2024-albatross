<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\SandboxExec;

use Nsfisis\Albatross\Models\ExecutionStatus;

final class ExecutionResult
{
    public function __construct(
        public readonly ExecutionStatus $status,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {
    }
}
