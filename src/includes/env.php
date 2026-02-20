<?php

declare(strict_types=1);

/**
 * Tiny .env loader for local development.
 */
function app_env_load(string $envPath): void
{
    static $loaded = false;
    if ($loaded || !is_file($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $quoted = strlen($value) >= 2 &&
            (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
             ($value[0] === "'" && $value[strlen($value) - 1] === "'"));
        if ($quoted) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    $loaded = true;
}

function app_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}
