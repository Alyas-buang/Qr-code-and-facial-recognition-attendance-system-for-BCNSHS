<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function attendance_app_timezone(): DateTimeZone
{
    $timezone = app_env('APP_TIMEZONE', 'Asia/Manila') ?? 'Asia/Manila';

    try {
        return new DateTimeZone($timezone);
    } catch (Throwable $e) {
        return new DateTimeZone('Asia/Manila');
    }
}

function attendance_late_cutoff(): string
{
    $cutoff = trim((string) (app_env('ATTENDANCE_LATE_TIME', '08:00:00') ?? '08:00:00'));
    if (preg_match('/^\d{2}:\d{2}$/', $cutoff)) {
        return $cutoff . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $cutoff)) {
        return $cutoff;
    }

    return '08:00:00';
}

function attendance_status_from_time(string $time): string
{
    return $time <= attendance_late_cutoff() ? 'Present' : 'Late';
}
