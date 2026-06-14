<?php

declare(strict_types=1);

namespace Intisari\Test;

use AssertionError;
use Intisari\Application;
use Intisari\Testing\CreatesApplication;
use Intisari\Testing\TestResponse;
use Lukman\Http\Request;
use Lukman\Http\Response;
use PHPUnit\Framework\TestCase;

class TestingUtilitiesTest extends TestCase
{
    use CreatesApplication;

    public function testCreatesApplicationUsesTemporaryBasePath(): void
    {
        $app = $this->createApplication();

        $this->assertInstanceOf(Application::class, $app);
        $this->assertDirectoryExists($app->basePath());
        $this->assertNull(Application::getGlobal());
    }

    public function testTestResponseStatus(): void
    {
        $response = new TestResponse(new Response('Created', 201));

        $this->assertSame(201, $response->status());
        $this->assertSame($response, $response->assertStatus(201));
    }

    public function testTestResponseSee(): void
    {
        $response = new TestResponse(new Response('Hello Intisari'));

        $this->assertSame('Hello Intisari', $response->content());
        $this->assertSame($response, $response->assertSee('Intisari'));
    }

    public function testTestResponseHeader(): void
    {
        $response = new TestResponse(new Response('OK', 200, ['X-Test' => 'yes']));

        $this->assertSame($response, $response->assertHeader('X-Test'));
        $this->assertSame($response, $response->assertHeader('X-Test', 'yes'));
    }

    public function testTestResponseAssertionFailure(): void
    {
        $response = new TestResponse(new Response('OK', 200));

        $this->expectException(AssertionError::class);

        $response->assertStatus(404);
    }

    public function testApplicationTestRoute(): void
    {
        $app = new Application();
        $app->get('/testing', function () {
            return new Response('testing-ok', 202, ['X-Test' => 'route']);
        });

        $response = $app->test(new Request('GET', '/testing'));

        $this->assertInstanceOf(TestResponse::class, $response);
        $response
            ->assertStatus(202)
            ->assertSee('testing-ok')
            ->assertHeader('X-Test', 'route');
    }
}
