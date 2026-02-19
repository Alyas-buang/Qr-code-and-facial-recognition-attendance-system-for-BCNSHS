<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require __DIR__ . "/../assets/libs/PHPMailer/src/Exception.php";
require __DIR__ . "/../assets/libs/PHPMailer/src/PHPMailer.php";
require __DIR__ . "/../assets/libs/PHPMailer/src/SMTP.php";
include __DIR__ . "/../../database/db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "No data received."]);
    exit();
}

$sid = $data['student_id'] ?? '';
$email = $data['parent_email'] ?? '';
$photoData = $data['photo'] ?? '';

if ($sid === '' || $email === '' || $photoData === '') {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit();
}

$currentDate = date("m-d-Y");
$currentTime = date("h:i A");
$dbDate = date("Y-m-d");
$dbTime = date("H:i:s");
$method = "Face Recognition";

$check = $conn->prepare(
    "SELECT id
     FROM attendance
     WHERE student_id = ?
       AND date = ?
       AND time > SUBTIME(NOW(), '00:00:30')"
);
$check->bind_param("ss", $sid, $dbDate);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Duplicate prevented."]);
    exit();
}

$image_parts = explode(";base64,", $photoData);
if (count($image_parts) < 2) {
    echo json_encode(["success" => false, "message" => "Invalid photo data."]);
    exit();
}

$image_base64 = base64_decode($image_parts[1], true);
if ($image_base64 === false) {
    echo json_encode(["success" => false, "message" => "Invalid base64 photo data."]);
    exit();
}

$filename = "attendance_" . $sid . "_" . time() . ".jpg";
$uploadDir = __DIR__ . "/../../public/assets/uploads";
$filepath = $uploadDir . "/" . $filename;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (file_put_contents($filepath, $image_base64) === false) {
    echo json_encode(["success" => false, "message" => "Failed to save photo."]);
    exit();
}

$stmt = $conn->prepare(
    "INSERT INTO attendance (student_id, date, time, photo_path, method)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("sssss", $sid, $dbDate, $dbTime, $filename, $method);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "DB Error: " . $stmt->error]);
    exit();
}

$nameQuery = $conn->prepare("SELECT fullname FROM students WHERE student_id = ?");
$nameQuery->bind_param("s", $sid);
$nameQuery->execute();
$student = $nameQuery->get_result()->fetch_assoc();
$fullname = $student ? $student['fullname'] : $sid;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'deezcookie123@gmail.com';
    $mail->Password = 'zsxi yajm yhak jobt';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('deezcookie123@gmail.com', 'BCNSHS Attendance System');
    $mail->addAddress($email);
    $mail->addAttachment($filepath);

    $mail->isHTML(true);
    $mail->Subject = "Attendance Alert: " . $fullname;
    $mail->Body = "
        <h3>Attendance Notification</h3>
        <p>Good day,</p>
        <p>Your child <b>$fullname</b> has arrived at <b>Campus</b>.</p>
        <p><b>Date:</b> $currentDate<br>
        <b>Time:</b> $currentTime</p>
        <p><i>Real-time verification photo is attached to this email.
        this email is auto-generated do not reply</i></p>";

    $mail->send();
    echo json_encode(["success" => true, "message" => "Attendance logged and email sent."]);
} catch (Exception $e) {
    echo json_encode(["success" => true, "message" => "Logged locally, but email failed."]);
}
?>
