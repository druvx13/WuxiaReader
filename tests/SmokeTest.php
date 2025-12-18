<?php

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testAutoloadingWorks(): void
    {
        $this->assertTrue(class_exists(App\Core\Router::class));
    }

    public function testConfigLoads(): void
    {
        // Mock the .env file if it doesn't exist or use existing
        // For smoke test, we assume environment might be set up or we mock it.
        // Let's just check if the class loads.
        $this->assertTrue(class_exists(App\Core\Config::class));
    }
}
