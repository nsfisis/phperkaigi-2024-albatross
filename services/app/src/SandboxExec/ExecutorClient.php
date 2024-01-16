<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\SandboxExec;

use Nsfisis\Albatross\Models\ExecutionStatus;

final class ExecutorClient
{
    public function __construct(
        private readonly string $apiEndpoint,
        private readonly int $timeoutMsec,
    ) {
    }

    public function execute(
        string $code,
        string $input,
    ): ExecutionResult {
        $bodyJson = json_encode([
            'code' => $code,
            'input' => $input,
            'timeout' => $this->timeoutMsec,
        ]);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'follow_location' => 0,
                'header' => [
                    'Content-type: application/json',
                    'Accept: application/json',
                ],
                'content' => $bodyJson,
                'timeout' => ($this->timeoutMsec + 1000) / 1000,
            ],
        ]);
        $result = file_get_contents(
            $this->apiEndpoint . '/exec',
            context: $context,
        );
        if ($result === false) {
            return new ExecutionResult(
                status: ExecutionStatus::IE,
                stdout: '',
                stderr: 'Failed to connect to the executor service',
            );
        }
        $json = json_decode($result, true);
        if ($json === null) {
            return new ExecutionResult(
                status: ExecutionStatus::IE,
                stdout: '',
                stderr: 'Failed to parse the response from the executor service: invalid JSON',
            );
        }

        if (!is_array($json)) {
            return new ExecutionResult(
                status: ExecutionStatus::IE,
                stdout: '',
                stderr: 'Failed to parse the response from the executor service: root object is not an array',
            );
        }
        if (!isset($json['status']) || !is_string($json['status'])) {
            return new ExecutionResult(
                status: ExecutionStatus::IE,
                stdout: '',
                stderr: 'Failed to parse the response from the executor service: "status" is not a string',
            );
        }
        if (!isset($json['stdout']) || !is_string($json['stdout'])) {
            return new ExecutionResult(
                status: ExecutionStatus::IE,
                stdout: '',
                stderr: 'Failed to parse the response from the executor service: "stdout" is not a string',
            );
        }
        if (!isset($json['stderr']) || !is_string($json['stderr'])) {
            return new ExecutionResult(
                status: ExecutionStatus::IE,
                stdout: '',
                stderr: 'Failed to parse the response from the executor service: "stderr" is not a string',
            );
        }

        return new ExecutionResult(
            status: ExecutionStatus::fromString($json['status']),
            stdout: $json['stdout'],
            stderr: $json['stderr'],
        );
    }
}
