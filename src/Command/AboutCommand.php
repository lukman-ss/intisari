<?php

declare(strict_types=1);

namespace Intisari\Command;

use Lukman\Console\Command;
use Lukman\Console\Input;
use Lukman\Console\Output;
use Intisari\Application;

class AboutCommand extends Command
{
    protected string $name = 'about';
    protected string $description = 'Display application information';

    public function __construct(protected Application $app)
    {
    }

    /**
     * Execute the command.
     */
    public function handle(Input $input, Output $output): int
    {
        $output->writeln('Framework: Intisari');
        $output->writeln('Version: ' . $this->app->version());
        $output->writeln('Environment: ' . $this->app->environment());

        return self::SUCCESS;
    }
}
