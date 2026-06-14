<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\Validation\ValidatorFactory;

class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(ValidatorFactory::class, function () {
            return new ValidatorFactory();
        });

        $this->app->singleton('validator', function () {
            return $this->app->make(ValidatorFactory::class);
        });
    }
}
