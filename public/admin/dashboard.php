<?php
require_once __DIR__ . "/auth.php";
admin_require_login();
include "../../database/db.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/jpeg" href="../assets/css/logo.jpg">
    <link rel="stylesheet" href="../assets/css/dashboard_styles.css">
</head>
<body>
<button class="back-btn" onclick="goBack()">Back</button>

<div class="top-bar">
    <h2>Attendance Records</h2>
    <div class="action-links">
        <a href="register_student.php" class="action-link action-link-register">+ Register New Student</a>
        <a href="manage_students.php" class="action-link action-link-manage">Manage Students</a>
        <a href="logout.php" class="action-link action-link-logout">Logout</a>
    </div>
</div>

<div class="qr-search-wrap">
    <input
        type="text"
        id="qr-search"
        class="qr-search-input"
        placeholder="Search attendance logs and QR codes by name, ID, section, or email..."
        aria-label="Search attendance logs and QR codes"
    >
</div>

<table>
    <tr>
        <th>Photo</th>
        <th>Student ID</th>
        <th>Name</th>
        <th>Grade & Section</th>
        <th>Date & Time</th>
        <th>Method</th>
        <th>Parent Email</th>
    </tr>

    <?php
    // Updated SQL: Matches your 'Aliviado_db' schema perfectly
    $sql = "SELECT a.student_id, a.date, a.time, a.photo_path, a.method, 
                   s.fullname, s.grade_section, s.parent_email
            FROM attendance a
            INNER JOIN students s ON a.student_id = s.student_id
            ORDER BY a.date DESC, a.time DESC";

    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        while($row = $res->fetch_assoc()){
            // Formatting Date and Time from your separate columns
            $displayDate = date("M d, Y", strtotime($row['date']));
            $displayTime = date("h:i A", strtotime($row['time']));
            $searchBlob = strtolower(
                $row['student_id'] . ' ' .
                $row['fullname'] . ' ' .
                $row['grade_section'] . ' ' .
                $row['parent_email'] . ' ' .
                $displayDate . ' ' .
                $displayTime
            );

            $photoName = htmlspecialchars($row['photo_path']);
            $photoSrc = "../assets/uploads/" . $photoName;

            echo "<tr class='attendance-row' data-search='" . htmlspecialchars($searchBlob, ENT_QUOTES) . "'>";
            echo "<td><img src='{$photoSrc}'></td>";
            echo "<td><b>".htmlspecialchars($row['student_id'])."</b></td>";
            echo "<td>".htmlspecialchars($row['fullname'])."</td>";
            echo "<td>".htmlspecialchars($row['grade_section'])."</td>";
            echo "<td>$displayDate <br><small>$displayTime</small></td>";
            // FIX: Changed 'status' to 'method' to match your CREATE TABLE
            echo "<td><span class='badge'>".htmlspecialchars($row['method'])."</span></td>";
            echo "<td>".htmlspecialchars($row['parent_email'])."</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7' class='empty-row'>No attendance records found. Ensure students have scanned their QR and verified their face.</td></tr>";
    }
    ?>
</table>
<p id="attendance-no-results" class="empty-state attendance-no-results">No matching attendance logs found.</p>

<h2 class="section-title">Student QR Code Preview</h2>
<div class="qr-grid">
    <?php
    $studentSql = "SELECT student_id, fullname FROM students ORDER BY fullname ASC";
    $studentRes = $conn->query($studentSql);

    if ($studentRes && $studentRes->num_rows > 0) {
        while ($student = $studentRes->fetch_assoc()) {
            $sid = htmlspecialchars($student['student_id']);
            $name = htmlspecialchars($student['fullname']);
            $qrSrc = "../assets/qrcodes/" . rawurlencode($student['student_id']) . ".png";
            $searchBlob = strtolower($student['fullname'] . " " . $student['student_id']);
            echo "<div class='qr-card' data-search='" . htmlspecialchars($searchBlob, ENT_QUOTES) . "'>";
            echo "<img class='qr-image' src='{$qrSrc}' alt='QR for {$name}' onerror=\"this.style.display='none'; this.nextElementSibling.style.display='block';\">";
            echo "<p class='qr-missing'>QR not generated yet</p>";
            echo "<p class='qr-student-name'>{$name}</p>";
            echo "<p class='qr-student-id'>{$sid}</p>";
            echo "</div>";
        }
    } else {
        echo "<p class='empty-state'>No registered students found.</p>";
    }
    ?>
</div>
<p id="qr-no-results" class="empty-state qr-no-results">No matching QR code found.</p>

<script>
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    window.location.href = "../../src/home.php";
}

const qrSearchInput = document.getElementById("qr-search");
const attendanceRows = Array.from(document.querySelectorAll(".attendance-row"));
const attendanceNoResults = document.getElementById("attendance-no-results");
const qrCards = Array.from(document.querySelectorAll(".qr-card"));
const qrNoResults = document.getElementById("qr-no-results");

if (qrSearchInput) {
    qrSearchInput.addEventListener("input", function () {
        const query = this.value.trim().toLowerCase();
        let visibleAttendance = 0;
        let visibleCount = 0;

        attendanceRows.forEach(row => {
            const haystack = row.getAttribute("data-search") || "";
            const show = query === "" || haystack.includes(query);
            row.style.display = show ? "" : "none";
            if (show) visibleAttendance++;
        });

        qrCards.forEach(card => {
            const haystack = card.getAttribute("data-search") || "";
            const show = query === "" || haystack.includes(query);
            card.style.display = show ? "" : "none";
            if (show) visibleCount++;
        });

        if (attendanceNoResults) {
            attendanceNoResults.style.display = visibleAttendance === 0 && attendanceRows.length > 0 ? "block" : "none";
        }

        if (qrNoResults) {
            qrNoResults.style.display = visibleCount === 0 && qrCards.length > 0 ? "block" : "none";
        }
    });
}
</script>
<?php include "../../src/includes/footer.php"; ?>
</body>
</html>
