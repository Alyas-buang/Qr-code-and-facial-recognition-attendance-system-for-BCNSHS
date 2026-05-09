<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testAppEnvRequiredReturnsValueWhenPresent(): void
    {
        putenv('UNIT_TEST_REQUIRED_ENV=hello');
        $this->assertSame('hello', app_env_required('UNIT_TEST_REQUIRED_ENV'));
    }

    public function testAppEnvRequiredThrowsWhenMissing(): void
    {
        putenv('UNIT_TEST_MISSING_ENV');
        $this->expectException(RuntimeException::class);
        app_env_required('UNIT_TEST_MISSING_ENV');
    }
}
