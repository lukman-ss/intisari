<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\View\ViewFactory;
use Lukman\View\FileViewFinder;
use Lukman\View\PhpEngine;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(ViewFactory::class, function () {
            $paths = [];
            $defaultPath = $this->app->basePath('resources' . DIRECTORY_SEPARATOR . 'views');

            if ($this->app->bound('config') && $this->app->config()->has('view.paths')) {
                $paths = (array) $this->app->config()->get('view.paths');
            } else {
                $paths = [$defaultPath];
            }

            $finder = new FileViewFinder($paths);
            $engine = new PhpEngine();

            return new ViewFactory($finder, $engine);
        });

        $this->app->singleton('view', function () {
            return $this->app->make(ViewFactory::class);
        });
    }
}
