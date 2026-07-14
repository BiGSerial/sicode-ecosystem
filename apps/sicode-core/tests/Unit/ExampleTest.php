<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_php_runtime_meets_core_requirement(): void
    {
        $this->assertGreaterThanOrEqual(80400, PHP_VERSION_ID);
    }
}
