<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\Config\Config;

class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('config', function () {
            return new Config();
        });

        $this->app->singleton(Config::class, function () {
            return $this->app->make('config');
        });
    }
}
