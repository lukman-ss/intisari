<?php

declare(strict_types=1);

namespace Intisari;

abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Get the application instance.
     */
    public function app(): Application
    {
        return $this->app;
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
