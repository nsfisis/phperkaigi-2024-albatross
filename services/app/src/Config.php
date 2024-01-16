<?php

declare(strict_types=1);

namespace Nsfisis\Albatross;

final class Config
{
    public function __construct(
        public readonly string $basePath,
        public readonly string $siteName,
        public readonly bool $displayErrors,
        public readonly string $dbHost,
        public readonly int $dbPort,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $forteeApiEndpoint,
    ) {
    }

    public static function fromEnvVars(): self
    {
        return new self(
            basePath: self::getEnvVar('ALBATROSS_BASE_PATH'),
            siteName: self::getEnvVar('ALBATROSS_SITE_NAME'),
            displayErrors: self::getEnvVar('ALBATROSS_DISPLAY_ERRORS') === '1',
            dbHost: self::getEnvVar('ALBATROSS_DB_HOST'),
            dbPort: (int) self::getEnvVar('ALBATROSS_DB_PORT'),
            dbName: self::getEnvVar('ALBATROSS_DB_NAME'),
            dbUser: self::getEnvVar('ALBATROSS_DB_USER'),
            dbPassword: self::getEnvVar('ALBATROSS_DB_PASSWORD'),
            forteeApiEndpoint: self::getEnvVar('ALBATROSS_FORTEE_API_ENDPOINT'),
        );
    }

    private static function getEnvVar(string $name): string
    {
        $value = getenv($name);
        if ($value === false) {
            throw new \RuntimeException("Environment variable \${$name} not set");
        }
        return $value;
    }
}
