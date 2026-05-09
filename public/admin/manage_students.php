<?php
require_once __DIR__ . "/auth.php";
admin_require_login();
include "../../database/db.php";

$message = '';
$messageType = 'success';
$schemaWarning = '';

$hasDisableColumn = false;
$colRes = $conn->query("SHOW COLUMNS FROM students LIKE 'is_disabled'");
if ($colRes && $colRes->num_rows > 0) {
    $hasDisableColumn = true;
} else {
    $schemaWarning = 'Student status controls are disabled because migration is missing. Run database/migrations/20260328_security_schema_updates.sql.';
}

$hasAttendanceTable = false;
$attendanceRes = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($attendanceRes && $attendanceRes->num_rows > 0) {
    $hasAttendanceTable = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please refresh and try again.';
        $messageType = 'error';
    }

    $action = $_POST['action'] ?? '';
    $studentId = trim($_POST['student_id'] ?? '');

    if ($message === '' && $action === 'update' && $studentId !== '') {
        $fullname = trim($_POST['fullname'] ?? '');
        $gradeSection = trim($_POST['grade_section'] ?? '');
        $parentEmail = trim($_POST['parent_email'] ?? '');

        if ($fullname === '' || $gradeSection === '' || $parentEmail === '') {
            $message = 'All fields are required to update a student.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare(
                "UPDATE students
                 SET fullname = ?, grade_section = ?, parent_email = ?
                 WHERE student_id = ?"
            );
            $stmt->bind_param("ssss", $fullname, $gradeSection, $parentEmail, $studentId);
            if ($stmt->execute()) {
                $message = 'Student updated successfully.';
            } else {
                $message = 'Failed to update student.';
                $messageType = 'error';
            }
        }
    }

    if ($message === '' && $action === 'toggle_disable' && $studentId !== '' && $hasDisableColumn) {
        $nextState = ($_POST['next_state'] ?? '1') === '1' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE students SET is_disabled = ? WHERE student_id = ?");
        $stmt->bind_param("is", $nextState, $studentId);
        if ($stmt->execute()) {
            $message = $nextState === 1 ? 'Student disabled successfully.' : 'Student enabled successfully.';
        } else {
            $message = 'Failed to update student status.';
            $messageType = 'error';
        }
    }

    if ($message === '' && $action === 'delete' && $studentId !== '') {
        $studentDeleted = false;
        $conn->begin_transaction();

        try {
            if ($hasAttendanceTable) {
                $attendanceStmt = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
                if (!$attendanceStmt) {
                    throw new RuntimeException('Unable to prepare attendance delete statement.');
                }
                $attendanceStmt->bind_param("s", $studentId);
                if (!$attendanceStmt->execute()) {
                    throw new RuntimeException('Unable to delete attendance records.');
                }
            }

            $deleteStmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
            if (!$deleteStmt) {
                throw new RuntimeException('Unable to prepare student delete statement.');
            }
            $deleteStmt->bind_param("s", $studentId);
            if (!$deleteStmt->execute()) {
                throw new RuntimeException('Unable to delete student record.');
            }

            if ($deleteStmt->affected_rows > 0) {
                $conn->commit();
                $studentDeleted = true;
                $message = 'Student deleted successfully.';
            } else {
                $conn->rollback();
                $message = 'Student not found.';
                $messageType = 'error';
            }
        } catch (Throwable $e) {
            $conn->rollback();
            $message = 'Failed to delete student.';
            $messageType = 'error';
            error_log('Delete student failed for ' . $studentId . ': ' . $e->getMessage());
        }

        if ($studentDeleted) {
            $safeFileName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $studentId) . '.png';
            $qrPath = __DIR__ . '/../assets/qrcodes/' . $safeFileName;
            if (is_file($qrPath)) {
                @unlink($qrPath);
            }
        }
    }
}

$studentSql = "SELECT student_id, fullname, grade_section, parent_email";
if ($hasDisableColumn) {
    $studentSql .= ", is_disabled";
} else {
    $studentSql .= ", 0 AS is_disabled";
}
$studentSql .= " FROM students ORDER BY fullname ASC";
$students = $conn->query($studentSql);

$totalStudents = 0;
$disabledStudents = 0;
$activeStudents = 0;

if ($hasDisableColumn) {
    $statsRes = $conn->query("SELECT COUNT(*) AS total_students, SUM(CASE WHEN is_disabled = 1 THEN 1 ELSE 0 END) AS disabled_students FROM students");
    if ($statsRes && ($statsRow = $statsRes->fetch_assoc())) {
        $totalStudents = (int)$statsRow['total_students'];
        $disabledStudents = (int)$statsRow['disabled_students'];
        $activeStudents = $totalStudents - $disabledStudents;
    }
} else {
    $statsRes = $conn->query("SELECT COUNT(*) AS total_students FROM students");
    if ($statsRes && ($statsRow = $statsRes->fetch_assoc())) {
        $totalStudents = (int)$statsRow['total_students'];
        $activeStudents = $totalStudents;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
    <link rel="icon" type="image/jpeg" href="../assets/css/logo.jpg">
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/legacy-theme.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <script src="../assets/js/anime.min.js"></script>
    <script src="../assets/js/anime-utils.js"></script>
    <script src="../assets/js/form-utils.js"></script>
</head>
<body class="manage-page min-h-screen bg-base-200">
<?php
$headerLogoSrc = "../assets/css/logo.jpg";
$headerHomeHref = "../../src/home.php";
include "../../src/includes/header.php";
?>

<div class="container mx-auto max-w-7xl space-y-4 px-4 py-6">
    <section class="manage-hero card border border-base-300 bg-base-100 shadow-lg">
        <div class="card-body flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary">Directory</p>
            <h1 class="text-2xl font-bold sm:text-3xl">Manage Student Accounts</h1>
            <p class="max-w-3xl text-sm opacity-80">Update profile details, monitor account status, and quickly filter the roster.</p>
        </div>
        <div class="menu-wrap relative ml-auto">
            <button type="button" id="manage-menu-toggle" class="menu-toggle btn btn-square btn-sm relative z-40" aria-label="Open manage menu" aria-expanded="false" aria-controls="manage-menu">&#9776;</button>
            <div id="manage-menu" class="menu-drawer absolute right-0 top-full z-30 mt-2 flex w-56 flex-col gap-2 rounded-box border border-base-300 bg-base-100 p-2 shadow-xl" hidden>
                <a href="dashboard.php" class="action-link btn btn-primary btn-sm">Dashboard</a>
                <a href="register_student.php" class="action-link btn btn-success btn-sm">Register Student</a>
                <a href="logout.php" class="action-link btn btn-error btn-sm">Logout</a>
            </div>
        </div>
        </div>
    </section>

    <section class="stats-grid grid gap-3 md:grid-cols-3">
        <article class="card border border-base-300 bg-base-100 shadow">
            <div class="card-body p-4">
            <p class="stat-label">Total Students</p>
            <p class="text-3xl font-bold"><?php echo number_format($totalStudents); ?></p>
            </div>
        </article>
        <article class="card border border-base-300 bg-base-100 shadow">
            <div class="card-body p-4">
            <p class="stat-label">Active</p>
            <p class="text-3xl font-bold"><?php echo number_format($activeStudents); ?></p>
            </div>
        </article>
        <article class="card border border-base-300 bg-base-100 shadow">
            <div class="card-body p-4">
            <p class="stat-label">Disabled</p>
            <p class="text-3xl font-bold"><?php echo number_format($disabledStudents); ?></p>
            </div>
        </article>
    </section>

    <?php if ($message !== ''): ?>
        <p class="alert py-2 <?php echo $messageType === 'error' ? 'alert-error' : 'alert-success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <?php if ($schemaWarning !== ''): ?>
        <p class="alert alert-warning py-2">
            <?php echo htmlspecialchars($schemaWarning); ?>
        </p>
    <?php endif; ?>

    <section class="card border border-base-300 bg-base-100 shadow">
        <div class="card-body p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold">Student Directory</h2>
            <div class="w-full sm:w-96">
                <input type="text" id="student-search" class="search-input input input-bordered w-full" placeholder="Search by name, ID, section, or email">
            </div>
        </div>

        <div class="table-wrap mt-3 overflow-x-auto rounded-box border border-base-300">
            <table class="table table-zebra table-sm min-w-[980px] md:table-md">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Grade & Section</th>
                        <th>Parent Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="student-table-body">
                    <?php if ($students && $students->num_rows > 0): ?>
                        <?php while ($row = $students->fetch_assoc()): ?>
                            <?php
                            $searchBlob = strtolower(
                                $row['student_id'] . ' ' .
                                $row['fullname'] . ' ' .
                                $row['grade_section'] . ' ' .
                                $row['parent_email']
                            );
                            $isDisabled = (int)$row['is_disabled'] === 1;
                            ?>
                            <tr data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES); ?>">
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td>
                                    <input class="input input-bordered input-sm w-full" form="update-<?php echo htmlspecialchars($row['student_id']); ?>" name="fullname" value="<?php echo htmlspecialchars($row['fullname']); ?>" required>
                                </td>
                                <td>
                                    <input class="input input-bordered input-sm w-full" form="update-<?php echo htmlspecialchars($row['student_id']); ?>" name="grade_section" value="<?php echo htmlspecialchars($row['grade_section']); ?>" required>
                                </td>
                                <td>
                                    <input class="input input-bordered input-sm w-full" form="update-<?php echo htmlspecialchars($row['student_id']); ?>" name="parent_email" type="email" value="<?php echo htmlspecialchars($row['parent_email']); ?>" required>
                                </td>
                                <td>
                                    <span class="status-badge badge <?php echo $isDisabled ? 'badge-error' : 'badge-success'; ?>">
                                        <?php echo $isDisabled ? 'Disabled' : 'Active'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-stack flex flex-col gap-2 sm:flex-row">
                                        <form id="update-<?php echo htmlspecialchars($row['student_id']); ?>" method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($row['student_id']); ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        </form>
                                        <?php if ($hasDisableColumn): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                                <input type="hidden" name="action" value="toggle_disable">
                                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($row['student_id']); ?>">
                                                <input type="hidden" name="next_state" value="<?php echo $isDisabled ? '0' : '1'; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $isDisabled ? 'btn-success' : 'btn-error'; ?>">
                                                    <?php echo $isDisabled ? 'Enable' : 'Disable'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" onsubmit="return confirm('Delete this student and all related attendance records? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($row['student_id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-error">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-sm opacity-70">No students found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p id="no-search-results" class="hidden pt-2 text-sm opacity-70">No matching students found.</p>
        </div>
    </section>
</div>

<script>
const searchInput = document.getElementById("student-search");
const rows = Array.from(document.querySelectorAll("#student-table-body tr[data-search]"));
const noResults = document.getElementById("no-search-results");
const menuToggle = document.getElementById("manage-menu-toggle");
const menuDrawer = document.getElementById("manage-menu");

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

if (searchInput) {
    searchInput.addEventListener("input", function () {
        const query = this.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(row => {
            const haystack = row.getAttribute("data-search") || "";
            const show = query === "" || haystack.includes(query);
            row.style.display = show ? "table-row" : "none";
            if (show) visibleCount++;
        });

        if (noResults) {
            noResults.classList.toggle("hidden", !(visibleCount === 0 && rows.length > 0));
        }
    });
}
</script>
<?php include "../../src/includes/footer.php"; ?>
</body>
</html>
