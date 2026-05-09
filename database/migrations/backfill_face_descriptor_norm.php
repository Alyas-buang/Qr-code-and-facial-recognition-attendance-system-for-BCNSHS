<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../src/includes/face_utils.php';

$columnRes = $conn->query("SHOW COLUMNS FROM students LIKE 'face_descriptor_norm'");
if (!$columnRes || $columnRes->num_rows === 0) {
    fwrite(STDERR, "Missing students.face_descriptor_norm column. Run 20260328_security_schema_updates.sql first.\n");
    exit(1);
}

$selectStmt = $conn->prepare(
    'SELECT student_id, face_descriptor
     FROM students
     WHERE face_descriptor_norm IS NULL'
);
if (!$selectStmt) {
    fwrite(STDERR, "Unable to prepare select statement: {$conn->error}\n");
    exit(1);
}
$selectStmt->execute();
$result = $selectStmt->get_result();

$updateStmt = $conn->prepare(
    'UPDATE students SET face_descriptor_norm = ? WHERE student_id = ?'
);
if (!$updateStmt) {
    fwrite(STDERR, "Unable to prepare update statement: {$conn->error}\n");
    exit(1);
}

$updated = 0;
$skipped = 0;
while ($row = $result->fetch_assoc()) {
    $studentId = (string) ($row['student_id'] ?? '');
    $raw = json_decode((string) ($row['face_descriptor'] ?? ''), true);
    $descriptor = is_array($raw) ? face_descriptor_normalize($raw) : null;
    if ($descriptor === null) {
        $skipped++;
        fwrite(STDERR, "Skipping {$studentId}: invalid descriptor format.\n");
        continue;
    }

    $norm = face_descriptor_norm($descriptor);
    $updateStmt->bind_param('ds', $norm, $studentId);
    if (!$updateStmt->execute()) {
        $skipped++;
        fwrite(STDERR, "Failed to update {$studentId}: {$updateStmt->error}\n");
        continue;
    }
    $updated++;
}

fwrite(STDOUT, "Backfill complete. Updated: {$updated}, skipped: {$skipped}\n");
