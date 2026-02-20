<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/includes/env.php';
app_env_load(__DIR__ . '/../.env');

$dbHost = app_env('DB_HOST', 'localhost');
$dbPort = (int) app_env('DB_PORT', '3306');
$dbName = app_env('DB_NAME', 'Aliviado_db');
$dbUser = app_env('DB_USER', 'root');
$dbPass = app_env('DB_PASS', '');

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Database connection failed.');
}
$conn->set_charset('utf8mb4');
