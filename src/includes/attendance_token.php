<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Create a signed attendance token signature.
 *
 * @throws RuntimeException
 */
function attendance_sign(string $studentId, int $timestamp, ?string $secret = null): string
{
    $resolvedSecret = $secret ?? app_env_required('APP_SECRET');
    return hash_hmac('sha256', $studentId . '|' . $timestamp, $resolvedSecret);
}

/**
 * Issue a short-lived attendance token.
 *
 * @return array{iat:int,sig:string}
 * @throws RuntimeException
 */
function attendance_token_issue(string $studentId, ?int $issuedAt = null, ?string $secret = null): array
{
    $iat = $issuedAt ?? time();
    return [
        'iat' => $iat,
        'sig' => attendance_sign($studentId, $iat, $secret),
    ];
}

/**
 * Validate attendance token integrity and freshness.
 *
 * @throws RuntimeException
 */
function attendance_token_valid(
    string $studentId,
    array $token,
    int $ttlSeconds = 180,
    ?int $now = null,
    ?string $secret = null
): bool {
    if (!isset($token['iat'], $token['sig'])) {
        return false;
    }

    $iat = (int) $token['iat'];
    $sig = (string) $token['sig'];
    $currentTs = $now ?? time();

    if ($iat <= 0 || ($currentTs - $iat) > $ttlSeconds || $iat > ($currentTs + 30)) {
        return false;
    }

    $expected = attendance_sign($studentId, $iat, $secret);
    return hash_equals($expected, $sig);
}
