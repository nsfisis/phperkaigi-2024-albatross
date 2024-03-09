<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Repositories;

use DateTimeImmutable;
use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Exceptions\EntityValidationException;
use Nsfisis\Albatross\Models\AggregatedExecutionStatus;
use Nsfisis\Albatross\Models\Answer;
use Nsfisis\Albatross\Sql\DateTimeParser;
use PDOException;

final class AnswerRepository
{
    private const ANSWER_FIELDS = [
        'answer_id',
        'quiz_id',
        'answer_number',
        'submitted_at',
        'author_id',
        'code',
        'code_size',
        'execution_status',
    ];

    private const ANSWER_JOIN_USER_FIELDS = [
        'users.username AS author_name',
        'users.is_admin AS author_is_admin',
    ];

    public function __construct(
        private readonly Connection $conn,
    ) {
    }

    /**
     * @return Answer[]
     */
    public function listByQuizId(int $quiz_id, bool $show_admin = false): array
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([...self::ANSWER_FIELDS, ...self::ANSWER_JOIN_USER_FIELDS])
            ->where(
                'quiz_id = :quiz_id'
                . ($show_admin ? '' : ' AND users.is_admin = FALSE')
            )
            ->orderBy([['execution_status', 'DESC'], ['code_size', 'ASC'], ['submitted_at', 'ASC']])
            ->execute(['quiz_id' => $quiz_id]);
        return array_map($this->mapRawRowToAnswer(...), $result);
    }

    /**
     * @return Answer[]
     */
    public function listByQuizIdAndAuthorId(int $quiz_id, int $author_id): array
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([...self::ANSWER_FIELDS, ...self::ANSWER_JOIN_USER_FIELDS])
            ->where('quiz_id = :quiz_id AND author_id = :author_id')
            ->orderBy([['execution_status', 'DESC'], ['code_size', 'ASC'], ['submitted_at', 'ASC']])
            ->execute(['quiz_id' => $quiz_id, 'author_id' => $author_id]);
        return array_map($this->mapRawRowToAnswer(...), $result);
    }

    public function findByQuizIdAndAnswerNumber(int $quiz_id, int $answer_number): ?Answer
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([...self::ANSWER_FIELDS, ...self::ANSWER_JOIN_USER_FIELDS])
            ->where('quiz_id = :quiz_id AND answer_number = :answer_number')
            ->first()
            ->execute(['quiz_id' => $quiz_id, 'answer_number' => $answer_number]);
        return isset($result) ? $this->mapRawRowToAnswer($result) : null;
    }

    public function findById(int $answer_id): ?Answer
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([...self::ANSWER_FIELDS, ...self::ANSWER_JOIN_USER_FIELDS])
            ->where('answer_id = :answer_id')
            ->first()
            ->execute(['answer_id' => $answer_id]);
        return isset($result) ? $this->mapRawRowToAnswer($result) : null;
    }

    /**
     * @param positive-int $upto
     * @return Answer[]
     */
    public function getRanking(int $quiz_id, int $upto, bool $show_admin = false): array
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([...self::ANSWER_FIELDS, ...self::ANSWER_JOIN_USER_FIELDS])
            ->where(
                'quiz_id = :quiz_id AND execution_status = :execution_status'
                . ($show_admin ? '' : ' AND users.is_admin = FALSE')
            )
            ->orderBy([['code_size', 'ASC'], ['submitted_at', 'ASC']])
            ->limit($upto)
            ->execute(['quiz_id' => $quiz_id, 'execution_status' => AggregatedExecutionStatus::OK->toInt()]);
        return array_map($this->mapRawRowToAnswer(...), $result);
    }

    /**
     * @param positive-int $upto
     * @return Answer[]
     */
    public function getRankingByBestScores(int $quiz_id, int $upto, bool $show_admin = false): array
    {
        $q = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([
                ...self::ANSWER_FIELDS,
                ...self::ANSWER_JOIN_USER_FIELDS,
                'ROW_NUMBER() OVER(PARTITION BY answers.author_id ORDER BY answers.code_size ASC, answers.submitted_at ASC) AS r',
            ])
            ->where(
                'quiz_id = :quiz_id AND execution_status = :execution_status'
                . ($show_admin ? '' : ' AND users.is_admin = FALSE')
            );

        $result = $this->conn
            ->query()
            ->select($q)
            ->fields([
                ...self::ANSWER_FIELDS,
                'author_name',
                'author_is_admin',
            ])
            ->where('r = 1')
            ->orderBy([['code_size', 'ASC'], ['submitted_at', 'ASC']])
            ->execute(['quiz_id' => $quiz_id, 'execution_status' => AggregatedExecutionStatus::OK->toInt()]);
        return array_map($this->mapRawRowToAnswer(...), $result);
    }

    public function getBestCode(int $quiz_id, bool $show_admin = false): ?string
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([...self::ANSWER_FIELDS, ...self::ANSWER_JOIN_USER_FIELDS])
            ->where(
                'quiz_id = :quiz_id AND execution_status = :execution_status'
                . ($show_admin ? '' : ' AND users.is_admin = FALSE')
            )
            ->orderBy([['code_size', 'ASC'], ['submitted_at', 'ASC']])
            ->first()
            ->execute(['quiz_id' => $quiz_id, 'execution_status' => AggregatedExecutionStatus::OK->toInt()]);

        return isset($result) ? $this->mapRawRowToAnswer($result)->code : null;
    }

    /**
     * @return Answer[]
     */
    public function listAllCorrectAnswers(int $quiz_id, bool $show_admin = false): array
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([...self::ANSWER_FIELDS, ...self::ANSWER_JOIN_USER_FIELDS])
            ->where(
                'quiz_id = :quiz_id AND execution_status = :execution_status'
                . ($show_admin ? '' : ' AND users.is_admin = FALSE')
            )
            ->orderBy([['submitted_at', 'ASC']])
            ->execute(['quiz_id' => $quiz_id, 'execution_status' => AggregatedExecutionStatus::OK->toInt()]);
        return array_map($this->mapRawRowToAnswer(...), $result);
    }

    public function countUniqueAuthors(bool $show_admin = false): int
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields(['COUNT(DISTINCT author_id) AS count'])
            ->where($show_admin ? '' : 'users.is_admin = FALSE')
            ->first()
            ->execute();
        assert(isset($result['count']));
        return (int) $result['count'];
    }

    public function countAll(bool $show_admin = false): int
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields(['COUNT(*) AS count'])
            ->where($show_admin ? '' : 'users.is_admin = FALSE')
            ->first()
            ->execute();
        assert(isset($result['count']));
        return (int) $result['count'];
    }

    public function create(
        int $quiz_id,
        int $author_id,
        string $code,
    ): int {
        $answer = Answer::create(
            quiz_id: $quiz_id,
            author_id: $author_id,
            code: $code,
        );

        $next_answer_number_query = $this->conn
            ->query()
            ->select('answers')
            ->fields(['COALESCE(MAX(answer_number), 0) + 1'])
            ->where('quiz_id = :quiz_id')
            ->limit(1);

        try {
            return $this->conn
                ->query()
                ->insert('answers')
                ->values([
                    'quiz_id' => $answer->quiz_id,
                    'answer_number' => $next_answer_number_query,
                    'author_id' => $answer->author_id,
                    'code' => $answer->code,
                    'code_size' => $answer->code_size,
                    'execution_status' => $answer->execution_status->toInt(),
                ])
                ->execute();
        } catch (PDOException $e) {
            throw new EntityValidationException(
                message: '回答の投稿に失敗しました',
                previous: $e,
            );
        }
    }

    public function markAllAsPending(int $quiz_id): void
    {
        $this->conn
            ->query()
            ->update('answers')
            ->set(['execution_status' => AggregatedExecutionStatus::Pending->toInt()])
            ->where('quiz_id = :quiz_id')
            ->execute(['quiz_id' => $quiz_id]);
    }

    public function markAllAsUpdateNeeded(int $quiz_id): void
    {
        $this->conn
            ->query()
            ->update('answers')
            ->set(['execution_status' => AggregatedExecutionStatus::UpdateNeeded->toInt()])
            ->where('quiz_id = :quiz_id')
            ->execute(['quiz_id' => $quiz_id]);
    }

    public function markAsPending(int $answer_id): void
    {
        $this->conn
            ->query()
            ->update('answers')
            ->set(['execution_status' => AggregatedExecutionStatus::Pending->toInt()])
            ->where('answer_id = :answer_id')
            ->execute(['answer_id' => $answer_id]);
    }

    public function tryGetNextUpdateNeededAnswer(): ?Answer
    {
        $result = $this->conn
            ->query()
            ->select('answers')
            ->leftJoin('users', 'answers.author_id = users.user_id')
            ->fields([...self::ANSWER_FIELDS, ...self::ANSWER_JOIN_USER_FIELDS])
            ->where('execution_status = :execution_status')
            ->orderBy([['submitted_at', 'ASC']])
            ->first()
            ->execute(['execution_status' => AggregatedExecutionStatus::UpdateNeeded->toInt()]);
        return isset($result) ? $this->mapRawRowToAnswer($result) : null;
    }

    public function updateExecutionStatus(
        int $answer_id,
        AggregatedExecutionStatus $execution_status,
    ): void {
        $this->conn
            ->query()
            ->update('answers')
            ->set(['execution_status' => $execution_status->toInt()])
            ->where('answer_id = :answer_id')
            ->execute(['answer_id' => $answer_id]);
    }

    public function deleteAllByQuizId(int $quiz_id): void
    {
        $this->conn
            ->query()
            ->delete('answers')
            ->where('quiz_id = :quiz_id')
            ->execute(['quiz_id' => $quiz_id]);
    }

    public function deleteAllByUserId(int $user_id): void
    {
        $this->conn
            ->query()
            ->delete('answers')
            ->where('author_id = :author_id')
            ->execute(['author_id' => $user_id]);
    }

    /**
     * @param array<string, ?string> $row
     */
    private function mapRawRowToAnswer(array $row): Answer
    {
        assert(isset($row['answer_id']));
        assert(isset($row['quiz_id']));
        assert(isset($row['answer_number']));
        assert(isset($row['submitted_at']));
        assert(isset($row['author_id']));
        assert(isset($row['code']));
        assert(isset($row['code_size']));
        assert(isset($row['execution_status']));

        $answer_id = (int) $row['answer_id'];
        $quiz_id = (int) $row['quiz_id'];
        $answer_number = (int) $row['answer_number'];
        $submitted_at = DateTimeParser::parse($row['submitted_at']);
        assert($submitted_at instanceof DateTimeImmutable, "Failed to parse " . $row['submitted_at']);
        $author_id = (int) $row['author_id'];

        return new Answer(
            answer_id: $answer_id,
            quiz_id: $quiz_id,
            answer_number: $answer_number,
            submitted_at: $submitted_at,
            author_id: $author_id,
            code: $row['code'],
            code_size: (int) $row['code_size'],
            execution_status: AggregatedExecutionStatus::fromInt((int)$row['execution_status']),
            author_name: $row['author_name'] ?? null,
            author_is_admin: (bool) ($row['author_is_admin'] ?? null),
        );
    }
}
