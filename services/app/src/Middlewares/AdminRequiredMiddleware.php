<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Middlewares;

use LogicException;
use Nsfisis\Albatross\Models\User;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;

final class AdminRequiredMiddleware implements MiddlewareInterface
{
    private function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public static function create(App $app): self
    {
        return new self($app->getResponseFactory());
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $current_user = $request->getAttribute('current_user');
        if (!$current_user instanceof User) {
            throw new LogicException('The route that has this middleware must have the CurrentUserMiddleware before this one');
        }

        if (!$current_user->is_admin) {
            $response = $this->responseFactory->createResponse(403);
            $response->getBody()->write('Forbidden');
            return $response->withHeader('Content-Type', 'text/plain');
        }

        return $handler->handle($request);
    }
}
