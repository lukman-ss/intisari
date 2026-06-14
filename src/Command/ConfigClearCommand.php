<?php

declare(strict_types=1);

namespace Intisari\Command;

use Lukman\Console\Command;
use Lukman\Console\Input;
use Lukman\Console\Output;
use Intisari\Application;

class ConfigClearCommand extends Command
{
    protected string $name = 'config:clear';
    protected string $description = 'Remove the configuration cache file';

    public function __construct(protected Application $app)
    {
    }

    /**
     * Execute the command.
     */
    public function handle(Input $input, Output $output): int
    {
        $path = $this->app->storagePath('cache' . DIRECTORY_SEPARATOR . 'config.php');
        
        if (is_file($path)) {
            if (unlink($path)) {
                $output->writeln('Configuration cache cleared successfully.');
                return self::SUCCESS;
            } else {
                $output->errorLine('Failed to clear configuration cache.');
                return self::FAILURE;
            }
        }

        $output->writeln('No configuration cache file found.');
        return self::SUCCESS;
    }
}
