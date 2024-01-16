<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Models;

use DateTimeImmutable;
use Nsfisis\Albatross\Exceptions\EntityValidationException;

final class Answer
{
    public function __construct(
        public readonly int $answer_id,
        public readonly int $quiz_id,
        public readonly int $answer_number,
        public readonly DateTimeImmutable $submitted_at,
        public readonly int $author_id,
        public readonly string $code,
        public readonly int $code_size,
        public readonly AggregatedExecutionStatus $execution_status,
        public readonly ?string $author_name, // joined
        public readonly ?bool $author_is_admin, // joined
    ) {
    }

    public static function create(
        int $quiz_id,
        int $author_id,
        string $code,
    ): self {
        self::validate($quiz_id, $author_id, $code);
        $answer = new self(
            answer_id: 0,
            quiz_id: $quiz_id,
            answer_number: 0,
            submitted_at: new DateTimeImmutable(), // dummy
            author_id: $author_id,
            code: $code,
            code_size: strlen(self::normalizeCode($code)), // not mb_strlen
            execution_status: AggregatedExecutionStatus::Pending,
            author_name: null,
            author_is_admin: null,
        );
        return $answer;
    }

    private static function validate(
        int $quiz_id,
        int $author_id,
        string $code,
    ): void {
        $errors = [];
        if (strlen($code) <= 0) {
            $errors['code'] = 'コードを入力してください';
        }
        if (10 * 1024 <= strlen($code)) {
            $errors['code'] = 'コードが長すぎます。10 KiB 未満まで縮めてください';
        }

        if (0 < count($errors)) {
            throw new EntityValidationException(errors: $errors);
        }
    }

    public static function normalizeCode(string $code): string
    {
        return preg_replace('/^\s*<\?(?:php\b)?\s*/', '', str_replace(["\r\n", "\r"], "\n", $code)) ?? $code;
    }
}
