<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Repositories;

use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Models\ExecutionStatus;
use Nsfisis\Albatross\Models\TestcaseExecution;

final class TestcaseExecutionRepository
{
    private const TESTCASE_EXECUTION_FIELDS = [
        'testcase_execution_id',
        'testcase_id',
        'answer_id',
        'status',
        'stdout',
        'stderr',
    ];

    public function __construct(
        private readonly Connection $conn,
    ) {
    }

    public function findByAnswerIdAndTestcaseExecutionId(
        int $answer_id,
        int $testcase_execution_id,
    ): ?TestcaseExecution {
        $result = $this->conn
            ->query()
            ->select('testcase_executions')
            ->fields(self::TESTCASE_EXECUTION_FIELDS)
            ->where('answer_id = :answer_id AND testcase_execution_id = :testcase_execution_id')
            ->first()
            ->execute([
                'answer_id' => $answer_id,
                'testcase_execution_id' => $testcase_execution_id,
            ]);
        return isset($result) ? $this->mapRawRowToTestcaseExecution($result) : null;
    }

    /**
     * @return TestcaseExecution[]
     */
    public function listByQuizId(int $quiz_id): array
    {
        $result = $this->conn
            ->query()
            ->select('testcase_executions')
            ->fields(self::TESTCASE_EXECUTION_FIELDS)
            ->where('quiz_id = :quiz_id')
            ->orderBy([['testcase_execution_id', 'ASC']])
            ->execute(['quiz_id' => $quiz_id]);
        return array_map($this->mapRawRowToTestcaseExecution(...), $result);
    }

    /**
     * @return TestcaseExecution[]
     */
    public function listByAnswerId(int $answer_id): array
    {
        $result = $this->conn
            ->query()
            ->select('testcase_executions')
            ->fields(self::TESTCASE_EXECUTION_FIELDS)
            ->where('answer_id = :answer_id')
            ->orderBy([['testcase_execution_id', 'ASC']])
            ->execute(['answer_id' => $answer_id]);
        return array_map($this->mapRawRowToTestcaseExecution(...), $result);
    }

    /**
     * @return array<int, ExecutionStatus>
     */
    public function getStatuses(int $answer_id): array
    {
        $result = $this->conn
            ->query()
            ->select('testcase_executions')
            ->fields(['testcase_execution_id', 'status'])
            ->where('answer_id = :answer_id')
            ->orderBy([['testcase_execution_id', 'ASC']])
            ->execute(['answer_id' => $answer_id]);
        return array_combine(
            array_map(fn ($row) => (int)$row['testcase_execution_id'], $result),
            array_map(fn ($row) => ExecutionStatus::fromInt((int)$row['status']), $result),
        );
    }

    public function tryGetNextPendingTestcaseExecution(): ?TestcaseExecution
    {
        return $this->conn->transaction(function () {
            $pending_ex_result = $this->conn
                ->query()
                ->select('testcase_executions')
                ->fields(self::TESTCASE_EXECUTION_FIELDS)
                ->where('status = :status')
                ->orderBy([['testcase_execution_id', 'ASC']])
                ->first()
                ->execute(['status' => ExecutionStatus::Pending->toInt()]);
            $pending_ex = isset($pending_ex_result) ? $this->mapRawRowToTestcaseExecution($pending_ex_result) : null;
            if ($pending_ex === null) {
                return null;
            }
            $this->conn
                ->query()
                ->update('testcase_executions')
                ->set(['status' => ExecutionStatus::Running->toInt()])
                ->where('testcase_execution_id = :testcase_execution_id')
                ->execute(['testcase_execution_id' => $pending_ex->testcase_execution_id]);
            return new TestcaseExecution(
                testcase_execution_id: $pending_ex->testcase_execution_id,
                testcase_id: $pending_ex->testcase_id,
                answer_id: $pending_ex->answer_id,
                status: ExecutionStatus::Running,
                stdout: null,
                stderr: null,
            );
        });
    }

    public function create(
        int $testcase_id,
        int $answer_id,
    ): int {
        $ex = TestcaseExecution::create(
            testcase_id: $testcase_id,
            answer_id: $answer_id,
        );

        $values = [
            'testcase_id' => $ex->testcase_id,
            'answer_id' => $ex->answer_id,
            'status' => $ex->status->toInt(),
        ];

        return $this->conn
            ->query()
            ->insert('testcase_executions')
            ->values([
                'testcase_id' => $ex->testcase_id,
                'answer_id' => $ex->answer_id,
                'status' => $ex->status->toInt(),
            ])
            ->execute();
    }

    public function enqueueForAllAnswers(
        int $quiz_id,
        int $testcase_id,
    ): void {
        $this->conn
            ->query()
            ->insertFromSelect('testcase_executions')
            ->fields(['testcase_id', 'answer_id', 'status'])
            ->from($this->conn
                ->query()
                ->select('answers')
                ->fields([':testcase_id', 'answer_id', ':status'])
                ->where('quiz_id = :quiz_id'))
            ->execute([
                'quiz_id' => $quiz_id,
                'testcase_id' => $testcase_id,
                'status' => ExecutionStatus::Pending->toInt(),
            ]);
    }

    public function enqueueForSingleAnswer(
        int $answer_id,
        int $quiz_id,
    ): void {
        $this->conn
            ->query()
            ->insertFromSelect('testcase_executions')
            ->fields(['testcase_id', 'answer_id', 'status'])
            ->from($this->conn
                ->query()
                ->select('testcases')
                ->fields(['testcase_id', ':answer_id', ':status'])
                ->where('quiz_id = :quiz_id'))
            ->execute([
                'quiz_id' => $quiz_id,
                'answer_id' => $answer_id,
                'status' => ExecutionStatus::Pending->toInt(),
            ]);
    }

    public function markAllAsPendingByQuizId(
        int $quiz_id,
    ): void {
        $this->conn
            ->query()
            ->update('testcase_executions')
            ->set([
                'status' => ExecutionStatus::Pending->toInt(),
                'stdout' => '',
                'stderr' => '',
            ])
            ->where('answer_id IN (SELECT answer_id FROM answers WHERE quiz_id = :quiz_id)')
            ->execute(['quiz_id' => $quiz_id]);
    }

    public function markAllAsPendingByAnswerId(
        int $answer_id,
    ): void {
        $this->conn
            ->query()
            ->update('testcase_executions')
            ->set([
                'status' => ExecutionStatus::Pending->toInt(),
                'stdout' => '',
                'stderr' => '',
            ])
            ->where('answer_id = :answer_id')
            ->execute(['answer_id' => $answer_id]);
    }

    public function markAllAsPendingByTestcaseId(
        int $testcase_id,
    ): void {
        $this->conn
            ->query()
            ->update('testcase_executions')
            ->set([
                'status' => ExecutionStatus::Pending->toInt(),
                'stdout' => '',
                'stderr' => '',
            ])
            ->where('testcase_id = :testcase_id')
            ->execute(['testcase_id' => $testcase_id]);
    }

    public function markAsPending(int $testcase_execution_id): void
    {
        $this->update($testcase_execution_id, ExecutionStatus::Pending, '', '');
    }

    public function update(
        int $testcase_execution_id,
        ExecutionStatus $status,
        ?string $stdout,
        ?string $stderr,
    ): void {
        $values = [
            'status' => $status->toInt(),
        ];
        if ($stdout !== null) {
            $values['stdout'] = $stdout;
        }
        if ($stderr !== null) {
            $values['stderr'] = $stderr;
        }

        $this->conn
            ->query()
            ->update('testcase_executions')
            ->set($values)
            ->where('testcase_execution_id = :testcase_execution_id')
            ->execute(['testcase_execution_id' => $testcase_execution_id]);
    }

    public function deleteByTestcaseId(int $testcase_id): void
    {
        $this->conn
            ->query()
            ->delete('testcase_executions')
            ->where('testcase_id = :testcase_id')
            ->execute(['testcase_id' => $testcase_id]);
    }

    /**
     * @param array<string, string> $row
     */
    private function mapRawRowToTestcaseExecution(array $row): TestcaseExecution
    {
        assert(isset($row['testcase_execution_id']));
        assert(isset($row['testcase_id']));
        assert(isset($row['answer_id']));
        assert(isset($row['status']));

        $testcase_execution_id = (int) $row['testcase_execution_id'];
        $testcase_id = (int) $row['testcase_id'];
        $answer_id = (int) $row['answer_id'];

        return new TestcaseExecution(
            testcase_execution_id: $testcase_execution_id,
            testcase_id: $testcase_id,
            answer_id: $answer_id,
            status: ExecutionStatus::fromInt((int)$row['status']),
            stdout: $row['stdout'] ?? null,
            stderr: $row['stderr'] ?? null,
        );
    }
}
