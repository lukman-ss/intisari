<?php

declare(strict_types=1);

namespace Intisari\Testing;

use Intisari\Application;

trait CreatesApplication
{
    /**
     * @var array<int, string>
     */
    private array $intisariTemporaryBasePaths = [];

    public function createApplication(): Application
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'intisari_app_' . uniqid();

        if (!mkdir($basePath) && !is_dir($basePath)) {
            throw new \RuntimeException(sprintf('Unable to create temporary application path [%s].', $basePath));
        }

        $this->intisariTemporaryBasePaths[] = $basePath;

        return new Application($basePath);
    }

    public function __destruct()
    {
        foreach ($this->intisariTemporaryBasePaths as $basePath) {
            $this->removeIntisariTemporaryPath($basePath);
        }
    }

    private function removeIntisariTemporaryPath(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                $this->removeIntisariTemporaryPath($itemPath);
                continue;
            }

            if (is_file($itemPath) || is_link($itemPath)) {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
