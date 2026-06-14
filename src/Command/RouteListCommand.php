<?php

declare(strict_types=1);

namespace Intisari\Command;

use Lukman\Console\Command;
use Lukman\Console\Input;
use Lukman\Console\Output;
use Intisari\Application;

class RouteListCommand extends Command
{
    protected string $name = 'route:list';
    protected string $description = 'List all registered routes';

    public function __construct(protected Application $app)
    {
    }

    /**
     * Execute the command.
     */
    public function handle(Input $input, Output $output): int
    {
        try {
            $routes = $this->app->router()->routes()->all();
            
            if (empty($routes)) {
                $output->writeln('No routes registered.');
                return self::SUCCESS;
            }

            foreach ($routes as $route) {
                $methods = implode('|', $route->methods());
                $path = $route->path();
                $output->writeln("{$methods} {$path}");
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('Route list is not supported or encountered an error.');
            return self::SUCCESS;
        }
    }
}
