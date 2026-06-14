<?php

declare(strict_types=1);

namespace Intisari\Testing;

use AssertionError;
use Lukman\Http\Response;

class TestResponse
{
    public function __construct(private Response $response)
    {
    }

    public function status(): int
    {
        return $this->response->status();
    }

    public function content(): string
    {
        return $this->response->content();
    }

    public function assertStatus(int $status): self
    {
        if ($this->status() !== $status) {
            throw new AssertionError(sprintf('Expected response status [%d], got [%d].', $status, $this->status()));
        }

        return $this;
    }

    public function assertSee(string $text): self
    {
        if (!str_contains($this->content(), $text)) {
            throw new AssertionError(sprintf('Expected response content to contain [%s].', $text));
        }

        return $this;
    }

    public function assertHeader(string $name, mixed $value = null): self
    {
        $headers = $this->response->headers();

        if (!$headers->has($name)) {
            throw new AssertionError(sprintf('Expected response header [%s] to exist.', $name));
        }

        if (func_num_args() >= 2 && $headers->get($name) !== $value) {
            throw new AssertionError(sprintf('Expected response header [%s] to equal [%s].', $name, (string) $value));
        }

        return $this;
    }
}
