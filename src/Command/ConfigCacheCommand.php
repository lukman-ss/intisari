<?php

declare(strict_types=1);

namespace Intisari\Command;

use Lukman\Console\Command;
use Lukman\Console\Input;
use Lukman\Console\Output;
use Intisari\Application;

class ConfigCacheCommand extends Command
{
    protected string $name = 'config:cache';
    protected string $description = 'Create a cache file for faster configuration loading';

    public function __construct(protected Application $app)
    {
    }

    /**
     * Execute the command.
     */
    public function handle(Input $input, Output $output): int
    {
        $path = $this->app->storagePath('cache' . DIRECTORY_SEPARATOR . 'config.php');
        
        try {
            $this->app->config()->cacheTo($path);
            $output->writeln('Configuration cached successfully.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $output->errorLine('Failed to cache configuration: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
