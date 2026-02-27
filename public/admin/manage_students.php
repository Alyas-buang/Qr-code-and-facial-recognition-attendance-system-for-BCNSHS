<?php
require_once __DIR__ . "/auth.php";
admin_require_login();
include "../../database/db.php";

$message = '';
$messageType = 'success';

$hasDisableColumn = false;
$colRes = $conn->query("SHOW COLUMNS FROM students LIKE 'is_disabled'");
if ($colRes && $colRes->num_rows > 0) {
    $hasDisableColumn = true;
} else {
    $alter = $conn->query("ALTER TABLE students ADD COLUMN is_disabled TINYINT(1) NOT NULL DEFAULT 0");
    if ($alter) {
        $hasDisableColumn = true;
    }
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
    <link rel="stylesheet" href="../assets/css/manage_students.css?v=3">
</head>
<body>
<?php
$headerLogoSrc = "../assets/css/logo.jpg";
$headerHomeHref = "../../src/home.php";
include "../../src/includes/header.php";
?>

<div class="page-wrap">
    <section class="hero-row">
        <div>
            <p class="eyebrow">Directory</p>
            <h1>Manage Student Accounts</h1>
            <p class="hero-subtitle">Update profile details, monitor account status, and quickly filter the roster.</p>
        </div>
        <div class="menu-wrap">
            <button type="button" id="manage-menu-toggle" class="menu-toggle" aria-label="Open manage menu" aria-expanded="false" aria-controls="manage-menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div id="manage-menu" class="menu-drawer" hidden>
                <a href="dashboard.php" class="action-link action-link-dashboard">Dashboard</a>
                <a href="register_student.php" class="action-link action-link-register">Register Student</a>
                <a href="manage_students.php" class="action-link action-link-manage">Manage Students</a>
                <a href="logout.php" class="action-link action-link-logout">Logout</a>
            </div>
        </div>
    </section>

    <section class="stats-grid">
        <article class="stat-card">
            <p class="stat-label">Total Students</p>
            <p class="stat-value"><?php echo number_format($totalStudents); ?></p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Active</p>
            <p class="stat-value"><?php echo number_format($activeStudents); ?></p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Disabled</p>
            <p class="stat-value"><?php echo number_format($disabledStudents); ?></p>
        </article>
    </section>

    <?php if ($message !== ''): ?>
        <p class="message <?php echo $messageType === 'error' ? 'message-error' : 'message-success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <section class="table-panel">
        <div class="header-row">
            <h2>Student Directory</h2>
            <div class="search-wrap">
                <input type="text" id="student-search" class="search-input" placeholder="Search by name, ID, section, or email">
            </div>
        </div>

        <div class="table-wrap">
            <table>
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
                                    <input form="update-<?php echo htmlspecialchars($row['student_id']); ?>" name="fullname" value="<?php echo htmlspecialchars($row['fullname']); ?>" required>
                                </td>
                                <td>
                                    <input form="update-<?php echo htmlspecialchars($row['student_id']); ?>" name="grade_section" value="<?php echo htmlspecialchars($row['grade_section']); ?>" required>
                                </td>
                                <td>
                                    <input form="update-<?php echo htmlspecialchars($row['student_id']); ?>" name="parent_email" type="email" value="<?php echo htmlspecialchars($row['parent_email']); ?>" required>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $isDisabled ? 'status-disabled' : 'status-active'; ?>">
                                        <?php echo $isDisabled ? 'Disabled' : 'Active'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-stack">
                                        <form id="update-<?php echo htmlspecialchars($row['student_id']); ?>" method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($row['student_id']); ?>">
                                            <button type="submit" class="btn btn-save">Save</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                            <input type="hidden" name="action" value="toggle_disable">
                                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($row['student_id']); ?>">
                                            <input type="hidden" name="next_state" value="<?php echo $isDisabled ? '0' : '1'; ?>">
                                            <button type="submit" class="btn <?php echo $isDisabled ? 'btn-enable' : 'btn-disable'; ?>">
                                                <?php echo $isDisabled ? 'Enable' : 'Disable'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-row">No students found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p id="no-search-results" class="empty-row no-results">No matching students found.</p>
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

if (searchInput) {
    searchInput.addEventListener("input", function () {
        const query = this.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(row => {
            const haystack = row.getAttribute("data-search") || "";
            const show = query === "" || haystack.includes(query);
            row.style.display = show ? "" : "none";
            if (show) visibleCount++;
        });

        if (noResults) {
            noResults.style.display = visibleCount === 0 && rows.length > 0 ? "block" : "none";
        }
    });
}
</script>
<?php include "../../src/includes/footer.php"; ?>
</body>
</html>
