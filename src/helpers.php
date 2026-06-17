<?php

declare(strict_types=1);

use Intisari\Application;
use Intisari\Exception\IntisariException;
use Lukman\Http\RedirectResponse;
use Lukman\Http\Response;
use Lukman\Session\SessionStore;

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $app = Application::getGlobal();

        if ($app === null) {
            throw new IntisariException('No global Intisari application has been set.');
        }

        if ($abstract === null) {
            return $app;
        }

        return $app->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = app()->config();

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('view')) {
    /**
     * @param array<string, mixed> $data
     */
    function view(string $view, array $data = []): string
    {
        return app()->render($view, $data);
    }
}

if (!function_exists('session')) {
    function session(?string $driver = null): SessionStore
    {
        return app()->session($driver);
    }
}

if (!function_exists('response')) {
    /**
     * @param array<string, string|list<string>> $headers
     */
    function response(string $content = '', int $status = 200, array $headers = []): Response
    {
        app();

        return new Response($content, $status, $headers);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): RedirectResponse
    {
        app();

        return new RedirectResponse($url, $status);
    }
}
