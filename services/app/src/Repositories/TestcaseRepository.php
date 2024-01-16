<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Repositories;

use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Exceptions\EntityValidationException;
use Nsfisis\Albatross\Models\Testcase;
use PDOException;

final class TestcaseRepository
{
    private const TESTCASE_FIELDS = [
        'testcase_id',
        'quiz_id',
        'input',
        'expected_result',
    ];

    public function __construct(
        private readonly Connection $conn,
    ) {
    }

    public function findByQuizIdAndTestcaseId(
        int $quiz_id,
        int $testcase_id,
    ): ?Testcase {
        $result = $this->conn
            ->query()
            ->select('testcases')
            ->fields(self::TESTCASE_FIELDS)
            ->where('quiz_id = :quiz_id AND testcase_id = :testcase_id')
            ->first()
            ->execute([
                'quiz_id' => $quiz_id,
                'testcase_id' => $testcase_id,
            ]);
        return isset($result) ? $this->mapRawRowToTestcase($result) : null;
    }

    /**
     * @return Testcase[]
     */
    public function listByQuizId(int $quiz_id): array
    {
        $result = $this->conn
            ->query()
            ->select('testcases')
            ->fields(self::TESTCASE_FIELDS)
            ->where('quiz_id = :quiz_id')
            ->orderBy([['testcase_id', 'ASC']])
            ->execute(['quiz_id' => $quiz_id]);
        return array_map($this->mapRawRowToTestcase(...), $result);
    }

    public function create(
        int $quiz_id,
        string $input,
        string $expected_result,
    ): int {
        $testcase = Testcase::create(
            quiz_id: $quiz_id,
            input: $input,
            expected_result: $expected_result,
        );

        $values = [
            'quiz_id' => $testcase->quiz_id,
            'input' => $testcase->input,
            'expected_result' => $testcase->expected_result,
        ];

        try {
            return $this->conn
                ->query()
                ->insert('testcases')
                ->values($values)
                ->execute();
        } catch (PDOException $e) {
            throw new EntityValidationException(
                message: 'テストケースの作成に失敗しました',
                previous: $e,
            );
        }
    }

    public function update(
        int $testcase_id,
        string $input,
        string $expected_result,
    ): void {
        try {
            $this->conn
                ->query()
                ->update('testcases')
                ->set([
                    'input' => $input,
                    'expected_result' => $expected_result,
                ])
                ->where('testcase_id = :testcase_id')
                ->execute([
                    'testcase_id' => $testcase_id,
                ]);
        } catch (PDOException $e) {
            throw new EntityValidationException(
                message: 'テストケースの更新に失敗しました',
                previous: $e,
            );
        }
    }

    public function delete(int $testcase_id): void
    {
        $this->conn
            ->query()
            ->delete('testcases')
            ->where('testcase_id = :testcase_id')
            ->execute(['testcase_id' => $testcase_id]);
    }

    /**
     * @param array<string, string> $row
     */
    private function mapRawRowToTestcase(array $row): Testcase
    {
        assert(isset($row['testcase_id']));
        assert(isset($row['quiz_id']));
        assert(isset($row['input']));
        assert(isset($row['expected_result']));

        $testcase_id = (int) $row['testcase_id'];
        $quiz_id = (int) $row['quiz_id'];

        return new Testcase(
            testcase_id: $testcase_id,
            quiz_id: $quiz_id,
            input: $row['input'],
            expected_result: $row['expected_result'],
        );
    }
}
