<?php

declare(strict_types=1);

namespace Intisari\Test;

use PHPUnit\Framework\TestCase;
use Intisari\Application;
use Intisari\HttpKernel;
use Intisari\ConsoleKernel;
use Intisari\ServiceProvider;
use Intisari\Exception\IntisariException;

class AutoloadTest extends TestCase
{
    public function testClassesAreAutoloaded(): void
    {
        $this->assertTrue(class_exists(Application::class));
        $this->assertTrue(class_exists(HttpKernel::class));
        $this->assertTrue(class_exists(ConsoleKernel::class));
        $this->assertTrue(class_exists(ServiceProvider::class));
        $this->assertTrue(class_exists(IntisariException::class));
    }
}
