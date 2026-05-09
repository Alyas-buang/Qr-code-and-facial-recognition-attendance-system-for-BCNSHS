<?php
require_once __DIR__ . "/auth.php";
admin_require_login();
require_once __DIR__ . "/../../src/includes/env.php";
require_once __DIR__ . "/../../src/includes/attendance_status.php";
require_once __DIR__ . "/../../src/assets/libs/phpqrcode/qrlib.php";
include "../../database/db.php";

$totalLogs = 0;
$presentToday = 0;
$lateToday = 0;
$absentToday = 0;
$studentsCount = 0;
$latestScanText = "No scans yet";
$hasDisableColumn = false;
$hasAttendanceStatusColumn = false;

app_env_load(__DIR__ . "/../../.env");
date_default_timezone_set(attendance_app_timezone()->getName());

$disableColRes = $conn->query("SHOW COLUMNS FROM students LIKE 'is_disabled'");
if ($disableColRes && $disableColRes->num_rows > 0) {
    $hasDisableColumn = true;
}

$attendanceStatusColRes = $conn->query("SHOW COLUMNS FROM attendance LIKE 'status'");
if ($attendanceStatusColRes && $attendanceStatusColRes->num_rows > 0) {
    $hasAttendanceStatusColumn = true;
}

$countRes = $conn->query("SELECT COUNT(*) AS total_logs FROM attendance");
if ($countRes && ($countRow = $countRes->fetch_assoc())) {
    $totalLogs = (int)$countRow['total_logs'];
}

$studentCountSql = $hasDisableColumn
    ? "SELECT COUNT(*) AS total_students FROM students WHERE is_disabled = 0"
    : "SELECT COUNT(*) AS total_students FROM students";

$studentCountRes = $conn->query($studentCountSql);
if ($studentCountRes && ($studentCountRow = $studentCountRes->fetch_assoc())) {
    $studentsCount = (int)$studentCountRow['total_students'];
}

$lateCutoff = $conn->real_escape_string(attendance_late_cutoff());

if ($hasAttendanceStatusColumn) {
    $statusSummarySql = "SELECT COALESCE(first_attendance.status, CASE WHEN first_attendance.time <= '{$lateCutoff}' THEN 'Present' ELSE 'Late' END) AS status,
                                COUNT(*) AS status_count
                         FROM (
                            SELECT a.student_id, a.time, a.status
                            FROM attendance a
                            INNER JOIN (
                                SELECT student_id, MIN(time) AS first_time
                                FROM attendance
                                WHERE date = CURDATE()
                                GROUP BY student_id
                            ) first_scan
                                ON first_scan.student_id = a.student_id
                               AND first_scan.first_time = a.time
                               AND a.date = CURDATE()
                         ) first_attendance
                         GROUP BY COALESCE(first_attendance.status, CASE WHEN first_attendance.time <= '{$lateCutoff}' THEN 'Present' ELSE 'Late' END)";
} else {
    $statusSummarySql = "SELECT CASE WHEN first_attendance.time <= '{$lateCutoff}' THEN 'Present' ELSE 'Late' END AS status,
                                COUNT(*) AS status_count
                         FROM (
                            SELECT a.student_id, a.time
                            FROM attendance a
                            INNER JOIN (
                                SELECT student_id, MIN(time) AS first_time
                                FROM attendance
                                WHERE date = CURDATE()
                                GROUP BY student_id
                            ) first_scan
                                ON first_scan.student_id = a.student_id
                               AND first_scan.first_time = a.time
                               AND a.date = CURDATE()
                         ) first_attendance
                         GROUP BY CASE WHEN first_attendance.time <= '{$lateCutoff}' THEN 'Present' ELSE 'Late' END";
}

$statusSummaryRes = $conn->query($statusSummarySql);
if ($statusSummaryRes) {
    while ($statusRow = $statusSummaryRes->fetch_assoc()) {
        $status = strtolower((string)$statusRow['status']);
        $count = (int)$statusRow['status_count'];
        if ($status === 'present') {
            $presentToday = $count;
        } elseif ($status === 'late') {
            $lateToday = $count;
        }
    }
}

$absentToday = max(0, $studentsCount - $presentToday - $lateToday);

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
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/legacy-theme.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <script src="../assets/js/anime.min.js"></script>
    <script src="../assets/js/anime-utils.js"></script>
    <script src="../assets/js/form-utils.js"></script>
</head>
<body class="admin-dashboard-page min-h-screen bg-base-200">
<?php
$headerLogoSrc = "../assets/css/logo.jpg";
$headerHomeHref = "../../src/home.php";
include "../../src/includes/header.php";
?>
<main class="container mx-auto max-w-7xl space-y-4 px-4 py-6">
    <div class="flex items-center">
        <button class="btn btn-sm btn-neutral border border-base-300 shadow-md" onclick="window.location.href='../../src/home.php'">Back to Home</button>
    </div>
    <section class="dashboard-hero card border border-base-300 bg-base-100 shadow-lg">
        <div class="card-body flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary">Administration</p>
            <h1 class="text-2xl font-bold sm:text-3xl">Attendance Command Center</h1>
            <p class="max-w-3xl text-sm opacity-80">Monitor attendance scans, manage student records, and verify generated QR codes in one place.</p>
        </div>
        <div class="menu-wrap relative ml-auto">
            <button type="button" id="dashboard-menu-toggle" class="menu-toggle btn btn-square btn-sm relative z-40" aria-label="Open dashboard menu" aria-expanded="false" aria-controls="dashboard-menu">&#9776;</button>
            <div id="dashboard-menu" class="menu-drawer absolute right-0 top-full z-30 mt-2 flex w-56 flex-col gap-2 rounded-box border border-base-300 bg-base-100 p-2 shadow-xl" hidden>
                <a href="register_student.php" class="action-link btn btn-success btn-sm">+ Register Student</a>
                <a href="manage_students.php" class="action-link btn btn-primary btn-sm">Manage Students</a>
                <a href="logout.php" class="action-link btn btn-error btn-sm">Logout</a>
            </div>
        </div>
        </div>
    </section>

    <section class="stats-grid grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <article class="card border border-base-300 bg-base-100 shadow">
            <div class="card-body p-4">
            <p class="stat-label">Total Scans</p>
            <p class="text-3xl font-bold"><?php echo number_format($totalLogs); ?></p>
            </div>
        </article>
        <article class="card border border-base-300 bg-base-100 shadow">
            <div class="card-body p-4">
            <p class="stat-label">Present Today</p>
            <p class="text-3xl font-bold text-success"><?php echo number_format($presentToday); ?></p>
            </div>
        </article>
        <article class="card border border-base-300 bg-base-100 shadow">
            <div class="card-body p-4">
            <p class="stat-label">Late Today</p>
            <p class="text-3xl font-bold text-warning"><?php echo number_format($lateToday); ?></p>
            </div>
        </article>
        <article class="card border border-base-300 bg-base-100 shadow">
            <div class="card-body p-4">
            <p class="stat-label">Absent Today</p>
            <p class="text-3xl font-bold text-error"><?php echo number_format($absentToday); ?></p>
            </div>
        </article>
        <article class="card border border-base-300 bg-base-100 shadow md:col-span-2 xl:col-span-1">
            <div class="card-body p-4">
            <p class="stat-label">Active Students</p>
            <p class="text-3xl font-bold"><?php echo number_format($studentsCount); ?></p>
            </div>
        </article>
        <article class="card border border-base-300 bg-base-100 shadow md:col-span-2 xl:col-span-1">
            <div class="card-body p-4">
            <p class="stat-label">Latest Scan</p>
            <p class="text-sm font-semibold"><?php echo htmlspecialchars($latestScanText); ?></p>
            </div>
        </article>
    </section>

    <section class="card border border-base-300 bg-base-100 shadow">
        <div class="card-body p-4 sm:p-5">
        <div class="panel-heading">
            <h2 class="text-xl font-semibold">Today's Student Attendance Status</h2>
        </div>

        <div class="mt-2">
            <input
                type="text"
                id="qr-search"
                class="qr-search-input input input-bordered w-full max-w-xl"
                placeholder="Search attendance logs and QR codes by name, ID, section, or email..."
                aria-label="Search attendance logs and QR codes"
            >
        </div>

        <div class="table-wrap mt-3 overflow-x-auto rounded-box border border-base-300">
            <table class="table table-zebra table-sm min-w-[920px] md:table-md">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Grade & Section</th>
                        <th>First Scan Today</th>
                        <th>Attendance Status</th>
                        <th>Parent Email</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $lateCutoff = $conn->real_escape_string(attendance_late_cutoff());
                $activeStudentWhere = $hasDisableColumn ? "WHERE s.is_disabled = 0" : "";
                $attendanceStatusSelect = $hasAttendanceStatusColumn
                    ? "COALESCE(first_attendance.status, CASE WHEN first_attendance.time <= '{$lateCutoff}' THEN 'Present' ELSE 'Late' END)"
                    : "CASE WHEN first_attendance.time <= '{$lateCutoff}' THEN 'Present' ELSE 'Late' END";

                $sql = "SELECT s.student_id, s.fullname, s.grade_section, s.parent_email,
                               first_attendance.date, first_attendance.time, first_attendance.photo_path,
                               CASE
                                   WHEN first_attendance.student_id IS NULL THEN 'Absent'
                                   ELSE {$attendanceStatusSelect}
                               END AS attendance_status
                        FROM students s
                        LEFT JOIN (
                            SELECT a.student_id, a.date, a.time, a.photo_path" . ($hasAttendanceStatusColumn ? ", a.status" : "") . "
                            FROM attendance a
                            INNER JOIN (
                                SELECT student_id, MIN(time) AS first_time
                                FROM attendance
                                WHERE date = CURDATE()
                                GROUP BY student_id
                            ) first_scan
                                ON first_scan.student_id = a.student_id
                               AND first_scan.first_time = a.time
                               AND a.date = CURDATE()
                        ) first_attendance
                            ON first_attendance.student_id = s.student_id
                        {$activeStudentWhere}
                        ORDER BY
                            CASE
                                WHEN first_attendance.student_id IS NULL THEN 2
                                WHEN {$attendanceStatusSelect} = 'Late' THEN 1
                                ELSE 0
                            END,
                            s.fullname ASC";

                $res = $conn->query($sql);

                if ($res && $res->num_rows > 0) {
                    while($row = $res->fetch_assoc()){
                        $displayDateTime = 'No scan yet';
                        if (!empty($row['date']) && !empty($row['time'])) {
                            $displayDateTime = date("M d, Y", strtotime($row['date'])) . " at " . date("h:i A", strtotime($row['time']));
                        }
                        $photoSrcEsc = '';
                        $photoCellHtml = "<span class='text-xs opacity-60'>No photo</span>";
                        if (!empty($row['photo_path'])) {
                            $photoSrc = "../assets/uploads/" . rawurlencode((string)$row['photo_path']);
                            $photoSrcEsc = htmlspecialchars($photoSrc, ENT_QUOTES);
                            $photoCellHtml = "<img src='{$photoSrcEsc}' alt='Student Photo' class='table-photo hoverable-media h-16 w-20 rounded object-cover'>";
                        }
                        $studentIdEsc = htmlspecialchars((string)$row['student_id'], ENT_QUOTES);
                        $nameEsc = htmlspecialchars((string)$row['fullname'], ENT_QUOTES);
                        $gradeSectionEsc = htmlspecialchars((string)$row['grade_section'], ENT_QUOTES);
                        $dateTimeEsc = htmlspecialchars($displayDateTime, ENT_QUOTES);
                        $statusText = (string)($row['attendance_status'] ?? 'Absent');
                        $statusEsc = htmlspecialchars($statusText, ENT_QUOTES);
                        $statusClass = 'badge badge-outline';
                        if (strcasecmp($statusText, 'Present') === 0) {
                            $statusClass .= ' badge-success';
                        } elseif (strcasecmp($statusText, 'Late') === 0) {
                            $statusClass .= ' badge-warning';
                        } else {
                            $statusClass .= ' badge-error';
                        }
                        $parentEmailEsc = htmlspecialchars((string)$row['parent_email'], ENT_QUOTES);
                        $searchBlob = strtolower(
                            $row['student_id'] . ' ' .
                            $row['fullname'] . ' ' .
                            $row['grade_section'] . ' ' .
                            $row['parent_email'] . ' ' .
                            $statusText . ' ' .
                            $displayDateTime
                        );
                        
                        echo "<tr class='data-row' data-search='" . htmlspecialchars($searchBlob, ENT_QUOTES) . "'>";
                        echo "<td>{$photoCellHtml}</td>";
                        echo "<td>{$studentIdEsc}</td>";
                        echo "<td><button type='button' title='View attendance details' class='student-name-btn link link-primary' data-photo-src='{$photoSrcEsc}' data-student-id='{$studentIdEsc}' data-name='{$nameEsc}' data-grade-section='{$gradeSectionEsc}' data-date-time='{$dateTimeEsc}' data-status='{$statusEsc}' data-parent-email='{$parentEmailEsc}'>{$nameEsc}</button></td>";
                        echo "<td>{$gradeSectionEsc}</td>";
                        echo "<td>{$dateTimeEsc}</td>";
                        echo "<td><span class='{$statusClass}'>{$statusEsc}</span></td>";
                        echo "<td>{$parentEmailEsc}</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='text-center text-sm opacity-70'>No attendance records found.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
        <p id="attendance-no-results" class="hidden pt-2 text-sm opacity-70">No matching attendance logs found.</p>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100 shadow">
        <div class="card-body p-4 sm:p-5">
        <div class="panel-heading">
            <h2 class="text-xl font-semibold">Student QR Code Preview</h2>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            <?php
            $studentSql = "SELECT student_id, fullname, qr_code FROM students ORDER BY fullname ASC";
            $studentRes = $conn->query($studentSql);

            if ($studentRes && $studentRes->num_rows > 0) {
                while ($student = $studentRes->fetch_assoc()) {
                    $sid = htmlspecialchars($student['student_id']);
                    $name = htmlspecialchars($student['fullname']);
                    $qrSrc = "../assets/qrcodes/" . rawurlencode($student['student_id']) . ".png";
                    $qrFilePath = __DIR__ . "/../assets/qrcodes/" . preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) $student['student_id']) . ".png";
                    $qrExists = is_file($qrFilePath);

                    if (!$qrExists) {
                        $storedQrValue = trim((string) ($student['qr_code'] ?? ''));
                        if ($storedQrValue !== '') {
                            try {
                                QRcode::png($storedQrValue, $qrFilePath, 'H', 10, 2);
                                if (is_file($qrFilePath)) {
                                    @chmod($qrFilePath, 0644);
                                    $qrExists = true;
                                }
                            } catch (Throwable $e) {
                                error_log('QR preview regeneration failed for ' . (string) $student['student_id'] . ': ' . $e->getMessage());
                            }
                        }
                    }

                    $searchBlob = strtolower($student['fullname'] . " " . $student['student_id']);
                    echo "<div class='qr-card card border border-base-300 bg-base-100 p-3 text-center' data-search='" . htmlspecialchars($searchBlob, ENT_QUOTES) . "'>";
                    if ($qrExists) {
                        echo "<img class='hoverable-media mx-auto h-28 w-28 rounded object-contain' src='{$qrSrc}' alt='QR for {$name}'>";
                        echo "<p class='qr-missing hidden text-error'>QR not generated yet</p>";
                    } else {
                        echo "<p class='qr-missing text-error'>QR not generated yet</p>";
                    }
                    echo "<p class='mt-2 text-sm font-semibold'>{$name}</p>";
                    echo "<p class='text-xs opacity-70'>{$sid}</p>";
                    echo "</div>";
                }
            } else {
                echo "<p class='text-sm opacity-70'>No registered students found.</p>";
            }
            ?>
        </div>
        <p id="qr-no-results" class="hidden pt-2 text-sm opacity-70">No matching QR code found.</p>
        </div>
    </section>
</main><script>
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

    const openMenu = function () {
        menuDrawer.hidden = false;
        menuDrawer.classList.add("open");
        menuToggle.setAttribute("aria-expanded", "true");
    };

    const isMenuOpen = function () {
        return menuToggle.getAttribute("aria-expanded") === "true" || !menuDrawer.hidden;
    };

    closeMenu();
    window.addEventListener("pageshow", closeMenu);

    menuToggle.addEventListener("click", function (event) {
        event.stopPropagation();
        if (isMenuOpen()) {
            closeMenu();
            return;
        }
        openMenu();
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
            card.style.display = show ? "block" : "none";
            if (show) visibleQr++;
        });

        if (attendanceNoResults) {
            attendanceNoResults.classList.toggle("hidden", !(visibleAttendance === 0 && attendanceRows.length > 0));
        }

        if (qrNoResults) {
            qrNoResults.classList.toggle("hidden", !(visibleQr === 0 && qrCards.length > 0));
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('attendance-modal');
    const modalBody = document.getElementById('modal-body');
    const closeModalBtn = document.getElementById('modal-close-btn');

    if (!modal || !modalBody || !closeModalBtn) {
        return;
    }

    const openModalFromButton = function (nameButton) {
        if (!(nameButton instanceof Element)) {
            return;
        }
        const photoSrc = nameButton.getAttribute('data-photo-src') || '';
        const studentId = nameButton.getAttribute('data-student-id') || '';
        const name = nameButton.getAttribute('data-name') || '';
        const gradeSection = nameButton.getAttribute('data-grade-section') || '';
        const dateTime = nameButton.getAttribute('data-date-time') || '';
        const status = nameButton.getAttribute('data-status') || '';
        const parentEmail = nameButton.getAttribute('data-parent-email') || '';

        modalBody.innerHTML = `
            ${photoSrc ? `<img src="${photoSrc}" alt="Student Photo" class="hoverable-media mx-auto mb-3 h-24 w-24 rounded-full border border-base-300 object-cover">` : ''}
            <div class="space-y-2 text-sm">
                <p><span class="font-semibold">Name:</span> ${name}</p>
                <p><span class="font-semibold">Student ID:</span> ${studentId}</p>
                <p><span class="font-semibold">Grade & Section:</span> ${gradeSection}</p>
                <p><span class="font-semibold">Date & Time:</span> ${dateTime}</p>
                <p><span class="font-semibold">Status:</span> ${status}</p>
                <p><span class="font-semibold">Parent Email:</span> ${parentEmail}</p>
            </div>
        `;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
    };

    document.querySelectorAll('.student-name-btn').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            openModalFromButton(button);
        });

        button.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openModalFromButton(button);
            }
        });
    });

    function closeModal() {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }

    closeModalBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
});
</script>
<?php include "../../src/includes/footer.php"; ?>

<div id="attendance-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50 p-4">
    <div class="card w-full max-w-md bg-base-100 shadow-2xl">
        <div class="card-body relative pt-10">
        <button id="modal-close-btn" class="btn btn-sm btn-circle btn-ghost absolute right-3 top-3">&times;</button>
        <div id="modal-body" class="text-base-content">
        </div>
        </div>
    </div>
</div>

</body>
</html>


