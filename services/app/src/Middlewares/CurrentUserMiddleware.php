<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Middlewares;

use Nsfisis\Albatross\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CurrentUserMiddleware implements MiddlewareInterface
{
    public function __construct(
        private UserRepository $userRepo,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $this->setCurrentUserAttribute($request);
        return $handler->handle($request);
    }

    private function setCurrentUserAttribute(ServerRequestInterface $request): ServerRequestInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return $request;
        }
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id === null) {
            return $request;
        }
        assert(is_int($user_id) || (is_string($user_id) && is_numeric($user_id)));
        $user_id = (int) $user_id;
        $user = $this->userRepo->findById($user_id);
        if ($user === null) {
            return $request;
        }
        return $request->withAttribute('current_user', $user);
    }
}
