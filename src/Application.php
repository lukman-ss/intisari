<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\Container\Container;
use Lukman\Config\Config;
use Lukman\Router\Router;
use Lukman\Router\Route;
use Lukman\Http\Request;
use Lukman\Http\Response;
use Lukman\View\ViewFactory;
use Lukman\Database\DatabaseManager;
use Lukman\Database\Connection;
use Lukman\Validation\ValidatorFactory;
use Lukman\Console\ConsoleApplication;
use Lukman\Console\CommandInterface;
use Lukman\Console\Input;
use Lukman\Console\Output;
use Intisari\Exception\IntisariException;
use Intisari\Testing\TestResponse;

class Application
{
    private static ?Application $global = null;

    private string $basePath;
    private Container $container;

    /**
     * @var array<string, ServiceProvider>
     */
    private array $serviceProviders = [];

    private bool $booted = false;
    private bool $bootstrapped = false;

    /**
     * @var array<int, callable>
     */
    private array $bootstrappingCallbacks = [];

    /**
     * @var array<int, callable>
     */
    private array $bootstrappedCallbacks = [];

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath !== null ? rtrim($basePath, '/\\') : getcwd();
        $this->container = new Container();
        $this->bindCore();
    }

    /**
     * Set this application as the global current application.
     */
    public function setAsGlobal(): self
    {
        self::$global = $this;

        return $this;
    }

    /**
     * Get the global current application.
     */
    public static function getGlobal(): ?Application
    {
        return self::$global;
    }

    /**
     * Clear the global current application.
     */
    public static function clearGlobal(): void
    {
        self::$global = null;
    }

    /**
     * Get the container instance.
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Register a binding with the container.
     */
    public function bind(string $abstract, mixed $concrete = null): void
    {
        $this->container->bind($abstract, $concrete);
    }

    /**
     * Register a shared binding (singleton) with the container.
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    /**
     * Register an existing instance in the container.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    /**
     * Resolve the given type from the container.
     */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool
    {
        return $this->container->has($abstract);
    }

    /**
     * Register a service provider with the application.
     */
    public function register(string|ServiceProvider $provider): ServiceProvider
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        $class = get_class($provider);

        if (isset($this->serviceProviders[$class])) {
            return $this->serviceProviders[$class];
        }

        $this->serviceProviders[$class] = $provider;

        $provider->register();

        if ($this->booted) {
            $provider->boot();
        }

        return $provider;
    }

    /**
     * Bootstrap the application's service providers.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    /**
     * Determine if the application has booted.
     */
    public function booted(): bool
    {
        return $this->booted;
    }

    /**
     * Get all registered service providers.
     *
     * @return array<int, ServiceProvider>
     */
    public function providers(): array
    {
        return array_values($this->serviceProviders);
    }

    /**
     * Get the config repository instance.
     */
    public function config(): Config
    {
        return $this->make('config');
    }

    /**
     * Load environmental variables from the given file.
     */
    public function loadEnvironment(string $path, bool $overwrite = false): self
    {
        $this->config()->loadEnv($path, $overwrite);

        return $this;
    }

    /**
     * Load configuration files from the given directory.
     */
    public function loadConfiguration(?string $directory = null): self
    {
        $directory = $directory ?? $this->configPath();

        if (is_dir($directory)) {
            $this->config()->loadDirectory($directory);
        }

        return $this;
    }

    /**
     * Get the router instance.
     */
    public function router(): Router
    {
        return $this->make('router');
    }

    /**
     * Register a GET route with the router.
     */
    public function get(string $path, mixed $handler): Route
    {
        return $this->router()->get($path, $handler);
    }

    /**
     * Register a POST route with the router.
     */
    public function post(string $path, mixed $handler): Route
    {
        return $this->router()->post($path, $handler);
    }

    /**
     * Register a PUT route with the router.
     */
    public function put(string $path, mixed $handler): Route
    {
        return $this->router()->put($path, $handler);
    }

    /**
     * Register a PATCH route with the router.
     */
    public function patch(string $path, mixed $handler): Route
    {
        return $this->router()->patch($path, $handler);
    }

    /**
     * Register a DELETE route with the router.
     */
    public function delete(string $path, mixed $handler): Route
    {
        return $this->router()->delete($path, $handler);
    }

    /**
     * Register an OPTIONS route with the router.
     */
    public function options(string $path, mixed $handler): Route
    {
        return $this->router()->options($path, $handler);
    }

    /**
     * Register a route that responds to all HTTP verbs.
     */
    public function any(string $path, mixed $handler): Route
    {
        return $this->router()->any($path, $handler);
    }

    /**
     * Register a route with a custom set of HTTP verbs.
     */
    public function match(array $methods, string $path, mixed $handler): Route
    {
        return $this->router()->match($methods, $path, $handler);
    }

    /**
     * Create a route group with shared attributes.
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->router()->group($attributes, $callback);
    }

    /**
     * Generate a URL to a named route.
     */
    public function url(string $name, array $parameters = []): string
    {
        return $this->router()->url($name, $parameters);
    }

    /**
     * Load route definitions from the given file.
     *
     * @throws IntisariException
     */
    public function loadRoutes(string $path): self
    {
        if (!is_file($path)) {
            throw new IntisariException("Routes file [{$path}] not found.");
        }

        $app = $this;
        $router = $this->router();

        (static function () use ($path, $app, $router) {
            require $path;
        })();

        return $this;
    }

    /**
     * Get the HTTP kernel instance.
     */
    public function httpKernel(): HttpKernel
    {
        return $this->make(HttpKernel::class);
    }

    /**
     * Handle the incoming HTTP request.
     */
    public function handle(Request $request): Response
    {
        return $this->httpKernel()->handle($request);
    }

    /**
     * Add a global HTTP middleware.
     *
     * @param string|object|array $middleware
     * @return $this
     */
    public function middleware(string|object|array $middleware): self
    {
        $this->httpKernel()->middleware($middleware);

        return $this;
    }

    /**
     * Get the exception handler instance.
     */
    public function exceptionHandler(): ExceptionHandler
    {
        return $this->make(ExceptionHandler::class);
    }

    /**
     * Set the application debug mode.
     */
    public function debug(bool $enabled): self
    {
        $this->exceptionHandler()->setDebug($enabled);

        return $this;
    }

    /**
     * Get the view factory instance.
     */
    public function view(): ViewFactory
    {
        return $this->make(ViewFactory::class);
    }

    /**
     * Render the given view.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        return $this->view()->render($view, $data);
    }

    /**
     * Get the database manager instance.
     */
    public function database(): DatabaseManager
    {
        return $this->make(DatabaseManager::class);
    }

    /**
     * Get a database connection instance.
     */
    public function db(?string $name = null): Connection
    {
        return $this->database()->connection($name);
    }

    /**
     * Get the validator factory instance.
     */
    public function validator(): ValidatorFactory
    {
        return $this->make(ValidatorFactory::class);
    }

    /**
     * Run the given data against the validator rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     *
     * @throws \Lukman\Validation\Exception\ValidationException
     */
    public function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return $this->validator()->make($data, $rules, $messages, $attributes)->validate();
    }

    /**
     * Get the console application instance.
     */
    public function console(): ConsoleApplication
    {
        return $this->make(ConsoleApplication::class);
    }

    /**
     * Register a console command dynamically.
     */
    public function command(string $name, callable $handler, string $description = ''): CommandInterface
    {
        return $this->console()->command($name, $handler, $description);
    }

    /**
     * Run the console application lifecycle.
     */
    public function runConsole(?Input $input = null, ?Output $output = null): int
    {
        $this->bootstrap();

        return $this->make(ConsoleKernel::class)->handle($input, $output);
    }

    /**
     * Register a callback to be run before bootstrapping.
     */
    public function bootstrapping(callable $callback): void
    {
        $this->bootstrappingCallbacks[] = $callback;
    }

    /**
     * Register a bootstrapped callback or check if the application has bootstrapped.
     *
     * @param callable|null $callback
     * @return bool|void
     */
    public function bootstrapped(?callable $callback = null)
    {
        if ($callback !== null) {
            $this->bootstrappedCallbacks[] = $callback;
            return;
        }

        return $this->bootstrapped;
    }

    /**
     * Bootstrap the application.
     */
    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        // 1. Call bootstrapping callbacks
        foreach ($this->bootstrappingCallbacks as $callback) {
            $callback($this);
        }

        // 2. Register default providers if not yet registered
        $defaultProviders = [
            ConfigServiceProvider::class,
            RoutingServiceProvider::class,
            ViewServiceProvider::class,
            DatabaseServiceProvider::class,
            ValidationServiceProvider::class,
        ];
        foreach ($defaultProviders as $provider) {
            $this->register($provider);
        }

        // 3. Boot providers
        $this->boot();

        $this->bootstrapped = true;

        // 4. Call bootstrapped callbacks
        foreach ($this->bootstrappedCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Run the HTTP lifecycle.
     */
    public function run(?Request $request = null): Response
    {
        $request ??= Request::capture();

        $this->bootstrap();

        $response = $this->handle($request);

        $response->send();

        $this->httpKernel()->terminate($request, $response);

        return $response;
    }

    /**
     * Handle a request for testing without sending the response.
     */
    public function test(Request $request): TestResponse
    {
        $this->bootstrap();

        return new TestResponse($this->handle($request));
    }

    /**
     * Get the base path of the installation.
     */
    public function basePath(string $path = ''): string
    {
        return $this->joinPath($this->basePath, $path);
    }

    /**
     * Get the path to the application directory.
     */
    public function path(string $path = ''): string
    {
        return $this->joinPath($this->basePath . DIRECTORY_SEPARATOR . 'app', $path);
    }

    /**
     * Get the path to the configuration directory.
     */
    public function configPath(string $path = ''): string
    {
        return $this->joinPath($this->basePath . DIRECTORY_SEPARATOR . 'config', $path);
    }

    /**
     * Get the path to the routes directory.
     */
    public function routesPath(string $path = ''): string
    {
        return $this->joinPath($this->basePath . DIRECTORY_SEPARATOR . 'routes', $path);
    }

    /**
     * Get the path to the public directory.
     */
    public function publicPath(string $path = ''): string
    {
        return $this->joinPath($this->basePath . DIRECTORY_SEPARATOR . 'public', $path);
    }

    /**
     * Get the path to the storage directory.
     */
    public function storagePath(string $path = ''): string
    {
        return $this->joinPath($this->basePath . DIRECTORY_SEPARATOR . 'storage', $path);
    }

    /**
     * Get the version number of the application.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Get the current application environment.
     */
    public function environment(): string
    {
        if ($this->bound('config')) {
            return $this->config()->get('app.env', 'production');
        }

        return 'production';
    }

    /**
     * Determine if the application is running in the console.
     */
    public function runningInConsole(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Bind the core framework instances into the container.
     */
    private function bindCore(): void
    {
        $this->instance(self::class, $this);
        $this->instance(Container::class, $this->container);
        $this->instance('app', $this);
        $this->instance('container', $this->container);
        $this->instance('path.base', $this->basePath);

        // Bind HttpKernel as singleton
        $this->singleton(HttpKernel::class, function () {
            return new HttpKernel($this);
        });

        // Bind ConsoleApplication as singleton
        $this->singleton(ConsoleApplication::class, function () {
            return new ConsoleApplication('Intisari', $this->version());
        });

        // Bind ConsoleKernel as singleton
        $this->singleton(ConsoleKernel::class, function () {
            return new ConsoleKernel($this);
        });

        // Bind ExceptionHandler as singleton
        $this->singleton(ExceptionHandler::class, function () {
            return new ExceptionHandler();
        });

        // Register ConfigServiceProvider & RoutingServiceProvider immediately
        $this->register(ConfigServiceProvider::class);
        $this->register(RoutingServiceProvider::class);
        $this->register(ViewServiceProvider::class);
        $this->register(DatabaseServiceProvider::class);
        $this->register(ValidationServiceProvider::class);
    }

    /**
     * Join paths without duplicate slashes.
     */
    private function joinPath(string $base, string $path): string
    {
        if ($path === '') {
            return $base;
        }

        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
