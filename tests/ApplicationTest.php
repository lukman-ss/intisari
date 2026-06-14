<?php

declare(strict_types=1);

namespace Intisari\Test;

use PHPUnit\Framework\TestCase;
use Intisari\Application;
use Intisari\ServiceProvider;
use Intisari\Exception\IntisariException;
use Intisari\HttpKernel;
use Intisari\ExceptionHandler;
use Lukman\Container\Container;
use Lukman\Config\Config;
use Lukman\Router\Router;
use Lukman\Router\Route;
use Lukman\Http\Request;
use Lukman\Http\Response;
use Lukman\Router\Exception\RouteNotFoundException;
use Lukman\Router\Exception\MethodNotAllowedException;
use Lukman\Validation\Exception\ValidationException;
use Lukman\Validation\MessageBag;
use Lukman\Validation\ValidatorFactory;
use Lukman\View\ViewFactory;
use Lukman\Database\DatabaseManager;
use Lukman\Database\Connection;
use Lukman\Database\Exception\ConnectionException;
use Lukman\Console\ConsoleApplication;
use Lukman\Console\CommandInterface;
use Lukman\Console\Input;
use Lukman\Console\Output;
use Intisari\ConsoleKernel;

class ApplicationTest extends TestCase
{
    public function testDefaultBasePath(): void
    {
        $app = new Application();
        $this->assertEquals(getcwd(), $app->basePath());
    }

    public function testCustomBasePath(): void
    {
        $customPath = DIRECTORY_SEPARATOR === '\\' ? 'C:\\my-app' : '/my-app';
        $app = new Application($customPath);
        $this->assertEquals($customPath, $app->basePath());
    }

    public function testPathsResolution(): void
    {
        $customPath = DIRECTORY_SEPARATOR === '\\' ? 'C:\\my-app' : '/my-app';
        $app = new Application($customPath);

        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'app', $app->path());
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'config', $app->configPath());
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'routes', $app->routesPath());
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'public', $app->publicPath());
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'storage', $app->storagePath());
    }

    public function testPathsResolutionWithSubpath(): void
    {
        $customPath = DIRECTORY_SEPARATOR === '\\' ? 'C:\\my-app' : '/my-app';
        $app = new Application($customPath);

        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http', $app->path('Http'));
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http', $app->path('/Http'));
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http', $app->path('\\Http'));

        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', $app->configPath('app.php'));
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php', $app->routesPath('web.php'));
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php', $app->publicPath('index.php'));
        $this->assertEquals($customPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', $app->storagePath('logs'));
    }

    public function testSafePathJoiningFromTrailingSlash(): void
    {
        $customPath = DIRECTORY_SEPARATOR === '\\' ? 'C:\\my-app\\' : '/my-app/';
        $app = new Application($customPath);

        $expectedBase = DIRECTORY_SEPARATOR === '\\' ? 'C:\\my-app' : '/my-app';
        $this->assertEquals($expectedBase, $app->basePath());
        $this->assertEquals($expectedBase . DIRECTORY_SEPARATOR . 'app', $app->path());
        $this->assertEquals($expectedBase . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'file.php', $app->path('file.php'));
        $this->assertEquals($expectedBase . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'file.php', $app->path('/file.php'));
    }

    public function testVersionAndEnvironmentAndConsole(): void
    {
        $app = new Application();
        
        $this->assertIsString($app->version());
        $this->assertEquals('production', $app->environment());
        $this->assertTrue($app->runningInConsole());
    }

    public function testContainerIsCreatedAndResolved(): void
    {
        $app = new Application();
        
        // Assert container exists and is correct type
        $this->assertInstanceOf(Container::class, $app->container());

        // Assert core instances are registered
        $this->assertSame($app, $app->make(Application::class));
        $this->assertSame($app, $app->make('app'));
        $this->assertSame($app->container(), $app->make(Container::class));
        $this->assertSame($app->container(), $app->make('container'));
        $this->assertEquals($app->basePath(), $app->make('path.base'));
        $this->assertTrue($app->bound(Application::class));
        $this->assertTrue($app->bound('app'));
        $this->assertTrue($app->bound(Container::class));
        $this->assertTrue($app->bound('container'));
        $this->assertTrue($app->bound('path.base'));
    }

    public function testContainerProxyMethods(): void
    {
        $app = new Application();

        // Test bind & make
        $app->bind('foo', function () {
            return 'bar';
        });
        $this->assertTrue($app->bound('foo'));
        $this->assertEquals('bar', $app->make('foo'));

        // Test singleton
        $runCount = 0;
        $app->singleton('counter', function () use (&$runCount) {
            $runCount++;
            return $runCount;
        });
        $this->assertEquals(1, $app->make('counter'));
        $this->assertEquals(1, $app->make('counter'));

        // Test instance
        $instanceObj = new \stdClass();
        $app->instance('baz', $instanceObj);
        $this->assertSame($instanceObj, $app->make('baz'));
    }

    public function testRegisterServiceProviderInstance(): void
    {
        $app = new Application();
        $provider = new DummyServiceProvider($app);

        $registered = $app->register($provider);

        $this->assertSame($provider, $registered);
        $this->assertTrue($provider->registered);
        $this->assertFalse($provider->booted);
        $this->assertContains($provider, $app->providers());
    }

    public function testRegisterServiceProviderClassString(): void
    {
        $app = new Application();

        $registered = $app->register(DummyServiceProvider::class);

        $this->assertInstanceOf(DummyServiceProvider::class, $registered);
        $this->assertTrue($registered->registered);
        $this->assertContains($registered, $app->providers());
    }

    public function testDuplicateServiceProviderRegistration(): void
    {
        $app = new Application();

        $firstCount = count($app->providers());
        $first = $app->register(DummyServiceProvider::class);
        $secondCount = count($app->providers());

        $second = $app->register(DummyServiceProvider::class);
        $thirdCount = count($app->providers());

        $this->assertSame($first, $second);
        $this->assertEquals($firstCount + 1, $secondCount);
        $this->assertEquals($secondCount, $thirdCount);
    }

    public function testBootCallsProviderBootOnce(): void
    {
        $app = new Application();
        $provider1 = $app->register(DummyServiceProvider::class);
        $provider2 = $app->register(AnotherDummyServiceProvider::class);

        $this->assertFalse($app->booted());
        $this->assertFalse($provider1->booted);
        $this->assertFalse($provider2->booted);

        $app->boot();

        $this->assertTrue($app->booted());
        $this->assertTrue($provider1->booted);
        $this->assertTrue($provider2->booted);

        // Reset provider flag to check if boot is run only once
        $provider1->booted = false;
        $app->boot();
        $this->assertFalse($provider1->booted);
    }

    public function testImmediateBootWhenAlreadyBooted(): void
    {
        $app = new Application();
        $app->boot();
        $this->assertTrue($app->booted());

        $provider = new DummyServiceProvider($app);
        $this->assertFalse($provider->booted);

        $app->register($provider);
        $this->assertTrue($provider->booted);
    }

    public function testConfigIntegration(): void
    {
        $app = new Application();
        
        $this->assertInstanceOf(Config::class, $app->config());
        $this->assertSame($app->config(), $app->make('config'));
        $this->assertSame($app->config(), $app->make(Config::class));
    }

    public function testLoadEnvironment(): void
    {
        $app = new Application();
        $tempEnvFile = tempnam(sys_get_temp_dir(), 'env_');
        file_put_contents($tempEnvFile, "APP_ENV=local\nDEBUG=true");

        $app->loadEnvironment($tempEnvFile);

        $this->assertEquals('local', $_ENV['APP_ENV']);
        $this->assertEquals(true, $_ENV['DEBUG']);

        unlink($tempEnvFile);
    }

    public function testLoadConfigurationDirectory(): void
    {
        $app = new Application();
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'config_' . uniqid();
        mkdir($tempDir);

        file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'app.php', "<?php return ['env' => 'staging', 'name' => 'Intisari'];");
        file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'db.php', "<?php return ['host' => '127.0.0.1'];");

        $app->loadConfiguration($tempDir);

        $this->assertEquals('staging', $app->config()->get('app.env'));
        $this->assertEquals('Intisari', $app->config()->get('app.name'));
        $this->assertEquals('127.0.0.1', $app->config()->get('db.host'));
        
        // Test environment() reading app.env from config
        $this->assertEquals('staging', $app->environment());

        // Clean up
        unlink($tempDir . DIRECTORY_SEPARATOR . 'app.php');
        unlink($tempDir . DIRECTORY_SEPARATOR . 'db.php');
        rmdir($tempDir);
    }

    public function testMissingConfigurationDirectoryIsNotFatal(): void
    {
        $app = new Application();
        $nonExistentDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'does_not_exist_' . uniqid();

        // Should not throw any exception
        $app->loadConfiguration($nonExistentDir);
        $this->assertTrue(true);
    }

    public function testRouterIntegration(): void
    {
        $app = new Application();
        
        $this->assertInstanceOf(Router::class, $app->router());
        $this->assertSame($app->router(), $app->make('router'));
        $this->assertSame($app->router(), $app->make(Router::class));
    }

    public function testRouteProxyMethods(): void
    {
        $app = new Application();
        
        $route = $app->get('/home', 'HomeController@index');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['GET'], $route->methods());
        $this->assertEquals('/home', $route->path());
        
        $app->post('/submit', 'SubmitController@save')->name('submit.save');
        $this->assertEquals('/submit', $app->url('submit.save'));
    }

    public function testRouteGroupProxy(): void
    {
        $app = new Application();
        
        $app->group(['prefix' => 'admin'], function ($router) {
            $router->get('/dashboard', 'DashboardController@index')->name('admin.dashboard');
        });
        
        $this->assertEquals('/admin/dashboard', $app->url('admin.dashboard'));
    }

    public function testLoadRoutesFile(): void
    {
        $app = new Application();
        $tempRoutesFile = tempnam(sys_get_temp_dir(), 'routes_');
        
        // Write routes file using $router variables
        file_put_contents($tempRoutesFile, '<?php $router->get("/api/v1", "Api@index")->name("api.v1");');
        
        $app->loadRoutes($tempRoutesFile);
        
        $this->assertEquals('/api/v1', $app->url('api.v1'));
        
        unlink($tempRoutesFile);
    }

    public function testMissingRouteFileThrowsIntisariException(): void
    {
        $app = new Application();
        $nonExistentFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'no_routes_' . uniqid() . '.php';
        
        $this->expectException(IntisariException::class);
        $app->loadRoutes($nonExistentFile);
    }

    public function testHttpKernelSingleton(): void
    {
        $app = new Application();
        $kernel1 = $app->httpKernel();
        $kernel2 = $app->httpKernel();
        $kernel3 = $app->make(HttpKernel::class);

        $this->assertInstanceOf(HttpKernel::class, $kernel1);
        $this->assertSame($kernel1, $kernel2);
        $this->assertSame($kernel1, $kernel3);
        $this->assertSame($app, $kernel1->app());
    }

    public function testHttpKernelHandleDispatchesRouteAndReturnsResponse(): void
    {
        $app = new Application();
        $app->get('/test-kernel', function () {
            return 'kernel-response';
        });

        $request = new Request('GET', '/test-kernel');
        $response = $app->handle($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('kernel-response', $response->content());
    }

    public function testHttpKernelTerminateIsCallableWithoutError(): void
    {
        $app = new Application();
        $kernel = $app->httpKernel();

        $request = new Request('GET', '/test-kernel');
        $response = new Response('kernel-response');

        $kernel->terminate($request, $response);
        
        // Assert no exceptions thrown and void return
        $this->assertTrue(true);
    }

    public function testRegisterObjectMiddleware(): void
    {
        $app = new Application();
        $middleware = new TestObjectMiddleware('custom-val');
        $app->middleware($middleware);

        $this->assertCount(1, $app->httpKernel()->middlewares());
        $this->assertSame($middleware, $app->httpKernel()->middlewares()[0]);

        $app->get('/test-mw-obj', function () {
            return 'ok';
        });

        $request = new Request('GET', '/test-mw-obj');
        $response = $app->handle($request);

        $this->assertEquals('ok', $response->content());
        $this->assertEquals('custom-val', $response->headers()->get('X-Test-Object'));
    }

    public function testRegisterClassMiddleware(): void
    {
        $app = new Application();
        $app->middleware(TestClassMiddleware::class);

        $this->assertCount(1, $app->httpKernel()->middlewares());
        $this->assertEquals(TestClassMiddleware::class, $app->httpKernel()->middlewares()[0]);

        $app->get('/test-mw-class', function () {
            return 'ok';
        });

        $request = new Request('GET', '/test-mw-class');
        $response = $app->handle($request);

        $this->assertEquals('ok', $response->content());
        $this->assertEquals('class-run', $response->headers()->get('X-Test-Class'));
    }

    public function testMiddlewareOrderAndArray(): void
    {
        $app = new Application();
        TestOrderTracker::$log = [];

        // Add array of middlewares to test array appending and ordering
        $app->middleware([
            TestOrderMiddlewareA::class,
            TestOrderMiddlewareB::class,
        ]);

        $this->assertCount(2, $app->httpKernel()->middlewares());

        $app->get('/test-mw-order', function () {
            return 'ok';
        });

        $request = new Request('GET', '/test-mw-order');
        $app->handle($request);

        $this->assertEquals(['A', 'B'], TestOrderTracker::$log);
    }

    public function testMiddlewareShortCircuit(): void
    {
        $app = new Application();
        $app->middleware(TestShortCircuitMiddleware::class);

        $routeCalled = false;
        $app->get('/test-mw-short', function () use (&$routeCalled) {
            $routeCalled = true;
            return 'ok';
        });

        $request = new Request('GET', '/test-mw-short');
        $response = $app->handle($request);

        $this->assertEquals('short-circuited', $response->content());
        $this->assertEquals(201, $response->status());
        $this->assertFalse($routeCalled);
    }

    public function testRegisterInvalidMiddlewareObjectThrowsException(): void
    {
        $app = new Application();
        
        $this->expectException(IntisariException::class);
        $app->middleware(new TestInvalidMiddleware());
    }

    public function testRegisterInvalidMiddlewareClassThrowsException(): void
    {
        $app = new Application();
        
        $this->expectException(IntisariException::class);
        $app->middleware(TestInvalidMiddleware::class);
    }

    public function testRegisterNonExistentMiddlewareClassThrowsException(): void
    {
        $app = new Application();
        
        $this->expectException(IntisariException::class);
        $app->middleware('NonExistentMiddlewareClass');
    }

    public function testExceptionHandlerRegistration(): void
    {
        $app = new Application();
        $this->assertInstanceOf(ExceptionHandler::class, $app->exceptionHandler());
        $this->assertSame($app->exceptionHandler(), $app->make(ExceptionHandler::class));
    }

    public function testExceptionHandlerDebugModeToggle(): void
    {
        $app = new Application();
        $this->assertFalse($app->exceptionHandler()->debug());
        
        $app->debug(true);
        $this->assertTrue($app->exceptionHandler()->debug());

        $app->debug(false);
        $this->assertFalse($app->exceptionHandler()->debug());
    }

    public function testExceptionRouteNotFound404(): void
    {
        $app = new Application();
        // Request a non-existent path
        $request = new Request('GET', '/non-existent-path');
        $response = $app->handle($request);

        $this->assertEquals(404, $response->status());
        $this->assertEquals('text/plain', $response->headers()->get('Content-Type'));
        $this->assertEquals('Not Found', $response->content());

        // With debug = true
        $app->debug(true);
        $response = $app->handle($request);
        $this->assertStringContainsString(RouteNotFoundException::class, $response->content());
    }

    public function testExceptionMethodNotAllowed405(): void
    {
        $app = new Application();
        $app->post('/only-post', function () {
            return 'post-ok';
        });

        // Request with wrong method (GET instead of POST)
        $request = new Request('GET', '/only-post');
        $response = $app->handle($request);

        $this->assertEquals(405, $response->status());
        $this->assertEquals('text/plain', $response->headers()->get('Content-Type'));
        $this->assertEquals('Method Not Allowed', $response->content());

        // With debug = true
        $app->debug(true);
        $response = $app->handle($request);
        $this->assertStringContainsString(MethodNotAllowedException::class, $response->content());
    }

    public function testExceptionValidationFailed422(): void
    {
        $app = new Application();
        $app->get('/trigger-validation', function () {
            throw new ValidationException(new MessageBag());
        });

        $request = new Request('GET', '/trigger-validation');
        $response = $app->handle($request);

        $this->assertEquals(422, $response->status());
        $this->assertEquals('text/plain', $response->headers()->get('Content-Type'));
        $this->assertEquals('Unprocessable Entity', $response->content());

        // With debug = true
        $app->debug(true);
        $response = $app->handle($request);
        $this->assertStringContainsString(ValidationException::class, $response->content());
    }

    public function testExceptionInternalServerError500(): void
    {
        $app = new Application();
        $app->get('/trigger-500', function () {
            throw new \Exception('Something bad happened');
        });

        $request = new Request('GET', '/trigger-500');
        $response = $app->handle($request);

        $this->assertEquals(500, $response->status());
        $this->assertEquals('text/plain', $response->headers()->get('Content-Type'));
        $this->assertEquals('Internal Server Error', $response->content());

        // With debug = true
        $app->debug(true);
        $response = $app->handle($request);
        $this->assertStringContainsString(\Exception::class, $response->content());
        $this->assertStringContainsString('Something bad happened', $response->content());
    }

    public function testIntisariExceptionInternalServerError500(): void
    {
        $app = new Application();
        $app->get('/trigger-intisari-500', function () {
            throw new IntisariException('Intisari failed internally');
        });

        $request = new Request('GET', '/trigger-intisari-500');
        $response = $app->handle($request);

        $this->assertEquals(500, $response->status());
        $this->assertEquals('text/plain', $response->headers()->get('Content-Type'));
        $this->assertEquals('Internal Server Error', $response->content());
        $this->assertStringNotContainsString('Intisari failed internally', $response->content());

        $app->debug(true);
        $response = $app->handle($request);

        $this->assertStringContainsString(IntisariException::class, $response->content());
        $this->assertStringContainsString('Intisari failed internally', $response->content());
    }

    public function testRunExecutesHttpLifecycle(): void
    {
        $app = new Application();
        
        // Register a SpyHttpKernel to verify terminate is called
        $spyKernel = new SpyHttpKernel($app);
        $app->instance(HttpKernel::class, $spyKernel);

        $app->get('/run-test', function () {
            return 'run-body';
        });

        $request = new Request('GET', '/run-test');

        ob_start();
        try {
            $response = $app->run($request);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        // 1. run request manual & run return response
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('run-body', $response->content());

        // 2. run sends response content
        $this->assertEquals('run-body', $output);

        // 3. terminate terpanggil
        $this->assertTrue($spyKernel->terminated);

        // 4. run tidak exit (execution successfully returns here)
        $this->assertTrue(true);
    }

    public function testRunDefaultRequestCapture(): void
    {
        $app = new Application();
        
        // Mock $_SERVER globals to ensure capture returns what we expect
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/run-captured';

        $app->get('/run-captured', function () {
            return 'captured-body';
        });

        ob_start();
        try {
            $response = $app->run();
        } finally {
            ob_end_clean();
        }

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('captured-body', $response->content());

        // Clean up
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    public function testViewFactoryIsSingleton(): void
    {
        $app = new Application();
        $view1 = $app->view();
        $view2 = $app->view();
        $view3 = $app->make(ViewFactory::class);
        $view4 = $app->make('view');

        $this->assertInstanceOf(ViewFactory::class, $view1);
        $this->assertSame($view1, $view2);
        $this->assertSame($view1, $view3);
        $this->assertSame($view1, $view4);
    }

    public function testConfigViewPath(): void
    {
        $app = new Application();
        
        $customPath1 = DIRECTORY_SEPARATOR === '\\' ? 'C:\\custom\\path\\1' : '/custom/path/1';
        $customPath2 = DIRECTORY_SEPARATOR === '\\' ? 'C:\\custom\\path\\2' : '/custom/path/2';
        
        $app->config()->set('view.paths', [$customPath1, $customPath2]);

        $paths = $app->view()->finder()->paths();

        $this->assertContains($customPath1, $paths);
        $this->assertContains($customPath2, $paths);
    }

    public function testRenderViewFromTempDirectory(): void
    {
        $app = new Application();
        
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'views_' . uniqid();
        mkdir($tempDir);
        
        file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'test-view.php', 'Hello, <?= $name ?>!');

        $app->config()->set('view.paths', [$tempDir]);

        $rendered = $app->render('test-view', ['name' => 'Lukman']);

        $this->assertEquals('Hello, Lukman!', $rendered);

        // Clean up
        unlink($tempDir . DIRECTORY_SEPARATOR . 'test-view.php');
        rmdir($tempDir);
    }

    public function testSharedDataStillUsable(): void
    {
        $app = new Application();
        
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'views_' . uniqid();
        mkdir($tempDir);
        
        file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'shared-view.php', 'Val: <?= $globalVar ?>');

        $app->config()->set('view.paths', [$tempDir]);

        $app->view()->share('globalVar', 'shared-val');

        $rendered = $app->render('shared-view');

        $this->assertEquals('Val: shared-val', $rendered);

        // Clean up
        unlink($tempDir . DIRECTORY_SEPARATOR . 'shared-view.php');
        rmdir($tempDir);
    }

    public function testDatabaseManagerIsSingleton(): void
    {
        $app = new Application();
        $db1 = $app->database();
        $db2 = $app->database();
        $db3 = $app->make(DatabaseManager::class);
        $db4 = $app->make('db');

        $this->assertInstanceOf(DatabaseManager::class, $db1);
        $this->assertSame($db1, $db2);
        $this->assertSame($db1, $db3);
        $this->assertSame($db1, $db4);
    }

    public function testSqliteMemoryConfigAndConnectionWorks(): void
    {
        $app = new Application();
        
        $app->config()->set('database.connections', [
            'sqlite_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);
        $app->config()->set('database.default', 'sqlite_test');

        $connection = $app->db();
        $this->assertInstanceOf(Connection::class, $connection);

        $result = $connection->statement("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
        $this->assertTrue($result);

        $inserted = $connection->affectingStatement("INSERT INTO users (name) VALUES (?), (?)", ['Lukman', 'SS']);
        $this->assertEquals(2, $inserted);

        $users = $connection->select("SELECT * FROM users ORDER BY id ASC");
        $this->assertCount(2, $users);
        $this->assertEquals('Lukman', $users[0]['name']);
        $this->assertEquals('SS', $users[1]['name']);
    }

    public function testMissingConfigIsNotFatalUntilConnectionCalled(): void
    {
        $app = new Application();
        
        // Clearing database config
        $app->config()->set('database', null);

        // DatabaseManager should be resolvable without exception
        $dbManager = $app->database();
        $this->assertInstanceOf(DatabaseManager::class, $dbManager);

        // Should throw exception only when attempting to get connection
        $this->expectException(ConnectionException::class);
        $app->db();
    }

    public function testValidatorFactoryIsSingleton(): void
    {
        $app = new Application();
        $val1 = $app->validator();
        $val2 = $app->validator();
        $val3 = $app->make(ValidatorFactory::class);
        $val4 = $app->make('validator');

        $this->assertInstanceOf(ValidatorFactory::class, $val1);
        $this->assertSame($val1, $val2);
        $this->assertSame($val1, $val3);
        $this->assertSame($val1, $val4);
    }

    public function testValidateSuccess(): void
    {
        $app = new Application();
        
        $validated = $app->validate(
            ['email' => 'test@example.com', 'age' => 25],
            ['email' => 'required', 'age' => 'required']
        );

        $this->assertEquals([
            'email' => 'test@example.com',
            'age' => 25,
        ], $validated);
    }

    public function testValidateFailThrowsValidationException(): void
    {
        $app = new Application();
        
        $this->expectException(ValidationException::class);
        
        $app->validate(
            ['email' => ''],
            ['email' => 'required']
        );
    }

    public function testCustomMessageWorks(): void
    {
        $app = new Application();

        try {
            $app->validate(
                ['email' => ''],
                ['email' => 'required'],
                ['email.required' => 'Custom Required Message!']
            );
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertEquals('Custom Required Message!', $e->errors()->first('email'));
        }
    }

    public function testConsoleApplicationIsSingleton(): void
    {
        $app = new Application();
        $console1 = $app->console();
        $console2 = $app->console();
        $console3 = $app->make(ConsoleApplication::class);

        $this->assertInstanceOf(ConsoleApplication::class, $console1);
        $this->assertSame($console1, $console2);
        $this->assertSame($console1, $console3);
        $this->assertEquals('Intisari', $console1->name());
        $this->assertEquals($app->version(), $console1->version());
    }

    public function testConsoleCommandCallable(): void
    {
        $app = new Application();
        $command = $app->command('test-callable', function ($input, $output) {
            return 0;
        }, 'Dynamic Test Command');

        $this->assertInstanceOf(CommandInterface::class, $command);
        $this->assertTrue($app->console()->registry()->has('test-callable'));
    }

    public function testRunConsoleSuccess(): void
    {
        $app = new Application();
        $app->command('run-success', function ($input, $output) {
            $output->write('success-output');
            return 0;
        });

        $input = new Input(['cli.php', 'run-success']);
        $stream = fopen('php://memory', 'w+');
        $output = new Output($stream, $stream);

        $exitCode = $app->runConsole($input, $output);

        $this->assertEquals(0, $exitCode);

        rewind($stream);
        $outputContent = stream_get_contents($stream);
        $this->assertEquals('success-output', $outputContent);
        fclose($stream);
    }

    public function testRunConsoleUnknownCommandReturnsInvalid(): void
    {
        $app = new Application();

        $input = new Input(['cli.php', 'unknown-cmd-test']);
        $stream = fopen('php://memory', 'w+');
        $output = new Output($stream, $stream);

        $exitCode = $app->runConsole($input, $output);

        // Command::INVALID is 2
        $this->assertEquals(2, $exitCode);

        rewind($stream);
        $outputContent = stream_get_contents($stream);
        $this->assertStringContainsString('Command "unknown-cmd-test" is not defined.', $outputContent);
        fclose($stream);
    }

    public function testConsoleKernelDelegation(): void
    {
        $app = new Application();
        $kernel = new ConsoleKernel($app);

        $app->command('kernel-test', function ($input, $output) {
            $output->write('kernel-ok');
            return 0;
        });

        $input = new Input(['cli.php', 'kernel-test']);
        $stream = fopen('php://memory', 'w+');
        $output = new Output($stream, $stream);

        $exitCode = $kernel->handle($input, $output);

        $this->assertEquals(0, $exitCode);
        rewind($stream);
        $this->assertEquals('kernel-ok', stream_get_contents($stream));
        fclose($stream);
        
        $this->assertSame($app, $kernel->app());
    }

    public function testAboutCommandOutput(): void
    {
        $app = new Application();
        $input = new Input(['cli.php', 'about']);
        $stream = fopen('php://memory', 'w+');
        $output = new Output($stream, $stream);

        $exitCode = $app->runConsole($input, $output);
        $this->assertEquals(0, $exitCode);

        rewind($stream);
        $content = stream_get_contents($stream);
        $this->assertStringContainsString('Framework: Intisari', $content);
        $this->assertStringContainsString('Version: 1.0.0', $content);
        $this->assertStringContainsString('Environment: production', $content);
        fclose($stream);
    }

    public function testConfigCacheAndClearCommands(): void
    {
        $app = new Application();
        
        // Ensure cache directory clean before testing
        $cacheFile = $app->storagePath('cache/config.php');
        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }

        // 1. Run config:cache
        $inputCache = new Input(['cli.php', 'config:cache']);
        $streamCache = fopen('php://memory', 'w+');
        $outputCache = new Output($streamCache, $streamCache);

        $exitCodeCache = $app->runConsole($inputCache, $outputCache);
        $this->assertEquals(0, $exitCodeCache);
        $this->assertFileExists($cacheFile);
        fclose($streamCache);

        // 2. Run config:clear
        $inputClear = new Input(['cli.php', 'config:clear']);
        $streamClear = fopen('php://memory', 'w+');
        $outputClear = new Output($streamClear, $streamClear);

        $exitCodeClear = $app->runConsole($inputClear, $outputClear);
        $this->assertEquals(0, $exitCodeClear);
        $this->assertFileDoesNotExist($cacheFile);
        fclose($streamClear);
    }

    public function testRouteListCommandDoesNotCrash(): void
    {
        $app = new Application();
        $app->get('/test-route-1', function () {});
        $app->post('/test-route-2', function () {});

        $input = new Input(['cli.php', 'route:list']);
        $stream = fopen('php://memory', 'w+');
        $output = new Output($stream, $stream);

        $exitCode = $app->runConsole($input, $output);
        $this->assertEquals(0, $exitCode);

        rewind($stream);
        $content = stream_get_contents($stream);
        $this->assertStringContainsString('GET /test-route-1', $content);
        $this->assertStringContainsString('POST /test-route-2', $content);
        fclose($stream);
    }

    public function testBootstrapCallbackOrder(): void
    {
        $app = new Application();
        $order = [];

        $app->bootstrapping(function () use (&$order) {
            $order[] = 'before';
        });

        $app->bootstrapped(function () use (&$order) {
            $order[] = 'after';
        });

        $this->assertFalse($app->bootstrapped());
        $app->bootstrap();
        $this->assertTrue($app->bootstrapped());

        $this->assertEquals(['before', 'after'], $order);
    }

    public function testBootstrapRunsProviderBootBetweenCallbacks(): void
    {
        $app = new Application();
        $order = [];

        $app->bootstrapping(function () use (&$order) {
            $order[] = 'bootstrapping';
        });

        $app->register(new class($app, $order) extends ServiceProvider {
            /**
             * @param array<int, string> $order
             */
            public function __construct(Application $app, private array &$order)
            {
                parent::__construct($app);
            }

            public function boot(): void
            {
                $this->order[] = 'provider';
            }
        });

        $app->bootstrapped(function (Application $app) use (&$order) {
            $order[] = $app->bootstrapped() ? 'bootstrapped' : 'not-bootstrapped';
        });

        $app->bootstrap();

        $this->assertEquals(['bootstrapping', 'provider', 'bootstrapped'], $order);
    }

    public function testBootstrapRunsOnce(): void
    {
        $app = new Application();
        $count = 0;

        $app->bootstrapping(function () use (&$count) {
            $count++;
        });

        $app->bootstrap();
        $app->bootstrap();

        $this->assertEquals(1, $count);
    }

    public function testRunTriggersBootstrap(): void
    {
        $app = new Application();
        $app->get('/trigger-boot', function () {
            return 'ok';
        });

        $request = new Request('GET', '/trigger-boot');

        $this->assertFalse($app->bootstrapped());

        ob_start();
        try {
            $app->run($request);
        } finally {
            ob_end_clean();
        }

        $this->assertTrue($app->bootstrapped());
    }

    public function testRunConsoleTriggersBootstrap(): void
    {
        $app = new Application();
        $app->command('trigger-boot-console', function ($input, $output) {
            return 0;
        });

        $input = new Input(['cli.php', 'trigger-boot-console']);
        $stream = fopen('php://memory', 'w+');
        $output = new Output($stream, $stream);

        $this->assertFalse($app->bootstrapped());

        $app->runConsole($input, $output);
        fclose($stream);

        $this->assertTrue($app->bootstrapped());
    }

    public function testBootstrapCallbackExceptionBubbles(): void
    {
        $app = new Application();

        $app->bootstrapping(function () {
            throw new \RuntimeException('Callback error bubbles');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Callback error bubbles');

        $app->bootstrap();
    }
}

class DummyServiceProvider extends ServiceProvider
{
    public bool $registered = false;
    public bool $booted = false;

    public function register(): void
    {
        $this->registered = true;
    }

    public function boot(): void
    {
        $this->booted = true;
    }
}

class AnotherDummyServiceProvider extends ServiceProvider
{
    public bool $registered = false;
    public bool $booted = false;

    public function register(): void
    {
        $this->registered = true;
    }

    public function boot(): void
    {
        $this->booted = true;
    }
}

class TestObjectMiddleware implements \Lukman\Http\MiddlewareInterface
{
    public function __construct(private string $headerValue = 'object-run')
    {
    }

    public function process(\Lukman\Http\Request $request, \Lukman\Http\RequestHandlerInterface $handler): \Lukman\Http\Response
    {
        $response = $handler->handle($request);
        return $response->header('X-Test-Object', $this->headerValue);
    }
}

class TestClassMiddleware implements \Lukman\Http\MiddlewareInterface
{
    public function process(\Lukman\Http\Request $request, \Lukman\Http\RequestHandlerInterface $handler): \Lukman\Http\Response
    {
        $response = $handler->handle($request);
        return $response->header('X-Test-Class', 'class-run');
    }
}

class TestOrderTracker
{
    public static array $log = [];
}

class TestOrderMiddlewareA implements \Lukman\Http\MiddlewareInterface
{
    public function process(\Lukman\Http\Request $request, \Lukman\Http\RequestHandlerInterface $handler): \Lukman\Http\Response
    {
        TestOrderTracker::$log[] = 'A';
        return $handler->handle($request);
    }
}

class TestOrderMiddlewareB implements \Lukman\Http\MiddlewareInterface
{
    public function process(\Lukman\Http\Request $request, \Lukman\Http\RequestHandlerInterface $handler): \Lukman\Http\Response
    {
        TestOrderTracker::$log[] = 'B';
        return $handler->handle($request);
    }
}

class TestShortCircuitMiddleware implements \Lukman\Http\MiddlewareInterface
{
    public function process(\Lukman\Http\Request $request, \Lukman\Http\RequestHandlerInterface $handler): \Lukman\Http\Response
    {
        return new \Lukman\Http\Response('short-circuited', 201);
    }
}

class TestInvalidMiddleware
{
}

class SpyHttpKernel extends HttpKernel
{
    public bool $terminated = false;

    public function terminate(Request $request, Response $response): void
    {
        $this->terminated = true;
    }
}
