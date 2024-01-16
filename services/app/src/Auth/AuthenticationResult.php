<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Auth;

enum AuthenticationResult
{
    case Success;
    case InvalidCredentials;
    case InvalidJson;
    case UnknownError;
}
