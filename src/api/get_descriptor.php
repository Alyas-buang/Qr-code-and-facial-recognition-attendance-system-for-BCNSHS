<?php

declare(strict_types=1);

header('Content-Type: application/json');
include __DIR__ . '/../../database/db.php';

$studentId = trim((string) ($_GET['student_id'] ?? ''));
if ($studentId === '') {
    echo '[]';
    exit();
}

$stmt = $conn->prepare('SELECT face_descriptor FROM students WHERE student_id = ?');
$stmt->bind_param('s', $studentId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

echo $res['face_descriptor'] ?? '[]';
