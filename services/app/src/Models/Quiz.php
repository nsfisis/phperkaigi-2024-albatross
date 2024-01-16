<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Models;

use DateTimeImmutable;
use DateTimeZone;
use Nsfisis\Albatross\Exceptions\EntityValidationException;

final class Quiz
{
    public function __construct(
        public readonly int $quiz_id,
        public readonly DateTimeImmutable $created_at,
        public readonly DateTimeImmutable $started_at,
        public readonly DateTimeImmutable $ranking_hidden_at,
        public readonly DateTimeImmutable $finished_at,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $description,
        public readonly string $example_code,
        public readonly ?int $birdie_code_size,
    ) {
    }

    public function isStarted(?DateTimeImmutable $now = null): bool
    {
        if ($now === null) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        return $this->started_at <= $now;
    }

    public function isFinished(?DateTimeImmutable $now = null): bool
    {
        if ($now === null) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        return $this->finished_at <= $now;
    }

    public function isOpenToAnswer(?DateTimeImmutable $now = null): bool
    {
        return $this->isStarted($now) && !$this->isFinished($now);
    }

    public function isClosedToAnswer(?DateTimeImmutable $now = null): bool
    {
        return !$this->isOpenToAnswer($now);
    }

    public function isRankingHidden(?DateTimeImmutable $now = null): bool
    {
        if ($now === null) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        return $this->ranking_hidden_at <= $now && !$this->isFinished($now);
    }

    public static function create(
        string $title,
        string $slug,
        string $description,
        string $example_code,
        ?int $birdie_code_size,
        DateTimeImmutable $started_at,
        DateTimeImmutable $ranking_hidden_at,
        DateTimeImmutable $finished_at,
    ): self {
        self::validate(
            $title,
            $slug,
            $description,
            $example_code,
            $birdie_code_size,
            $started_at,
            $ranking_hidden_at,
            $finished_at,
        );
        $quiz = new self(
            quiz_id: 0,
            created_at: new DateTimeImmutable(), // dummy
            started_at: $started_at,
            ranking_hidden_at: $ranking_hidden_at,
            finished_at: $finished_at,
            title: $title,
            slug: $slug,
            description: $description,
            example_code: $example_code,
            birdie_code_size: $birdie_code_size,
        );
        return $quiz;
    }

    public static function validate(
        string $title,
        string $slug,
        string $description,
        string $example_code,
        ?int $birdie_code_size,
        DateTimeImmutable $started_at,
        DateTimeImmutable $ranking_hidden_at,
        DateTimeImmutable $finished_at,
    ): void {
        $errors = [];
        if (strlen($slug) < 1) {
            $errors['slug'] = 'スラグは必須です';
        }
        if (32 < strlen($slug)) {
            $errors['slug'] = 'スラグは32文字以下である必要があります';
        }
        if (strlen($description) < 1) {
            $errors['description'] = '説明は必須です';
        }
        if (strlen($example_code) < 1) {
            $errors['example_code'] = '実装例は必須です';
        }
        if ($birdie_code_size !== null && $birdie_code_size < 1) {
            $errors['birdie_code_size'] = 'バーディになるコードサイズは 1 byte 以上である必要があります';
        }
        if ($ranking_hidden_at < $started_at) {
            $errors['ranking_hidden_at'] = 'ランキングが非表示になる日時は開始日時より後である必要があります';
        }
        if ($finished_at < $ranking_hidden_at) {
            $errors['finished_at'] = '終了日時はランキングが非表示になる日時より後である必要があります';
        }

        if (0 < count($errors)) {
            throw new EntityValidationException(errors: $errors);
        }
    }
}
