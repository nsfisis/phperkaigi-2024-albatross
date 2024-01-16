<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Models;

final class TestcaseExecution
{
    public function __construct(
        public readonly int $testcase_execution_id,
        public readonly int $testcase_id,
        public readonly int $answer_id,
        public readonly ExecutionStatus $status,
        public readonly ?string $stdout,
        public readonly ?string $stderr,
    ) {
    }

    public static function create(
        int $testcase_id,
        int $answer_id,
    ): self {
        return new self(
            testcase_execution_id: 0,
            testcase_id: $testcase_id,
            answer_id: $answer_id,
            status: ExecutionStatus::Pending,
            stdout: null,
            stderr: null,
        );
    }
}
