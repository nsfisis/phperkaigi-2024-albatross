<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Forms;

use DateTimeImmutable;
use DateTimeZone;
use Nsfisis\Albatross\Exceptions\EntityValidationException;
use Nsfisis\Albatross\Form\FormBase;
use Nsfisis\Albatross\Form\FormItem;
use Nsfisis\Albatross\Form\FormState;
use Nsfisis\Albatross\Form\FormSubmissionFailureException;
use Nsfisis\Albatross\Models\Quiz;
use Nsfisis\Albatross\Repositories\QuizRepository;
use Slim\Interfaces\RouteParserInterface;

final class AdminQuizEditForm extends FormBase
{
    public function __construct(
        ?FormState $state,
        private readonly Quiz $quiz,
        private readonly RouteParserInterface $routeParser,
        private readonly QuizRepository $quizRepo,
    ) {
        if (!isset($state)) {
            $state = new FormState([
                'title' => $quiz->title,
                'slug' => $quiz->slug,
                'description' => $quiz->description,
                'example_code' => $quiz->example_code,
                'birdie_code_size' => (string)$quiz->birdie_code_size,
                'started_at' => $quiz->started_at->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d\TH:i'),
                'ranking_hidden_at' => $quiz->ranking_hidden_at->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d\TH:i'),
                'finished_at' => $quiz->finished_at->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d\TH:i'),
            ]);
        }
        parent::__construct($state);
    }

    public function pageTitle(): string
    {
        return "管理画面 - 問題 #{$this->quiz->quiz_id} - 編集";
    }

    public function redirectUrl(): string
    {
        return $this->routeParser->urlFor('admin_quiz_list');
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
                name: 'title',
                type: 'text',
                label: 'タイトル',
                isRequired: true,
            ),
            new FormItem(
                name: 'slug',
                type: 'text',
                label: 'スラグ',
                isRequired: true,
                isDisabled: true,
            ),
            new FormItem(
                name: 'description',
                type: 'textarea',
                label: '説明',
                isRequired: true,
                extra: 'rows=3 cols=80',
            ),
            new FormItem(
                name: 'example_code',
                type: 'textarea',
                label: '実装例',
                isRequired: true,
                extra: 'rows=10 cols=80',
            ),
            new FormItem(
                name: 'birdie_code_size',
                type: 'text',
                label: 'バーディになるコードサイズ (byte)',
            ),
            new FormItem(
                name: 'started_at',
                type: 'datetime-local',
                label: '開始日時 (JST)',
                isRequired: true,
            ),
            new FormItem(
                name: 'ranking_hidden_at',
                type: 'datetime-local',
                label: 'ランキングが非表示になる日時 (JST)',
                isRequired: true,
            ),
            new FormItem(
                name: 'finished_at',
                type: 'datetime-local',
                label: '終了日時 (JST)',
                isRequired: true,
            ),
        ];
    }

    /**
     * @return array{quiz: Quiz}
     */
    public function getRenderContext(): array
    {
        return [
            'quiz' => $this->quiz,
        ];
    }

    public function submit(): void
    {
        $title = $this->state->get('title') ?? '';
        $slug = $this->state->get('slug') ?? '';
        $description = $this->state->get('description') ?? '';
        $example_code = $this->state->get('example_code') ?? '';
        $birdie_code_size = $this->state->get('birdie_code_size') ?? '';
        $started_at = $this->state->get('started_at') ?? '';
        $ranking_hidden_at = $this->state->get('ranking_hidden_at') ?? '';
        $finished_at = $this->state->get('finished_at') ?? '';

        $errors = [];
        if ($birdie_code_size !== '' && !is_numeric($birdie_code_size)) {
            $errors['birdie_code_size'] = '数値を入力してください';
            $birdie_code_size = ''; // dummy
        }
        if ($started_at === '') {
            $errors['started_at'] = '開始日時は必須です';
        } else {
            $started_at = DateTimeImmutable::createFromFormat(
                'Y-m-d\TH:i',
                $started_at,
                new DateTimeZone('Asia/Tokyo'),
            );
            if ($started_at === false) {
                $errors['started_at'] = '開始日時の形式が不正です';
            } else {
                $started_at = $started_at->setTimezone(new DateTimeZone('UTC'));
            }
        }
        if (!$started_at instanceof DateTimeImmutable) {
            $started_at = new DateTimeImmutable('now', new DateTimeZone('UTC')); // dummy
        }
        if ($ranking_hidden_at === '') {
            $errors['ranking_hidden_at'] = 'ランキングが非表示になる日時は必須です';
        } else {
            $ranking_hidden_at = DateTimeImmutable::createFromFormat(
                'Y-m-d\TH:i',
                $ranking_hidden_at,
                new DateTimeZone('Asia/Tokyo'),
            );
            if ($ranking_hidden_at === false) {
                $errors['ranking_hidden_at'] = 'ランキングが非表示になる日時の形式が不正です';
            } else {
                $ranking_hidden_at = $ranking_hidden_at->setTimezone(new DateTimeZone('UTC'));
            }
        }
        if (!$ranking_hidden_at instanceof DateTimeImmutable) {
            $ranking_hidden_at = new DateTimeImmutable('now', new DateTimeZone('UTC')); // dummy
        }
        if ($finished_at === '') {
            $errors['finished_at'] = '終了日時は必須です';
        } else {
            $finished_at = DateTimeImmutable::createFromFormat(
                'Y-m-d\TH:i',
                $finished_at,
                new DateTimeZone('Asia/Tokyo'),
            );
            if ($finished_at === false) {
                $errors['finished_at'] = '終了日時の形式が不正です';
            } else {
                $finished_at = $finished_at->setTimezone(new DateTimeZone('UTC'));
            }
        }
        if (!$finished_at instanceof DateTimeImmutable) {
            $finished_at = new DateTimeImmutable('now', new DateTimeZone('UTC')); // dummy
        }

        if (0 < count($errors)) {
            $this->state->setErrors($errors);
            throw new FormSubmissionFailureException();
        }

        try {
            $this->quizRepo->update(
                $this->quiz->quiz_id,
                $title,
                $description,
                $example_code,
                $birdie_code_size === '' ? null : (int)$birdie_code_size,
                $started_at,
                $ranking_hidden_at,
                $finished_at,
            );
        } catch (EntityValidationException $e) {
            $this->state->setErrors($e->toFormErrors());
            throw new FormSubmissionFailureException(previous: $e);
        }
    }
}
