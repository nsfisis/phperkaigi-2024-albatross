<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Forms;

use Nsfisis\Albatross\Form\FormBase;
use Nsfisis\Albatross\Form\FormItem;
use Nsfisis\Albatross\Form\FormState;
use Nsfisis\Albatross\Models\User;
use Nsfisis\Albatross\Repositories\UserRepository;
use Slim\Interfaces\RouteParserInterface;

final class AdminUserEditForm extends FormBase
{
    public function __construct(
        ?FormState $state,
        private readonly User $user,
        private readonly RouteParserInterface $routeParser,
        private readonly UserRepository $userRepo,
    ) {
        if (!isset($state)) {
            $state = new FormState([
                'username' => $user->username,
                'is_admin' => $user->is_admin ? 'on' : '',
            ]);
        }
        parent::__construct($state);
    }

    public function pageTitle(): string
    {
        return "管理画面 - ユーザ {$this->user->username} 編集";
    }

    public function redirectUrl(): string
    {
        return $this->routeParser->urlFor('admin_user_list');
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
                name: 'username',
                type: 'text',
                label: 'ユーザ名',
                isDisabled: true,
            ),
            new FormItem(
                name: 'is_admin',
                type: 'checkbox',
                label: '管理者',
            ),
        ];
    }

    /**
     * @return array{user: User}
     */
    public function getRenderContext(): array
    {
        return [
            'user' => $this->user,
        ];
    }

    public function submit(): void
    {
        $is_admin = $this->state->get('is_admin') === 'on';

        $this->userRepo->update(
            $this->user->user_id,
            is_admin: $is_admin,
        );
    }
}
