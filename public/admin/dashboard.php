<?php
require_once __DIR__ . "/auth.php";
admin_require_login();
include "../../database/db.php";

$totalLogs = 0;
$todayLogs = 0;
$studentsCount = 0;
$latestScanText = "No scans yet";

$countRes = $conn->query("SELECT COUNT(*) AS total_logs FROM attendance");
if ($countRes && ($countRow = $countRes->fetch_assoc())) {
    $totalLogs = (int)$countRow['total_logs'];
}

$todayRes = $conn->query("SELECT COUNT(*) AS today_logs FROM attendance WHERE date = CURDATE()");
if ($todayRes && ($todayRow = $todayRes->fetch_assoc())) {
    $todayLogs = (int)$todayRow['today_logs'];
}

$studentCountRes = $conn->query("SELECT COUNT(*) AS total_students FROM students");
if ($studentCountRes && ($studentCountRow = $studentCountRes->fetch_assoc())) {
    $studentsCount = (int)$studentCountRow['total_students'];
}

$latestRes = $conn->query("SELECT date, time FROM attendance ORDER BY date DESC, time DESC LIMIT 1");
if ($latestRes && ($latestRow = $latestRes->fetch_assoc())) {
    $latestScanText = date("M d, Y", strtotime($latestRow['date'])) . " at " . date("h:i A", strtotime($latestRow['time']));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/jpeg" href="../assets/css/logo.jpg">
    <link rel="stylesheet" href="../assets/css/dashboard_styles.css">
</head>
<body>
<button class="back-btn" onclick="goBack()">Back</button>
<main class="dashboard-wrap">
    <section class="hero-panel">
        <div class="hero-copy">
            <p class="eyebrow">Administration</p>
            <h1>Attendance Command Center</h1>
            <p class="hero-subtitle">Monitor attendance scans, manage student records, and verify generated QR codes in one place.</p>
        </div>
        <div class="menu-wrap">
            <button type="button" id="dashboard-menu-toggle" class="menu-toggle" aria-label="Open dashboard menu" aria-expanded="false" aria-controls="dashboard-menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div id="dashboard-menu" class="menu-drawer" hidden>
                <a href="register_student.php" class="action-link action-link-register">+ Register Student</a>
                <a href="manage_students.php" class="action-link action-link-manage">Manage Students</a>
                <a href="logout.php" class="action-link action-link-logout">Logout</a>
            </div>
        </div>
    </section>

    <section class="stats-grid">
        <article class="stat-card">
            <p class="stat-label">Total Scans</p>
            <p class="stat-value"><?php echo number_format($totalLogs); ?></p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Today's Scans</p>
            <p class="stat-value"><?php echo number_format($todayLogs); ?></p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Registered Students</p>
            <p class="stat-value"><?php echo number_format($studentsCount); ?></p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Latest Scan</p>
            <p class="stat-value stat-value-small"><?php echo htmlspecialchars($latestScanText); ?></p>
        </article>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>Attendance Records</h2>
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

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Grade & Section</th>
                        <th>Date & Time</th>
                        <th>Method</th>
                        <th>Parent Email</th>
                    </tr>
                </thead>
                <tbody>
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
                        $photoSrc = "../assets/uploads/" . rawurlencode((string)$row['photo_path']);
                        $photoSrcEsc = htmlspecialchars($photoSrc, ENT_QUOTES);
                        $studentIdEsc = htmlspecialchars((string)$row['student_id'], ENT_QUOTES);
                        $nameEsc = htmlspecialchars((string)$row['fullname'], ENT_QUOTES);
                        $gradeSectionEsc = htmlspecialchars((string)$row['grade_section'], ENT_QUOTES);
                        $dateTimeEsc = htmlspecialchars($displayDate . " at " . $displayTime, ENT_QUOTES);
                        $methodEsc = htmlspecialchars((string)$row['method'], ENT_QUOTES);
                        $parentEmailEsc = htmlspecialchars((string)$row['parent_email'], ENT_QUOTES);
                        $searchBlob = strtolower(
                            $row['student_id'] . ' ' .
                            $row['fullname'] . ' ' .
                            $row['grade_section'] . ' ' .
                            $row['parent_email'] . ' ' .
                            $displayDate . ' ' .
                            $displayTime
                        );
                        
                        echo "<tr class='data-row' data-search='" . htmlspecialchars($searchBlob, ENT_QUOTES) . "'>";
                        echo "<td><img src='{$photoSrcEsc}' alt='Student Photo' class='table-photo'></td>";
                        echo "<td>{$studentIdEsc}</td>";
                        echo "<td><button type='button' class='student-name-btn' data-photo-src='{$photoSrcEsc}' data-student-id='{$studentIdEsc}' data-name='{$nameEsc}' data-grade-section='{$gradeSectionEsc}' data-date-time='{$dateTimeEsc}' data-method='{$methodEsc}' data-parent-email='{$parentEmailEsc}'>{$nameEsc}</button></td>";
                        echo "<td>{$gradeSectionEsc}</td>";
                        echo "<td>" . $displayDate . " at " . $displayTime . "</td>";
                        echo "<td><span class='method-tag'>{$methodEsc}</span></td>";
                        echo "<td>{$parentEmailEsc}</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='no-records'>No attendance records found.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
        <p id="attendance-no-results" class="empty-state attendance-no-results">No matching attendance logs found.</p>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2 class="section-title">Student QR Code Preview</h2>
        </div>
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
    </section>
</main>

<script>
function goBack() {
    const current = new URL(window.location.href);
    const referrer = document.referrer ? new URL(document.referrer, window.location.origin) : null;
    const canUseHistory =
        window.history.length > 1 &&
        referrer &&
        referrer.origin === current.origin &&
        referrer.pathname !== current.pathname;

    if (canUseHistory) {
        window.history.back();
        return;
    }
    window.location.replace("../../src/home.php");
}

const qrSearchInput = document.getElementById("qr-search");
const attendanceRows = Array.from(document.querySelectorAll(".data-row"));
const attendanceNoResults = document.getElementById("attendance-no-results");
const qrCards = Array.from(document.querySelectorAll(".qr-card"));
const qrNoResults = document.getElementById("qr-no-results");
const menuToggle = document.getElementById("dashboard-menu-toggle");
const menuDrawer = document.getElementById("dashboard-menu");

if (menuToggle && menuDrawer) {
    const closeMenu = function () {
        menuDrawer.hidden = true;
        menuDrawer.classList.remove("open");
        menuToggle.setAttribute("aria-expanded", "false");
    };

    menuToggle.addEventListener("click", function (event) {
        event.stopPropagation();
        const willOpen = menuDrawer.hidden;
        menuDrawer.hidden = !willOpen;
        menuDrawer.classList.toggle("open", willOpen);
        menuToggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
    });

    document.addEventListener("click", function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (!target.closest(".menu-wrap")) {
            closeMenu();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeMenu();
        }
    });
}

if (qrSearchInput) {
    qrSearchInput.addEventListener("input", function () {
        const query = this.value.trim().toLowerCase();
        let visibleAttendance = 0;
        let visibleQr = 0;

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
            if (show) visibleQr++;
        });

        if (attendanceNoResults) {
            attendanceNoResults.style.display = visibleAttendance === 0 && attendanceRows.length > 0 ? "block" : "none";
        }

        if (qrNoResults) {
            qrNoResults.style.display = visibleQr === 0 && qrCards.length > 0 ? "block" : "none";
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Modal Functionality
    const modal = document.getElementById('attendance-modal');
    const modalBody = document.getElementById('modal-body');
    const closeModalBtn = document.getElementById('modal-close-btn');

    if (!modal || !modalBody || !closeModalBtn) {
        return;
    }

    const isPhoneLike = window.matchMedia('(max-width: 900px), (pointer: coarse)').matches;

    document.addEventListener('click', function(event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const nameButton = target.closest('.student-name-btn');
        if (!nameButton) {
            return;
        }
        if (!isPhoneLike) {
            return;
        }

        event.preventDefault();
        const photoSrc = nameButton.getAttribute('data-photo-src') || '';
        const studentId = nameButton.getAttribute('data-student-id') || '';
        const name = nameButton.getAttribute('data-name') || '';
        const gradeSection = nameButton.getAttribute('data-grade-section') || '';
        const dateTime = nameButton.getAttribute('data-date-time') || '';
        const method = nameButton.getAttribute('data-method') || '';
        const parentEmail = nameButton.getAttribute('data-parent-email') || '';

        modalBody.innerHTML = `
            ${photoSrc ? `<img src="${photoSrc}" alt="Student Photo" class="modal-student-photo">` : ''}
            <ul class="modal-details-list">
                <li><strong>Name:</strong> ${name}</li>
                <li><strong>Student ID:</strong> ${studentId}</li>
                <li><strong>Grade & Section:</strong> ${gradeSection}</li>
                <li><strong>Date & Time:</strong> ${dateTime}</li>
                <li><strong>Method:</strong> ${method}</li>
                <li><strong>Parent Email:</strong> ${parentEmail}</li>
            </ul>
        `;

        modal.style.display = 'flex';
    });

    function closeModal() {
        modal.style.display = 'none';
    }

    closeModalBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
});
</script>
<?php include "../../src/includes/footer.php"; ?>

<!-- Attendance Details Modal -->
<div id="attendance-modal" class="modal-container" style="display: none;">
    <div class="modal-content">
        <button id="modal-close-btn" class="modal-close-btn">&times;</button>
        <div id="modal-body">
            <!-- Student info will be injected here by JavaScript -->
        </div>
    </div>
</div>

</body>
</html>
