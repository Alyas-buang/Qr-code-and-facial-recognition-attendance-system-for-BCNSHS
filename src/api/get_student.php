<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/includes/env.php';
require_once __DIR__ . '/../../src/includes/security.php';
require_once __DIR__ . '/../../src/includes/attendance_token.php';
require_once __DIR__ . '/../../src/includes/face_utils.php';
include __DIR__ . '/../../database/db.php';

header('Content-Type: application/json');
app_no_cache_headers();

app_env_load(__DIR__ . '/../../.env');

try {
    app_env_required('APP_SECRET');
} catch (RuntimeException $e) {
    error_log('Configuration error in get_student.php: ' . $e->getMessage());
    app_json_response(['success' => false, 'message' => 'Server configuration error.'], 500);
}

$code = trim($_GET['code'] ?? '');
if ($code === '' || strlen($code) > 255) {
    app_json_response(['success' => false, 'message' => 'Invalid QR code.'], 400);
}

$hasDisableColumn = false;
$colRes = $conn->query("SHOW COLUMNS FROM students LIKE 'is_disabled'");
if ($colRes && $colRes->num_rows > 0) {
    $hasDisableColumn = true;
}

if ($hasDisableColumn) {
    $stmt = $conn->prepare("SELECT student_id, fullname, grade_section, face_descriptor FROM students WHERE qr_code = ? AND is_disabled = 0");
} else {
    $stmt = $conn->prepare("SELECT student_id, fullname, grade_section, face_descriptor FROM students WHERE qr_code = ?");
}
if (!$stmt) {
    error_log('get_student prepare failed: ' . $conn->error);
    app_json_response(['success' => false, 'message' => 'Failed to prepare query.'], 500);
}

$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $descriptor = json_decode((string) ($row['face_descriptor'] ?? ''), true);
    $normalizedDescriptor = is_array($descriptor) ? face_descriptor_normalize($descriptor) : null;
    if ($normalizedDescriptor === null) {
        app_json_response(['success' => false, 'message' => 'Stored face descriptor is invalid.'], 500);
    }

    app_json_response([
        'success' => true,
        'student_id' => $row['student_id'],
        'fullname' => $row['fullname'],
        'grade_section' => $row['grade_section'],
        'descriptor' => $normalizedDescriptor,
        'attendance_token' => attendance_token_issue((string) $row['student_id'])
    ]);
} else {
    app_json_response(['success' => false], 404);
}
