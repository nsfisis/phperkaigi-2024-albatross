<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Form;

abstract class FormBase
{
    public function __construct(
        protected readonly FormState $state,
    ) {
    }

    /**
     * @return array{action: ?string, submit_label: string, items: list<FormItem>, state: array<string, string>, errors: array<string, string>}
     */
    public function toTemplateVars(): array
    {
        return [
            'action' => $this->action(),
            'submit_label' => $this->submitLabel(),
            'items' => $this->items(),
            'state' => $this->state->getParams(),
            'errors' => $this->state->getErrors(),
        ];
    }

    abstract public function pageTitle(): string;

    abstract public function redirectUrl(): string;

    protected function action(): ?string
    {
        return null;
    }

    abstract protected function submitLabel(): string;

    /**
     * @return list<FormItem>
     */
    abstract protected function items(): array;

    /**
     * @return array<string, mixed>
     */
    public function getRenderContext(): array
    {
        return [];
    }

    abstract public function submit(): void;
}
