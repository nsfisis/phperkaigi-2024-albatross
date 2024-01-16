<?php

declare(strict_types=1);

// phpcs:disable
/*
 * Based on https://github.com/middlewares/trailing-slash
 *
 * Original license:
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2019
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
// phpcs:enable

namespace Nsfisis\Albatross\Middlewares;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TrailingSlash implements MiddlewareInterface
{
    /**
     * @var bool Add or remove the slash
     */
    private $trailingSlash;

    /**
     * @var ResponseFactoryInterface|null
     */
    private $responseFactory;

    /**
     * Configure whether add or remove the slash.
     */
    public function __construct(bool $trailingSlash = false)
    {
        $this->trailingSlash = $trailingSlash;
    }

    /**
     * Whether returns a 301 response to the new path.
     */
    public function redirect(ResponseFactoryInterface $responseFactory): self
    {
        $this->responseFactory = $responseFactory;

        return $this;
    }

    /**
     * Process a request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $this->normalize($uri->getPath());

        if (isset($this->responseFactory) && ($uri->getPath() !== $path)) {
            return $this->responseFactory->createResponse(301)
                ->withHeader('Location', $path);
        }

        return $handler->handle($request->withUri($uri->withPath($path)));
    }

    /**
     * Normalize the trailing slash.
     */
    private function normalize(string $path): string
    {
        if ($path === '') {
            return '/';
        }
        if (str_contains($path, '/api/')) {
            return $path;
        }

        if (strlen($path) > 1) {
            if ($this->trailingSlash) {
                if (substr($path, -1) !== '/' && pathinfo($path, PATHINFO_EXTENSION) === '') {
                    return $path . '/';
                }
            } else {
                return rtrim($path, '/');
            }
        }

        return $path;
    }
}
