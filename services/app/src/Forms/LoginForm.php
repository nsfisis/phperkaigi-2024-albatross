<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Forms;

use Nsfisis\Albatross\Auth\AuthenticationResult;
use Nsfisis\Albatross\Auth\AuthProviderInterface;
use Nsfisis\Albatross\Exceptions\EntityValidationException;
use Nsfisis\Albatross\Form\FormBase;
use Nsfisis\Albatross\Form\FormItem;
use Nsfisis\Albatross\Form\FormState;
use Nsfisis\Albatross\Form\FormSubmissionFailureException;
use Nsfisis\Albatross\Repositories\UserRepository;
use Slim\Interfaces\RouteParserInterface;

final class LoginForm extends FormBase
{
    public function __construct(
        ?FormState $state,
        private readonly ?string $destination,
        private readonly RouteParserInterface $routeParser,
        private readonly UserRepository $userRepo,
        private readonly AuthProviderInterface $authProvider,
    ) {
        if (!isset($state)) {
            $state = new FormState();
        }
        parent::__construct($state);
    }

    public function pageTitle(): string
    {
        return 'ログイン';
    }

    public function redirectUrl(): string
    {
        return $this->destination ?? $this->routeParser->urlFor('quiz_list');
    }

    protected function submitLabel(): string
    {
        return 'ログイン';
    }

    /**
     * @return list<FormItem>
     */
    protected function items(): array
    {
        return [
            new FormItem(
                name: 'username',
                type: 'text',
                label: 'ユーザ名',
                isRequired: true,
            ),
            new FormItem(
                name: 'password',
                type: 'password',
                label: 'パスワード',
                isRequired: true,
            ),
        ];
    }

    public function submit(): void
    {
        $username = $this->state->get('username') ?? '';
        $password = $this->state->get('password') ?? '';

        $this->validate($username, $password);

        $authResult = $this->authProvider->login($username, $password);
        if ($authResult === AuthenticationResult::InvalidCredentials) {
            $this->state->setErrors(['general' => 'ユーザ名またはパスワードが異なります']);
            throw new FormSubmissionFailureException(code: 403);
        } elseif ($authResult === AuthenticationResult::InvalidJson || $authResult === AuthenticationResult::UnknownError) {
            throw new FormSubmissionFailureException(code: 500);
        } else {
            $user = $this->userRepo->findByUsername($username);
            if ($user === null) {
                try {
                    $user_id = $this->userRepo->create(
                        $username,
                        is_admin: true, // TODO
                    );
                } catch (EntityValidationException $e) {
                    $this->state->setErrors($e->toFormErrors());
                    throw new FormSubmissionFailureException(previous: $e);
                }
                $_SESSION['user_id'] = $user_id;
            } else {
                $_SESSION['user_id'] = $user->user_id;
            }
        }
    }

    private function validate(string $username, string $password): void
    {
        $errors = [];
        if (strlen($username) < 1) {
            $errors['username'] = 'ユーザ名は必須です';
        }

        if (strlen($password) < 1) {
            $errors['password'] = 'パスワードは必須です';
        }

        if (count($errors) > 0) {
            $this->state->setErrors($errors);
            throw new FormSubmissionFailureException(code: 400);
        }
    }
}
