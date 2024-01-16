<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Auth;

final class ForteeAuth implements AuthProviderInterface
{
    public function __construct(
        private string $apiEndpoint,
    ) {
    }

    public function login(string $username, string $password): AuthenticationResult
    {
        $query_params = [
            'username' => $username,
            'password' => $password,
        ];
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'follow_location' => 0,
                'header' => [
                    'Content-type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                'timeout' => 5.0,
                'content' => http_build_query($query_params),
            ],
        ]);
        $result = file_get_contents(
            $this->apiEndpoint . '/api/user/login',
            context: $context,
        );
        if ($result === false) {
            return AuthenticationResult::UnknownError;
        }
        $result = json_decode($result, true);
        if (!is_array($result)) {
            return AuthenticationResult::InvalidJson;
        }
        $ok = ($result['loggedIn'] ?? null) === true;
        if ($ok) {
            return AuthenticationResult::Success;
        } else {
            return AuthenticationResult::InvalidCredentials;
        }
    }
}
