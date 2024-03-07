<?php

declare(strict_types=1);

namespace Nsfisis\Albatross;

use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Models\AggregatedExecutionStatus;
use Nsfisis\Albatross\Models\Answer;
use Nsfisis\Albatross\Models\ExecutionStatus;
use Nsfisis\Albatross\Models\TestcaseExecution;
use Nsfisis\Albatross\Repositories\AnswerRepository;
use Nsfisis\Albatross\Repositories\TestcaseExecutionRepository;
use Nsfisis\Albatross\Repositories\TestcaseRepository;
use Nsfisis\Albatross\SandboxExec\ExecutionResult;
use Nsfisis\Albatross\SandboxExec\ExecutorClient;

final class JobWorker
{
    private Connection $conn;
    private AnswerRepository $answerRepo;
    private TestcaseRepository $testcaseRepo;
    private TestcaseExecutionRepository $testcaseExecutionRepo;
    private ExecutorClient $executorClient;

    public function __construct(
        Config $config,
    ) {
        $this->conn = new Connection(
            driver: 'pgsql',
            host: $config->dbHost,
            port: $config->dbPort,
            name: $config->dbName,
            user: $config->dbUser,
            password: $config->dbPassword,
            max_tries: 10,
            sleep_sec: 3,
        );
        $this->answerRepo = new AnswerRepository($this->conn);
        $this->testcaseRepo = new TestcaseRepository($this->conn);
        $this->testcaseExecutionRepo = new TestcaseExecutionRepository($this->conn);

        $this->executorClient = new ExecutorClient(
            'http://albatross-sandbox-exec:8888',
            timeoutMsec: 10 * 1000,
        );
    }

    public function run(): void
    {
        // @phpstan-ignore-next-line
        while (true) {
            $task = $this->tryGetNextTask();
            if (isset($task)) {
                $this->process($task);
            } else {
                $this->sleep();
            }
        }
    }

    private function process(Answer|TestcaseExecution $task): void
    {
        if ($task instanceof Answer) {
            $this->updateAnswerAggregatedExecutionStatus($task, null);
        } else {
            $this->executeTestcase($task);
        }
    }

    private function tryGetNextTask(): Answer|TestcaseExecution|null
    {
        $answer = $this->answerRepo->tryGetNextUpdateNeededAnswer();
        if ($answer !== null) {
            return $answer;
        }
        $ex = $this->testcaseExecutionRepo->tryGetNextPendingTestcaseExecution();
        return $ex;
    }

    /**
     * @param ?array{int, ExecutionStatus} $statusUpdate
     */
    private function updateAnswerAggregatedExecutionStatus(
        Answer $answer,
        ?array $statusUpdate,
    ): void {
        $statuses = $this->testcaseExecutionRepo->getStatuses($answer->answer_id);
        if ($statusUpdate !== null) {
            [$updatedExId, $newStatus] = $statusUpdate;
            $statuses[$updatedExId] = $newStatus;
        }

        $pendingOrRunningCount = 0;
        $acCount = 0;
        foreach ($statuses as $status) {
            match ($status) {
                ExecutionStatus::AC => $acCount++,
                ExecutionStatus::Pending, ExecutionStatus::Running => $pendingOrRunningCount++,
                default => null,
            };
        }

        $aggregatedStatus = match (true) {
            $acCount === count($statuses) => AggregatedExecutionStatus::OK,
            $pendingOrRunningCount !== 0 => AggregatedExecutionStatus::Pending,
            default => AggregatedExecutionStatus::Failed,
        };
        $this->answerRepo->updateExecutionStatus($answer->answer_id, $aggregatedStatus);
    }

    private function executeTestcase(TestcaseExecution $ex): void
    {
        $answer = $this->answerRepo->findById($ex->answer_id);
        if ($answer === null) {
            $this->testcaseExecutionRepo->update(
                $ex->testcase_execution_id,
                ExecutionStatus::IE,
                '',
                'Failed to get the corresponding answer',
            );
            return;
        }

        $testcase = $this->testcaseRepo->findByQuizIdAndTestcaseId(
            $answer->quiz_id,
            $ex->testcase_id,
        );
        if ($testcase === null) {
            $this->testcaseExecutionRepo->update(
                $ex->testcase_execution_id,
                ExecutionStatus::IE,
                '',
                'Failed to get the corresponding testcase',
            );
            return;
        }

        $result = $this->executeCode($answer->code, $testcase->input);
        if ($result->status === ExecutionStatus::AC) {
            $status = self::verifyResult($testcase->expected_result, $result->stdout)
                ? ExecutionStatus::AC
                : ExecutionStatus::WA;
        } else {
            $status = $result->status;
        }

        $this->conn->transaction(function () use ($ex, $status, $result, $answer) {
            $this->updateAnswerAggregatedExecutionStatus(
                $answer,
                [$ex->testcase_execution_id, $status],
            );
            $this->testcaseExecutionRepo->update(
                $ex->testcase_execution_id,
                $status,
                $result->stdout,
                $result->stderr,
            );
        });
    }

    private function executeCode(string $code, string $input): ExecutionResult
    {
        return $this->executorClient->execute(
            code: Answer::normalizeCode($code),
            input: self::normalizeInput($input),
        );
    }

    private function sleep(): void
    {
        sleep(1);
    }

    private static function verifyResult(string $expected, string $actual): bool
    {
        return self::normalizeOutput($expected) === self::normalizeOutput($actual);
    }

    private static function normalizeInput(string $s): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $s)) . "\n";
    }

    private static function normalizeOutput(string $s): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $s));
    }
}
