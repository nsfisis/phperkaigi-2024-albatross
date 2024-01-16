<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Auth;

interface AuthProviderInterface
{
    public function login(string $username, string $password): AuthenticationResult;
}
