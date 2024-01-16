<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Models;

final class Testcase
{
    public function __construct(
        public readonly int $testcase_id,
        public readonly int $quiz_id,
        public readonly string $input,
        public readonly string $expected_result,
    ) {
    }

    public static function create(
        int $quiz_id,
        string $input,
        string $expected_result,
    ): self {
        return new self(
            testcase_id: 0,
            quiz_id: $quiz_id,
            input: $input,
            expected_result: $expected_result,
        );
    }
}
