<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Twig;

use Slim\Csrf\Guard;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class CsrfExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly Guard $csrf)
    {
    }

    /**
     * @return array{csrf: array{name_key: string, name: string, value_key: string, value: string}}
     */
    public function getGlobals(): array
    {
        $csrf_name_key = $this->csrf->getTokenNameKey();
        $csrf_name = $this->csrf->getTokenName();
        assert(
            isset($csrf_name),
            'It must be present here because the access is denied by Csrf\Guard middleware if absent.',
        );

        $csrf_value_key = $this->csrf->getTokenValueKey();
        $csrf_value = $this->csrf->getTokenValue();
        assert(
            isset($csrf_value),
            'It must be present here because the access is denied by Csrf\Guard middleware if absent.',
        );

        return [
            'csrf' => [
                'name_key' => $csrf_name_key,
                'name' => $csrf_name,
                'value_key' => $csrf_value_key,
                'value' => $csrf_value
            ]
        ];
    }
}
