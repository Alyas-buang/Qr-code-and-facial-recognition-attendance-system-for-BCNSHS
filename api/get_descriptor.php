<?php
include "../../../database/db.php";

$student_id = $_GET['student_id'] ?? '';

$stmt = $conn->prepare("SELECT face_descriptor FROM students WHERE student_id=?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

echo $res['face_descriptor'] ?? '[]'; // safe fallback
?>
