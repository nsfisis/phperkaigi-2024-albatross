<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Middlewares;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;

final class AuthRequiredMiddleware implements MiddlewareInterface
{
    private function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $loginPath,
    ) {
    }

    public static function create(
        App $app,
        string $loginRouteName,
    ): self {
        return new self(
            $app->getResponseFactory(),
            $app->getRouteCollector()->getRouteParser()->urlFor($loginRouteName),
        );
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $current_user = $request->getAttribute('current_user');
        if ($current_user === null) {
            return $this->responseFactory
                ->createResponse(302)
                ->withHeader('Location', $this->loginPath . "?to=" . urlencode($request->getUri()->getPath()));
        }

        return $handler->handle($request);
    }
}
