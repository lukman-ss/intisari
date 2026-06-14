# IntisariPHP

IntisariPHP is a framework core package for building small PHP applications. It provides the core application object and integrates routing, HTTP responses, configuration, views, database access, validation, console commands, middleware, exception handling, helpers, and testing utilities.

This package is the framework core only. The starter project skeleton is separate and is not created inside this package.

## Requirements

- PHP 8.2 or higher

## Installation

```bash
composer require lukman-ss/intisari
```

## Minimal Application

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Intisari\Application;

$app = new Application(__DIR__);

$app->get('/', function () {
    return 'Hello IntisariPHP';
});

$app->run();
```

## GET Route

```php
use Intisari\Application;
use Lukman\Http\Request;

$app = new Application(__DIR__);

$app->get('/hello', function () {
    return 'Hello';
});

$response = $app->handle(new Request('GET', '/hello'));
```

## Global Middleware

```php
use Intisari\Application;
use Lukman\Http\MiddlewareInterface;
use Lukman\Http\Request;
use Lukman\Http\RequestHandlerInterface;
use Lukman\Http\Response;

final class AddHeaderMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        return $handler->handle($request)->header('X-App', 'Intisari');
    }
}

$app = new Application(__DIR__);
$app->middleware(AddHeaderMiddleware::class);
```

## Config Load

```php
use Intisari\Application;

$app = new Application(__DIR__);
$app->loadConfiguration(__DIR__ . '/config');

$name = $app->config()->get('app.name', 'Intisari');
```

## View Render

```php
use Intisari\Application;

$app = new Application(__DIR__);
$app->config()->set('view.paths', [__DIR__ . '/resources/views']);

$html = $app->render('home', ['name' => 'Lukman']);
```

For `home`, create `resources/views/home.php`:

```php
<h1>Hello, <?= $name ?></h1>
```

## SQLite Database

```php
use Intisari\Application;

$app = new Application(__DIR__);

$app->config()->set('database.connections', [
    'sqlite' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ],
]);
$app->config()->set('database.default', 'sqlite');

$db = $app->db();
$db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$db->affectingStatement('INSERT INTO users (name) VALUES (?)', ['Lukman']);

$users = $db->select('SELECT * FROM users');
```

## Validation

```php
use Intisari\Application;
use Lukman\Validation\Exception\ValidationException;

$app = new Application(__DIR__);

try {
    $validated = $app->validate(
        ['email' => 'demo@example.com'],
        ['email' => 'required']
    );
} catch (ValidationException $e) {
    $errors = $e->errors();
}
```

## Console Command

```php
use Intisari\Application;
use Lukman\Console\Command;
use Lukman\Console\Input;
use Lukman\Console\Output;

$app = new Application(__DIR__);

$app->command('hello', function (Input $input, Output $output): int {
    $output->writeln('Hello console');

    return Command::SUCCESS;
}, 'Print hello');

$exitCode = $app->runConsole();
```

## Exception Debug

```php
use Intisari\Application;
use Lukman\Http\Request;

$app = new Application(__DIR__);
$app->debug(true);

$response = $app->handle(new Request('GET', '/missing-route'));
```

When debug is enabled, exception responses include more detail. When debug is disabled, framework exceptions return concise HTTP responses.

## Testing Utilities

```php
use Intisari\Application;
use Lukman\Http\Request;

$app = new Application(__DIR__);
$app->get('/health', fn () => 'OK');

$app->test(new Request('GET', '/health'))
    ->assertStatus(200)
    ->assertSee('OK');
```

## Helpers

Optional helper functions are loaded by Composer:

```php
use Intisari\Application;

$app = new Application(__DIR__);
$app->setAsGlobal();

$value = config('app.name', 'Intisari');
$html = view('home', ['name' => 'Lukman']);
$response = response('OK');
$redirect = redirect('/login');
```

## Tests

```bash
composer test
```
