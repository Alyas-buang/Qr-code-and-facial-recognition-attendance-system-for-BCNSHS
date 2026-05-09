<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AttendanceStatusTest extends TestCase
{
    public function testReturnsPresentWhenTimeIsOnOrBeforeCutoff(): void
    {
        $this->assertSame('Present', attendance_status_from_time('08:00:00'));
        $this->assertSame('Present', attendance_status_from_time('07:59:59'));
    }

    public function testReturnsLateWhenTimeIsAfterCutoff(): void
    {
        $this->assertSame('Late', attendance_status_from_time('08:00:01'));
    }
}
