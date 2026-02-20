<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/includes/env.php';
require_once __DIR__ . '/../../src/includes/security.php';

app_env_load(__DIR__ . '/../../.env');
app_session_bootstrap();

const LOGIN_ATTEMPT_WINDOW_SECONDS = 300;
const LOGIN_ATTEMPT_MAX = 5;
const LOGIN_LOCK_SECONDS = 300;

function admin_username(): string
{
    return app_env('ADMIN_USERNAME', 'admin') ?? 'admin';
}

function admin_password_hash(): string
{
    $fallback = '$2y$10$Sc4liMXAeHKYo3Q.COBozOHQdzMS9bc.7frbEeYo.AmSZk7m3nn0.';
    return app_env('ADMIN_PASSWORD_HASH', $fallback) ?? $fallback;
}

function admin_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return preg_replace('/[^a-zA-Z0-9\:\.\-]/', '_', $ip) ?: 'unknown';
}

function admin_attempt_file(): string
{
    return sys_get_temp_dir() . '/bcnshs_admin_login_' . admin_client_ip() . '.json';
}

function admin_read_attempts(): array
{
    $file = admin_attempt_file();
    if (!is_file($file)) {
        return ['fails' => [], 'locked_until' => 0];
    }

    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return ['fails' => [], 'locked_until' => 0];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['fails' => [], 'locked_until' => 0];
    }

    $fails = isset($data['fails']) && is_array($data['fails']) ? $data['fails'] : [];
    $lockedUntil = isset($data['locked_until']) ? (int) $data['locked_until'] : 0;
    return ['fails' => $fails, 'locked_until' => $lockedUntil];
}

function admin_write_attempts(array $data): void
{
    file_put_contents(admin_attempt_file(), json_encode($data), LOCK_EX);
}

function admin_is_rate_limited(): bool
{
    $now = time();
    $state = admin_read_attempts();
    return ((int) $state['locked_until']) > $now;
}

function admin_rate_limit_remaining_seconds(): int
{
    $now = time();
    $state = admin_read_attempts();
    return max(0, ((int) $state['locked_until']) - $now);
}

function admin_record_failed_attempt(): void
{
    $now = time();
    $state = admin_read_attempts();
    $fails = array_values(array_filter(
        array_map('intval', $state['fails']),
        static fn (int $ts): bool => ($now - $ts) <= LOGIN_ATTEMPT_WINDOW_SECONDS
    ));
    $fails[] = $now;

    $lockedUntil = (int) $state['locked_until'];
    if (count($fails) >= LOGIN_ATTEMPT_MAX) {
        $lockedUntil = $now + LOGIN_LOCK_SECONDS;
        $fails = [];
    }

    admin_write_attempts([
        'fails' => $fails,
        'locked_until' => $lockedUntil
    ]);
}

function admin_clear_failed_attempts(): void
{
    $file = admin_attempt_file();
    if (is_file($file)) {
        unlink($file);
    }
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function admin_login(string $username, string $password): bool
{
    if (admin_is_rate_limited()) {
        return false;
    }

    if (!hash_equals(admin_username(), $username)) {
        admin_record_failed_attempt();
        return false;
    }

    if (!password_verify($password, admin_password_hash())) {
        admin_record_failed_attempt();
        return false;
    }

    session_regenerate_id(true);
    admin_clear_failed_attempts();
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = admin_username();
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function admin_require_login(): void
{
    if (admin_is_logged_in()) {
        return;
    }

    $target = urlencode($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
    header("Location: login.php?next={$target}");
    exit();
}
