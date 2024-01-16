<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Form;

use Psr\Http\Message\ServerRequestInterface;

final class FormState
{
    /**
     * @var array<string, string>
     */
    private array $errors = [];

    /**
     * @param array<string, string> $params
     */
    public function __construct(private readonly array $params = [])
    {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        return new self((array)$request->getParsedBody());
    }

    /**
     * @return array<string, string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function get(string $key): ?string
    {
        $value = $this->params[$key] ?? null;
        if (isset($value)) {
            return $key === 'password' ? $value : trim($value);
        } else {
            return null;
        }
    }

    /**
     * @param array<string, string> $errors
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
