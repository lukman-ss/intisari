<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\Http\Request;
use Lukman\Http\Response;
use Lukman\Http\MiddlewareInterface;
use Lukman\Http\MiddlewarePipeline;
use Lukman\Http\RequestHandlerInterface;
use Intisari\Exception\IntisariException;

class HttpKernel
{
    /**
     * The global middleware stack.
     *
     * @var array<int, string|object>
     */
    protected array $middlewares = [];

    public function __construct(protected Application $app)
    {
    }

    /**
     * Get the application instance.
     */
    public function app(): Application
    {
        return $this->app;
    }

    /**
     * Add a global middleware.
     *
     * @param string|object|array $middleware
     * @return $this
     * @throws IntisariException
     */
    public function middleware(string|object|array $middleware): self
    {
        if (is_array($middleware)) {
            foreach ($middleware as $m) {
                $this->middleware($m);
            }
            return $this;
        }

        if (is_object($middleware)) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new IntisariException('Middleware object must implement MiddlewareInterface.');
            }
            $this->middlewares[] = $middleware;
        } elseif (is_string($middleware)) {
            if (!class_exists($middleware)) {
                throw new IntisariException("Middleware class [{$middleware}] does not exist.");
            }
            if (!is_subclass_of($middleware, MiddlewareInterface::class)) {
                throw new IntisariException("Middleware class [{$middleware}] must implement " . MiddlewareInterface::class);
            }
            $this->middlewares[] = $middleware;
        } else {
            throw new IntisariException('Invalid middleware type.');
        }

        return $this;
    }

    /**
     * Get the global middleware stack.
     *
     * @return array<int, string|object>
     */
    public function middlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Handle the incoming HTTP request.
     */
    public function handle(Request $request): Response
    {
        try {
            $resolved = [];
            foreach ($this->middlewares as $middleware) {
                if (is_string($middleware)) {
                    $resolved[] = $this->app->make($middleware);
                } else {
                    $resolved[] = $middleware;
                }
            }

            $finalHandler = new class($this->app) implements RequestHandlerInterface {
                public function __construct(private Application $app)
                {
                }

                public function handle(Request $request): Response
                {
                    return $this->app->router()->dispatch($request);
                }
            };

            $pipeline = new MiddlewarePipeline($resolved, $finalHandler);
            return $pipeline->handle($request);
        } catch (\Throwable $e) {
            return $this->app->exceptionHandler()->render($e);
        }
    }

    /**
     * Perform any final actions after the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
    }
}

