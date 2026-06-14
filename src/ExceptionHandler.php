<?php

declare(strict_types=1);

namespace Intisari;

use Throwable;
use Lukman\Http\Response;
use Lukman\Router\Exception\RouteNotFoundException;
use Lukman\Router\Exception\MethodNotAllowedException;
use Lukman\Validation\Exception\ValidationException;

class ExceptionHandler
{
    protected bool $debug = false;

    /**
     * Render the given exception into an HTTP response.
     */
    public function render(Throwable $e): Response
    {
        $status = 500;
        $body = 'Internal Server Error';

        if ($e instanceof RouteNotFoundException) {
            $status = 404;
            $body = 'Not Found';
        } elseif ($e instanceof MethodNotAllowedException) {
            $status = 405;
            $body = 'Method Not Allowed';
        } elseif ($e instanceof ValidationException) {
            $status = 422;
            $body = 'Unprocessable Entity';
        }

        if ($this->debug) {
            $body = get_class($e) . ': ' . $e->getMessage();
        }

        return new Response($body, $status, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Get the debug mode state.
     */
    public function debug(): bool
    {
        return $this->debug;
    }

    /**
     * Set the debug mode state.
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }
}
