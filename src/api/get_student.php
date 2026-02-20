<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../src/includes/env.php';
include __DIR__ . '/../../database/db.php';

app_env_load(__DIR__ . '/../../.env');

function attendance_sign(string $studentId, int $timestamp): string
{
    $secret = app_env('APP_SECRET', 'local-dev-secret-change-me') ?? 'local-dev-secret-change-me';
    return hash_hmac('sha256', $studentId . '|' . $timestamp, $secret);
}

$code = trim($_GET['code'] ?? '');
if ($code === '' || strlen($code) > 255) {
    echo json_encode(['success' => false, 'message' => 'Invalid QR code.']);
    exit();
}

$hasDisableColumn = false;
$colRes = $conn->query("SHOW COLUMNS FROM students LIKE 'is_disabled'");
if ($colRes && $colRes->num_rows > 0) {
    $hasDisableColumn = true;
}

if ($hasDisableColumn) {
    $stmt = $conn->prepare("SELECT student_id, fullname, parent_email, grade_section, face_descriptor FROM students WHERE qr_code = ? AND is_disabled = 0");
} else {
    $stmt = $conn->prepare("SELECT student_id, fullname, parent_email, grade_section, face_descriptor FROM students WHERE qr_code = ?");
}
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $issuedAt = time();
    echo json_encode([
        'success' => true,
        'student_id' => $row['student_id'],
        'fullname' => $row['fullname'],
        'parent_email' => $row['parent_email'],
        'grade_section' => $row['grade_section'],
        'descriptor' => json_decode($row['face_descriptor'], true),
        'attendance_token' => [
            'iat' => $issuedAt,
            'sig' => attendance_sign((string) $row['student_id'], $issuedAt)
        ]
    ]);
} else {
    echo json_encode(['success' => false]);
}
