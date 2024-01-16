<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Forms;

use Nsfisis\Albatross\Database\Connection;
use Nsfisis\Albatross\Exceptions\EntityValidationException;
use Nsfisis\Albatross\Form\FormBase;
use Nsfisis\Albatross\Form\FormItem;
use Nsfisis\Albatross\Form\FormState;
use Nsfisis\Albatross\Form\FormSubmissionFailureException;
use Nsfisis\Albatross\Models\Answer;
use Nsfisis\Albatross\Models\Quiz;
use Nsfisis\Albatross\Models\User;
use Nsfisis\Albatross\Repositories\AnswerRepository;
use Nsfisis\Albatross\Repositories\TestcaseExecutionRepository;
use Slim\Interfaces\RouteParserInterface;

final class AnswerNewForm extends FormBase
{
    private ?Answer $answer = null;

    public function __construct(
        ?FormState $state,
        private readonly User $currentUser,
        private readonly Quiz $quiz,
        private readonly RouteParserInterface $routeParser,
        private readonly AnswerRepository $answerRepo,
        private readonly TestcaseExecutionRepository $testcaseExecutionRepo,
        private readonly Connection $conn,
    ) {
        if (!isset($state)) {
            $state = new FormState();
        }
        parent::__construct($state);
    }

    public function pageTitle(): string
    {
        return "問題 #{$this->quiz->quiz_id} - 提出";
    }

    public function redirectUrl(): string
    {
        $answer = $this->answer;
        assert(isset($answer));
        return $this->routeParser->urlFor(
            'answer_view',
            ['qslug' => $this->quiz->slug, 'anum' => "$answer->answer_number"],
        );
    }

    protected function submitLabel(): string
    {
        return '投稿';
    }

    /**
     * @return list<FormItem>
     */
    protected function items(): array
    {
        return [
            new FormItem(
                name: 'code',
                type: 'textarea',
                label: 'コード',
                isRequired: true,
                extra: 'rows="3" cols="80"',
            ),
        ];
    }

    /**
     * @return array{quiz: Quiz, is_closed: bool}
     */
    public function getRenderContext(): array
    {
        return [
            'quiz' => $this->quiz,
            'is_closed' => $this->quiz->isClosedToAnswer(),
        ];
    }

    public function submit(): void
    {
        if ($this->quiz->isClosedToAnswer()) {
            $this->state->setErrors(['general' => 'この問題の回答は締め切られました']);
            throw new FormSubmissionFailureException();
        }

        $code = $this->state->get('code') ?? '';

        try {
            $answer_id = $this->conn->transaction(function () use ($code) {
                $answer_id = $this->answerRepo->create(
                    $this->quiz->quiz_id,
                    $this->currentUser->user_id,
                    $code,
                );
                $this->testcaseExecutionRepo->enqueueForSingleAnswer(
                    $answer_id,
                    $this->quiz->quiz_id,
                );
                return $answer_id;
            });
        } catch (EntityValidationException $e) {
            $this->state->setErrors($e->toFormErrors());
            throw new FormSubmissionFailureException(previous: $e);
        }
        $answer = $this->answerRepo->findById($answer_id);
        assert(isset($answer));
        $this->answer = $answer;
    }
}
