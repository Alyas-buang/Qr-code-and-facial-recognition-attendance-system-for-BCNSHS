<?php
session_start();

const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD_HASH = '$2y$10$Sc4liMXAeHKYo3Q.COBozOHQdzMS9bc.7frbEeYo.AmSZk7m3nn0.';

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function admin_login(string $username, string $password): bool
{
    if ($username !== ADMIN_USERNAME) {
        return false;
    }

    if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
        return false;
    }

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = ADMIN_USERNAME;
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

