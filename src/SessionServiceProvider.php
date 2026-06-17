<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\Session\SessionManager;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(SessionManager::class, function () {
            $config = [];

            if ($this->app->bound('config')) {
                $config = (array) $this->app->config()->get('session', []);
            }

            if (($config['driver'] ?? 'file') === 'file' && !isset($config['files'])) {
                $config['files'] = $this->app->storagePath('framework' . DIRECTORY_SEPARATOR . 'sessions');
            }

            return new SessionManager($config);
        });

        $this->app->singleton('session', function () {
            return $this->app->make(SessionManager::class);
        });
    }
}
