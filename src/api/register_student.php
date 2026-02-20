<?php

declare(strict_types=1);

ob_start();
header('Content-Type: application/json');

include __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../public/admin/auth.php';
require_once __DIR__ . '/../assets/libs/phpqrcode/qrlib.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No data received.']);
    exit();
}

$studentId = trim((string) ($data['student_id'] ?? ''));
$fullname = trim((string) ($data['fullname'] ?? ''));
$grade = trim((string) ($data['grade'] ?? ''));
$parentEmail = trim((string) ($data['parent_email'] ?? ''));
$descriptor = $data['descriptor'] ?? null;
$csrfToken = (string) ($data['csrf_token'] ?? '');

if (!admin_is_logged_in() || !csrf_validate($csrfToken)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
    exit();
}

if ($studentId === '' || $fullname === '' || $parentEmail === '' || !is_array($descriptor)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

if (!preg_match('/^[A-Za-z0-9\-_]+$/', $studentId)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid student ID format.']);
    exit();
}

if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid parent email.']);
    exit();
}

if (count($descriptor) !== 128) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid face descriptor.']);
    exit();
}

foreach ($descriptor as $value) {
    if (!is_numeric($value)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid face descriptor values.']);
        exit();
    }
}

$qrCodeValue = 'BCNSHS-' . bin2hex(random_bytes(16));
$descriptorJson = json_encode(array_map('floatval', $descriptor));

$stmt = $conn->prepare(
    'INSERT INTO students
     (student_id, fullname, grade_section, parent_email, face_descriptor, qr_code)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('ssssss', $studentId, $fullname, $grade, $parentEmail, $descriptorJson, $qrCodeValue);

if (!$stmt->execute()) {
    ob_end_clean();
    error_log('Register student failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Student ID may already exist.']);
    exit();
}

$folder = __DIR__ . '/../../public/assets/qrcodes/';
if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Failed to prepare QR output directory.']);
    exit();
}

$safeFileName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $studentId) . '.png';
$filePath = $folder . $safeFileName;
QRcode::png($qrCodeValue, $filePath, 'H', 10, 2);
@chmod($filePath, 0644);

ob_end_clean();
echo json_encode([
    'success' => true,
    'qr_value' => $qrCodeValue
]);
