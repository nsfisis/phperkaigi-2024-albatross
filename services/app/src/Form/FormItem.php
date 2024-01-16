<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Form;

final class FormItem
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $label = null,
        public readonly bool $isRequired = false,
        public readonly bool $isDisabled = false,
        public readonly string $extra = '',
    ) {
    }
}
