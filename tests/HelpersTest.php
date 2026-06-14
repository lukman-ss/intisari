<?php

declare(strict_types=1);

namespace Intisari\Test;

use Intisari\Application;
use Intisari\Exception\IntisariException;
use Lukman\Config\Config;
use Lukman\Http\RedirectResponse;
use Lukman\Http\Response;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::clearGlobal();
    }

    public function testSetGlobalApp(): void
    {
        $app = new Application();

        $this->assertSame($app, $app->setAsGlobal());
        $this->assertSame($app, Application::getGlobal());
    }

    public function testAppHelper(): void
    {
        $app = (new Application())->setAsGlobal();

        $this->assertSame($app, app());
        $this->assertSame($app->container(), app('container'));
    }

    public function testAppHelperThrowsWhenGlobalAppMissing(): void
    {
        $this->expectException(IntisariException::class);

        app();
    }

    public function testConfigHelper(): void
    {
        $app = (new Application())->setAsGlobal();
        $app->config()->set('app.name', 'Intisari');

        $this->assertInstanceOf(Config::class, config());
        $this->assertSame('Intisari', config('app.name'));
        $this->assertSame('fallback', config('missing.key', 'fallback'));
    }

    public function testViewHelper(): void
    {
        $app = (new Application())->setAsGlobal();
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'intisari_views_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'hello.php', 'Hello, <?= $name ?>');

        $app->config()->set('view.paths', [$tempDir]);

        $this->assertSame('Hello, Lukman', view('hello', ['name' => 'Lukman']));

        unlink($tempDir . DIRECTORY_SEPARATOR . 'hello.php');
        rmdir($tempDir);
    }

    public function testResponseHelper(): void
    {
        (new Application())->setAsGlobal();

        $response = response('OK', 201, ['X-Test' => 'yes']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('OK', $response->content());
        $this->assertSame(201, $response->status());
        $this->assertSame('yes', $response->headers()->get('X-Test'));
    }

    public function testResponseHelperThrowsWhenGlobalAppMissing(): void
    {
        $this->expectException(IntisariException::class);

        response();
    }

    public function testRedirectHelper(): void
    {
        (new Application())->setAsGlobal();

        $response = redirect('/login', 301);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(301, $response->status());
        $this->assertSame('/login', $response->headers()->get('Location'));
    }

    public function testRedirectHelperThrowsWhenGlobalAppMissing(): void
    {
        $this->expectException(IntisariException::class);

        redirect('/login');
    }

    public function testClearGlobalApp(): void
    {
        (new Application())->setAsGlobal();

        Application::clearGlobal();

        $this->assertNull(Application::getGlobal());
    }
}
