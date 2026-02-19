<?php
ob_start();
header('Content-Type: application/json');

include __DIR__ . "/../../database/db.php";
require_once __DIR__ . "/../assets/libs/phpqrcode/qrlib.php";

$data = json_decode(file_get_contents("php://input"), true);

if ($data) {

    $student_id = $data['student_id'];
    $fullname = $data['fullname'];
    $grade = $data['grade'];
    $parent_email = $data['parent_email'];
    $descriptor = json_encode($data['descriptor']);

    $qr_code_value = uniqid("BCNHSSHS-");

    $stmt = $conn->prepare(
        "INSERT INTO students 
        (student_id, fullname, grade_section, parent_email, face_descriptor, qr_code)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "ssssss",
        $student_id,
        $fullname,
        $grade,
        $parent_email,
        $descriptor,
        $qr_code_value
    );

    if ($stmt->execute()) {

        $folder = __DIR__ . "/../../public/assets/qrcodes/";

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $file_name = $student_id . ".png";
        $file_path = $folder . $file_name;

        QRcode::png($qr_code_value, $file_path, 'H', 10, 2);

        ob_end_clean();
        echo json_encode([
            "success" => true,
            "qr_value" => $qr_code_value
        ]);

    } else {

        ob_end_clean();
        echo json_encode([
            "success" => false,
            "message" => "Database Error: " . $stmt->error
        ]);
    }

} else {

    ob_end_clean();
    echo json_encode([
        "success" => false,
        "message" => "No data received."
    ]);
}
?>
