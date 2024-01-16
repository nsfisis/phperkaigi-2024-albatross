<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CacheControlPrivateMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($request->getAttribute('current_user') !== null) {
            return $response->withHeader('Cache-Control', 'private');
        } else {
            return $response;
        }
    }
}
