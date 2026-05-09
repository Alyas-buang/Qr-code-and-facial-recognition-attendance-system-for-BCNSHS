<?php

declare(strict_types=1);

ob_start();
header('Content-Type: application/json');

include __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../public/admin/auth.php';
require_once __DIR__ . '/../../src/includes/security.php';
require_once __DIR__ . '/../../src/includes/face_utils.php';
require_once __DIR__ . '/../assets/libs/phpqrcode/qrlib.php';

app_no_cache_headers();

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    ob_end_clean();
    app_json_response(['success' => false, 'message' => 'No data received.'], 400);
}

$studentId = trim((string) ($data['student_id'] ?? ''));
$fullname = trim((string) ($data['fullname'] ?? ''));
$grade = trim((string) ($data['grade'] ?? ''));
$parentEmail = trim((string) ($data['parent_email'] ?? ''));
$descriptor = $data['descriptor'] ?? null;
$csrfToken = (string) ($data['csrf_token'] ?? '');

if (!admin_is_logged_in() || !csrf_validate($csrfToken)) {
    ob_end_clean();
    app_json_response(['success' => false, 'message' => 'Unauthorized request.'], 403);
}

if ($studentId === '' || $fullname === '' || $parentEmail === '' || !is_array($descriptor)) {
    ob_end_clean();
    app_json_response(['success' => false, 'message' => 'Missing required fields.'], 400);
}

if (!preg_match('/^[A-Za-z0-9\-_]+$/', $studentId)) {
    ob_end_clean();
    app_json_response(['success' => false, 'message' => 'Invalid student ID format.'], 400);
}

if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    app_json_response(['success' => false, 'message' => 'Invalid parent email.'], 400);
}

$inputDescriptor = face_descriptor_normalize($descriptor);
if ($inputDescriptor === null) {
    ob_end_clean();
    app_json_response(['success' => false, 'message' => 'Invalid face descriptor.'], 400);
}

$defaultDuplicateThreshold = 0.60;
$duplicateFaceThreshold = $defaultDuplicateThreshold;
$configuredThreshold = app_env('FACE_DUPLICATE_THRESHOLD', (string) $defaultDuplicateThreshold);
if (is_numeric($configuredThreshold)) {
    $duplicateFaceThreshold = max(0.30, min(1.00, (float) $configuredThreshold));
}

$fullnameNormalized = face_name_normalize($fullname);
$inputNorm = face_descriptor_norm($inputDescriptor);

$hasNormColumn = false;
$normColRes = $conn->query("SHOW COLUMNS FROM students LIKE 'face_descriptor_norm'");
if ($normColRes && $normColRes->num_rows > 0) {
    $hasNormColumn = true;
}

if ($hasNormColumn) {
    $minNorm = max(0.0, $inputNorm - $duplicateFaceThreshold);
    $maxNorm = $inputNorm + $duplicateFaceThreshold;
    $dupStmt = $conn->prepare(
        'SELECT student_id, fullname, face_descriptor
         FROM students
         WHERE face_descriptor_norm BETWEEN ? AND ?
            OR face_descriptor_norm IS NULL'
    );
    if (!$dupStmt) {
        ob_end_clean();
        app_json_response(['success' => false, 'message' => 'Failed to prepare duplicate check query.'], 500);
    }
    $dupStmt->bind_param('dd', $minNorm, $maxNorm);
    $dupStmt->execute();
    $dupRes = $dupStmt->get_result();
} else {
    $dupRes = $conn->query('SELECT student_id, fullname, face_descriptor FROM students');
}

if ($dupRes) {
    while ($existing = $dupRes->fetch_assoc()) {
        $existingName = (string) ($existing['fullname'] ?? '');
        $existingNameNormalized = face_name_normalize($existingName);
        $existingDescriptorRaw = json_decode((string) ($existing['face_descriptor'] ?? ''), true);
        $existingDescriptor = is_array($existingDescriptorRaw) ? face_descriptor_normalize($existingDescriptorRaw) : null;
        if ($existingDescriptor === null) {
            continue;
        }

        $distance = face_descriptor_distance($inputDescriptor, $existingDescriptor);
        if ($distance < $duplicateFaceThreshold) {
            $sameName = $existingNameNormalized === $fullnameNormalized;
            ob_end_clean();
            app_json_response([
                'success' => false,
                'message' => $sameName
                    ? 'This face is already registered. Use the existing student record instead of creating another one.'
                    : 'This face already matches an existing student record with a different name.',
                'conflict_student_id' => (string) ($existing['student_id'] ?? ''),
                'conflict_fullname' => $existingName,
                'distance' => round($distance, 4),
            ], 409);
        }
    }
}

$qrCodeValue = 'BCNSHS-' . bin2hex(random_bytes(16));
$descriptorJson = json_encode($inputDescriptor);
if ($descriptorJson === false) {
    ob_end_clean();
    app_json_response(['success' => false, 'message' => 'Unable to encode descriptor.'], 500);
}

$folder = __DIR__ . '/../../public/assets/qrcodes/';
if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
    ob_end_clean();
    app_json_response(['success' => false, 'message' => 'Failed to prepare QR output directory.'], 500);
}

$safeFileName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $studentId) . '.png';
$filePath = $folder . $safeFileName;
$tempFilePath = $filePath . '.tmp.' . bin2hex(random_bytes(5));
$createdFinalFile = false;

try {
    $conn->begin_transaction();

    if ($hasNormColumn) {
        $insertStmt = $conn->prepare(
            'INSERT INTO students
             (student_id, fullname, grade_section, parent_email, face_descriptor, face_descriptor_norm, qr_code)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$insertStmt) {
            throw new RuntimeException('Failed to prepare student insert statement.');
        }
        $insertStmt->bind_param('sssssds', $studentId, $fullname, $grade, $parentEmail, $descriptorJson, $inputNorm, $qrCodeValue);
    } else {
        $insertStmt = $conn->prepare(
            'INSERT INTO students
             (student_id, fullname, grade_section, parent_email, face_descriptor, qr_code)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$insertStmt) {
            throw new RuntimeException('Failed to prepare student insert statement.');
        }
        $insertStmt->bind_param('ssssss', $studentId, $fullname, $grade, $parentEmail, $descriptorJson, $qrCodeValue);
    }

    if (!$insertStmt->execute()) {
        throw new RuntimeException('Registration failed. Student ID may already exist.');
    }

    QRcode::png($qrCodeValue, $tempFilePath, 'H', 10, 2);
    if (!is_file($tempFilePath)) {
        throw new RuntimeException('QR code generation failed.');
    }

    if (!@rename($tempFilePath, $filePath)) {
        $tmpContents = file_get_contents($tempFilePath);
        if ($tmpContents === false || file_put_contents($filePath, $tmpContents, LOCK_EX) === false) {
            throw new RuntimeException('Failed to save generated QR file.');
        }
        @unlink($tempFilePath);
    }
    $createdFinalFile = true;
    @chmod($filePath, 0644);

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    if (is_file($tempFilePath)) {
        @unlink($tempFilePath);
    }
    if ($createdFinalFile && is_file($filePath)) {
        @unlink($filePath);
    }

    error_log('Register student failed: ' . $e->getMessage());
    ob_end_clean();
    app_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

ob_end_clean();
app_json_response([
    'success' => true,
    'qr_value' => $qrCodeValue,
]);
