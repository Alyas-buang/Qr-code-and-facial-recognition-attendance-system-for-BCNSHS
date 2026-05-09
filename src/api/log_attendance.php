<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../../src/includes/env.php';
require_once __DIR__ . '/../../src/includes/security.php';
require_once __DIR__ . '/../../src/includes/attendance_token.php';
require_once __DIR__ . '/../../src/includes/attendance_status.php';
if (is_file(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../assets/libs/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../assets/libs/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../assets/libs/PHPMailer/src/SMTP.php';
}
include __DIR__ . '/../../database/db.php';

app_env_load(__DIR__ . '/../../.env');
app_no_cache_headers();
header('Content-Type: application/json');
date_default_timezone_set(attendance_app_timezone()->getName());

const ATTENDANCE_TOKEN_TTL_SECONDS = 180;
const MAX_IMAGE_BYTES = 3_145_728; // 3MB

try {
    app_env_required('APP_SECRET');
} catch (RuntimeException $e) {
    error_log('Configuration error in log_attendance.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'No data received.']);
    exit();
}

$sid = trim((string) ($data['student_id'] ?? ''));
$photoData = (string) ($data['photo'] ?? '');
$token = $data['attendance_token'] ?? null;

if ($sid === '' || $photoData === '' || !is_array($token)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

if (!attendance_token_valid($sid, $token, ATTENDANCE_TOKEN_TTL_SECONDS)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired verification token.']);
    exit();
}

$currentDate = date('m-d-Y');
$currentTime = date('h:i A');
$dbDate = date('Y-m-d');
$dbTime = date('H:i:s');
$attendanceStatus = attendance_status_from_time($dbTime);
$method = 'Face Recognition';
$hasAttendanceStatusColumn = false;

$statusColRes = $conn->query("SHOW COLUMNS FROM attendance LIKE 'status'");
if ($statusColRes && $statusColRes->num_rows > 0) {
    $hasAttendanceStatusColumn = true;
}

$lockName = 'attendance_' . $sid;
$hasLock = false;
$lockStmt = $conn->prepare('SELECT GET_LOCK(?, 5) AS got_lock');
$lockStmt->bind_param('s', $lockName);
$lockStmt->execute();
$lockRow = $lockStmt->get_result()->fetch_assoc();
$hasLock = isset($lockRow['got_lock']) && (int) $lockRow['got_lock'] === 1;
if (!$hasLock) {
    echo json_encode(['success' => false, 'message' => 'Please retry in a moment.']);
    exit();
}

try {
    $studentStmt = $conn->prepare('SELECT fullname, parent_email FROM students WHERE student_id = ? LIMIT 1');
    $studentStmt->bind_param('s', $sid);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc();
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit();
    }

    $email = (string) ($student['parent_email'] ?? '');
    $fullname = (string) ($student['fullname'] ?? $sid);

    $check = $conn->prepare(
        "SELECT id
         FROM attendance
         WHERE student_id = ?
           AND date = ?
         LIMIT 1"
    );
    $check->bind_param('ss', $sid, $dbDate);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Attendance already logged for today.']);
        exit();
    }

    if (!preg_match('#^data:image/(jpeg|jpg|png);base64,#i', $photoData, $mimeMatch)) {
        echo json_encode(['success' => false, 'message' => 'Unsupported photo format.']);
        exit();
    }

    $imageParts = explode(';base64,', $photoData, 2);
    if (count($imageParts) !== 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid photo data.']);
        exit();
    }

    $imageBase64 = base64_decode($imageParts[1], true);
    if ($imageBase64 === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid base64 photo data.']);
        exit();
    }
    if (strlen($imageBase64) > MAX_IMAGE_BYTES) {
        echo json_encode(['success' => false, 'message' => 'Photo is too large.']);
        exit();
    }

    $ext = strtolower($mimeMatch[1]) === 'png' ? 'png' : 'jpg';
    $safeSid = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sid) ?: 'student';
    $filename = 'attendance_' . $safeSid . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../../public/assets/uploads';
    $filepath = $uploadDir . '/' . $filename;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare upload directory.']);
        exit();
    }

    if (file_put_contents($filepath, $imageBase64, LOCK_EX) === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to save photo.']);
        exit();
    }
    @chmod($filepath, 0644);

    if ($hasAttendanceStatusColumn) {
        $stmt = $conn->prepare(
            'INSERT INTO attendance (student_id, date, time, photo_path, method, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssss', $sid, $dbDate, $dbTime, $filename, $method, $attendanceStatus);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO attendance (student_id, date, time, photo_path, method)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssss', $sid, $dbDate, $dbTime, $filename, $method);
    }
    if (!$stmt->execute()) {
        error_log('Attendance insert failed: ' . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to record attendance.']);
        exit();
    }

    $smtpHost = app_env('SMTP_HOST');
    $smtpUser = app_env('SMTP_USER');
    $smtpPass = app_env('SMTP_PASS');
    $smtpFromEmail = app_env('SMTP_FROM_EMAIL');
    $smtpFromName = app_env('SMTP_FROM_NAME', 'BCNSHS Attendance System') ?? 'BCNSHS Attendance System';

    if ($smtpHost && $smtpUser && $smtpPass && $smtpFromEmail && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $secure = strtolower(app_env('SMTP_SECURE', 'tls') ?? 'tls');
            $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) (app_env('SMTP_PORT', '587') ?? '587');

            $mail->setFrom($smtpFromEmail, $smtpFromName);
            $mail->addAddress($email);
            $mail->addAttachment($filepath);

            $mail->isHTML(true);
            $mail->Subject = 'Attendance Alert: ' . $fullname;
            $mail->Body = "
                <h3>Attendance Notification</h3>
                <p>Good day,</p>
                <p>Your child <b>{$fullname}</b> has arrived at <b>Campus</b>.</p>
                <p><b>Attendance Status:</b> {$attendanceStatus}</p>
                <p><b>Date:</b> {$currentDate}<br>
                <b>Time:</b> {$currentTime}</p>
                <p><i>Real-time verification photo is attached to this email.
                This email is auto-generated. Do not reply.</i></p>";
            $mail->send();
            echo json_encode([
                'success' => true,
                'message' => 'Attendance logged and email sent.',
                'attendance_status' => $attendanceStatus,
            ]);
            exit();
        } catch (Exception $e) {
            error_log('Mailer failed: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Attendance logged.',
        'attendance_status' => $attendanceStatus,
    ]);
} finally {
    if ($hasLock) {
        $releaseStmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
        $releaseStmt->bind_param('s', $lockName);
        $releaseStmt->execute();
    }
}
