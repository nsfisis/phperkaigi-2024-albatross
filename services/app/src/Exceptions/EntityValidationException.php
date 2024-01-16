<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Exceptions;

use RuntimeException;
use Throwable;

final class EntityValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly array $errors = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, string>
     */
    public function toFormErrors(): array
    {
        if (count($this->errors) === 0) {
            return ['general' => $this->getMessage()];
        } else {
            return $this->errors;
        }
    }
}
