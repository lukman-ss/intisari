<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\Database\DatabaseManager;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(DatabaseManager::class, function () {
            $manager = new DatabaseManager();

            if ($this->app->bound('config')) {
                $connections = $this->app->config()->get('database.connections', []);
                $default = $this->app->config()->get('database.default');

                foreach ($connections as $name => $config) {
                    $isDefault = ($name === $default);
                    $manager->addConnection($name, $config, $isDefault);
                }

                if ($default !== null && isset($connections[$default])) {
                    $manager->setDefaultConnection($default);
                }
            }

            return $manager;
        });

        $this->app->singleton('db', function () {
            return $this->app->make(DatabaseManager::class);
        });
    }
}
