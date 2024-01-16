<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Form;

use RuntimeException;
use Throwable;

final class FormSubmissionFailureException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 400,
        Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
