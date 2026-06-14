<?php

declare(strict_types=1);

namespace Intisari;

use Lukman\Console\CommandInterface;
use Lukman\Console\Input;
use Lukman\Console\Output;

class ConsoleKernel
{
    public function __construct(protected Application $app)
    {
        $this->bootstrap();
    }

    /**
     * Bootstrap the kernel by registering default commands.
     */
    protected function bootstrap(): void
    {
        $this->addBuiltInCommand(new Command\AboutCommand($this->app));
        $this->addBuiltInCommand(new Command\ConfigCacheCommand($this->app));
        $this->addBuiltInCommand(new Command\ConfigClearCommand($this->app));
        $this->addBuiltInCommand(new Command\RouteListCommand($this->app));
    }

    protected function addBuiltInCommand(CommandInterface $command): void
    {
        $console = $this->app->console();

        if (!$console->registry()->has($command->name())) {
            $console->add($command);
        }
    }

    /**
     * Get the application instance.
     */
    public function app(): Application
    {
        return $this->app;
    }

    /**
     * Handle the incoming console command.
     */
    public function handle(?Input $input = null, ?Output $output = null): int
    {
        return $this->app->console()->run($input, $output);
    }
}
