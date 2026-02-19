<?php
header('Content-Type: application/json');

include "../../database/db.php";

$code = $_GET['code'] ?? '';

$hasDisableColumn = false;
$colRes = $conn->query("SHOW COLUMNS FROM students LIKE 'is_disabled'");
if ($colRes && $colRes->num_rows > 0) {
    $hasDisableColumn = true;
}

if ($hasDisableColumn) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE qr_code = ? AND is_disabled = 0");
} else {
    $stmt = $conn->prepare("SELECT * FROM students WHERE qr_code = ?");
}
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "success" => true,
        "student_id" => $row['student_id'],
        "fullname" => $row['fullname'],
        "parent_email" => $row['parent_email'],
        "grade_section" => $row['grade_section'],
        "descriptor" => json_decode($row['face_descriptor'])
    ]);
} else {
    echo json_encode(["success" => false]);
}
?>
