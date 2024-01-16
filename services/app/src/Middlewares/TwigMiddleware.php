<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Middlewares;

use Nsfisis\Albatross\Twig\CsrfExtension;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware as SlimTwigMiddleware;

final class TwigMiddleware implements MiddlewareInterface
{
    private readonly SlimTwigMiddleware $wrapped;

    public function __construct(App $app, CsrfExtension $csrf_extension)
    {
        // TODO:
        // $twig = Twig::create(__DIR__ . '/../../templates', ['cache' => __DIR__ . '/../../twig-cache']);
        $twig = Twig::create(__DIR__ . '/../../templates', ['cache' => false]);
        $twig->addExtension($csrf_extension);
        $this->wrapped = SlimTwigMiddleware::create($app, $twig);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->wrapped->process($request, $handler);
    }
}
