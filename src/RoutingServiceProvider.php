<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\Router\Router;

class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('router', function () {
            return new Router();
        });

        $this->app->singleton(Router::class, function () {
            return $this->app->make('router');
        });
    }
}
