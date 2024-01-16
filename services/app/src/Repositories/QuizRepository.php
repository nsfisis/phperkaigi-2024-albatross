<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Exceptions\EntityValidationException;
use Nsfisis\Albatross\Models\Quiz;
use Nsfisis\Albatross\Sql\DateTimeParser;
use PDOException;

final class QuizRepository
{
    private const QUIZ_FIELDS = [
        'quiz_id',
        'created_at',
        'started_at',
        'ranking_hidden_at',
        'finished_at',
        'title',
        'slug',
        'description',
        'example_code',
        'birdie_code_size',
    ];

    public function __construct(
        private readonly Connection $conn,
    ) {
    }

    /**
     * @return Quiz[]
     */
    public function listAll(): array
    {
        $result = $this->conn
            ->query()
            ->select('quizzes')
            ->fields(self::QUIZ_FIELDS)
            ->orderBy([['created_at', 'ASC']])
            ->execute();
        return array_map($this->mapRawRowToQuiz(...), $result);
    }

    /**
     * @return Quiz[]
     */
    public function listStarted(?DateTimeImmutable $now = null): array
    {
        if ($now === null) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        $result = $this->conn
            ->query()
            ->select('quizzes')
            ->fields(self::QUIZ_FIELDS)
            ->where('started_at <= :now')
            ->orderBy([['created_at', 'ASC']])
            ->execute(['now' => $now->format('Y-m-d H:i:s.u')]);
        return array_map($this->mapRawRowToQuiz(...), $result);
    }

    public function findById(int $quiz_id): ?Quiz
    {
        $result = $this->conn
            ->query()
            ->select('quizzes')
            ->fields(self::QUIZ_FIELDS)
            ->where('quiz_id = :quiz_id')
            ->first()
            ->execute(['quiz_id' => $quiz_id]);
        return isset($result) ? $this->mapRawRowToQuiz($result) : null;
    }

    public function findBySlug(string $slug): ?Quiz
    {
        $result = $this->conn
            ->query()
            ->select('quizzes')
            ->fields(self::QUIZ_FIELDS)
            ->where('slug = :slug')
            ->first()
            ->execute(['slug' => $slug]);
        return isset($result) ? $this->mapRawRowToQuiz($result) : null;
    }

    public function create(
        string $title,
        string $slug,
        string $description,
        string $example_code,
        ?int $birdie_code_size,
        DateTimeImmutable $started_at,
        DateTimeImmutable $ranking_hidden_at,
        DateTimeImmutable $finished_at,
    ): int {
        $quiz = Quiz::create(
            title: $title,
            slug: $slug,
            description: $description,
            example_code: $example_code,
            birdie_code_size: $birdie_code_size,
            started_at: $started_at,
            ranking_hidden_at: $ranking_hidden_at,
            finished_at: $finished_at,
        );

        $values = [
            'title' => $quiz->title,
            'slug' => $quiz->slug,
            'description' => $quiz->description,
            'example_code' => $quiz->example_code,
            'started_at' => $quiz->started_at->format('Y-m-d H:i:s.u'),
            'ranking_hidden_at' => $quiz->ranking_hidden_at->format('Y-m-d H:i:s.u'),
            'finished_at' => $quiz->finished_at->format('Y-m-d H:i:s.u'),
        ];
        if ($quiz->birdie_code_size !== null) {
            $values['birdie_code_size'] = $quiz->birdie_code_size;
        }

        try {
            return $this->conn
                ->query()
                ->insert('quizzes')
                ->values($values)
                ->execute();
        } catch (PDOException $e) {
            throw new EntityValidationException(
                message: '問題の作成に失敗しました',
                previous: $e,
            );
        }
    }

    public function update(
        int $quiz_id,
        string $title,
        string $description,
        string $example_code,
        ?int $birdie_code_size,
        DateTimeImmutable $started_at,
        DateTimeImmutable $ranking_hidden_at,
        DateTimeImmutable $finished_at,
    ): void {
        Quiz::validate(
            $title,
            'dummy',
            $description,
            $example_code,
            $birdie_code_size,
            $started_at,
            $ranking_hidden_at,
            $finished_at,
        );

        $values = [
            'title' => $title,
            'description' => $description,
            'example_code' => $example_code,
            'started_at' => $started_at->format('Y-m-d H:i:s.u'),
            'ranking_hidden_at' => $ranking_hidden_at->format('Y-m-d H:i:s.u'),
            'finished_at' => $finished_at->format('Y-m-d H:i:s.u'),
        ];
        if ($birdie_code_size !== null) {
            $values['birdie_code_size'] = $birdie_code_size;
        }

        try {
            $this->conn
                ->query()
                ->update('quizzes')
                ->set($values)
                ->where('quiz_id = :quiz_id')
                ->execute(['quiz_id' => $quiz_id]);
        } catch (PDOException $e) {
            throw new EntityValidationException(
                message: '問題の更新に失敗しました',
                previous: $e,
            );
        }
    }

    public function delete(int $quiz_id): void
    {
        $this->conn
            ->query()
            ->delete('quizzes')
            ->where('quiz_id = :quiz_id')
            ->execute(['quiz_id' => $quiz_id]);
    }

    /**
     * @param array<string, string> $row
     */
    private function mapRawRowToQuiz(array $row): Quiz
    {
        assert(isset($row['quiz_id']));
        assert(isset($row['created_at']));
        assert(isset($row['started_at']));
        assert(isset($row['ranking_hidden_at']));
        assert(isset($row['finished_at']));
        assert(isset($row['title']));
        assert(isset($row['slug']));
        assert(isset($row['description']));
        assert(isset($row['example_code']));

        $quiz_id = (int) $row['quiz_id'];
        $created_at = DateTimeParser::parse($row['created_at']);
        assert($created_at instanceof DateTimeImmutable, "Failed to parse " . $row['created_at']);
        $started_at = DateTimeParser::parse($row['started_at']);
        assert($started_at instanceof DateTimeImmutable, "Failed to parse " . $row['started_at']);
        $ranking_hidden_at = DateTimeParser::parse($row['ranking_hidden_at']);
        assert($ranking_hidden_at instanceof DateTimeImmutable, "Failed to parse " . $row['ranking_hidden_at']);
        $finished_at = DateTimeParser::parse($row['finished_at']);
        assert($finished_at instanceof DateTimeImmutable, "Failed to parse " . $row['finished_at']);

        return new Quiz(
            quiz_id: $quiz_id,
            created_at: $created_at,
            started_at: $started_at,
            ranking_hidden_at: $ranking_hidden_at,
            finished_at: $finished_at,
            title: $row['title'],
            slug: $row['slug'],
            description: $row['description'],
            example_code: $row['example_code'],
            birdie_code_size: ($row['birdie_code_size'] ?? '') === '' ? null : (int) $row['birdie_code_size'],
        );
    }
}
