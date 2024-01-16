<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Forms;

use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Exceptions\EntityValidationException;
use Nsfisis\Albatross\Form\FormBase;
use Nsfisis\Albatross\Form\FormItem;
use Nsfisis\Albatross\Form\FormState;
use Nsfisis\Albatross\Form\FormSubmissionFailureException;
use Nsfisis\Albatross\Models\Quiz;
use Nsfisis\Albatross\Models\Testcase;
use Nsfisis\Albatross\Repositories\AnswerRepository;
use Nsfisis\Albatross\Repositories\TestcaseExecutionRepository;
use Nsfisis\Albatross\Repositories\TestcaseRepository;
use Slim\Interfaces\RouteParserInterface;

final class AdminTestcaseEditForm extends FormBase
{
    public function __construct(
        ?FormState $state,
        private readonly Testcase $testcase,
        private readonly Quiz $quiz,
        private readonly RouteParserInterface $routeParser,
        private readonly AnswerRepository $answerRepo,
        private readonly TestcaseRepository $testcaseRepo,
        private readonly TestcaseExecutionRepository $testcaseExecutionRepo,
        private readonly Connection $conn,
    ) {
        if (!isset($state)) {
            $state = new FormState([
                'input' => $testcase->input,
                'expected_result' => $testcase->expected_result,
            ]);
        }
        parent::__construct($state);
    }

    public function pageTitle(): string
    {
        return "管理画面 - 問題 #{$this->quiz->quiz_id} - テストケース #{$this->testcase->testcase_id} - 編集";
    }

    public function redirectUrl(): string
    {
        return $this->routeParser->urlFor('admin_testcase_list', ['qslug' => $this->quiz->slug]);
    }

    protected function submitLabel(): string
    {
        return '保存';
    }

    /**
     * @return list<FormItem>
     */
    protected function items(): array
    {
        return [
            new FormItem(
                name: 'input',
                type: 'textarea',
                label: '標準入力',
                extra: 'rows="10" cols="80"',
            ),
            new FormItem(
                name: 'expected_result',
                type: 'textarea',
                label: '期待する出力',
                isRequired: true,
                extra: 'rows="10" cols="80"',
            ),
        ];
    }

    /**
     * @return array{testcase: Testcase, quiz: Quiz}
     */
    public function getRenderContext(): array
    {
        return [
            'testcase' => $this->testcase,
            'quiz' => $this->quiz,
        ];
    }

    public function submit(): void
    {
        $input = $this->state->get('input') ?? '';
        $expected_result = $this->state->get('expected_result') ?? '';

        $errors = [];
        if ($expected_result === '') {
            $errors['expected_result'] = '期待する出力は必須です';
        }
        if (0 < count($errors)) {
            $this->state->setErrors($errors);
            throw new FormSubmissionFailureException();
        }

        try {
            $this->conn->transaction(function () use ($input, $expected_result): void {
                $quiz_id = $this->quiz->quiz_id;

                $this->testcaseRepo->update(
                    testcase_id: $this->testcase->testcase_id,
                    input: $input,
                    expected_result: $expected_result,
                );
                $this->answerRepo->markAllAsPending($quiz_id);
                $this->testcaseExecutionRepo->markAllAsPendingByTestcaseId(
                    testcase_id: $this->testcase->testcase_id,
                );
            });
        } catch (EntityValidationException $e) {
            $this->state->setErrors($e->toFormErrors());
            throw new FormSubmissionFailureException(previous: $e);
        }
    }
}
