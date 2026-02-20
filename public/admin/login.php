<?php
require_once __DIR__ . "/auth.php";

if (admin_is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$next = $_GET['next'] ?? 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $next = $_POST['next'] ?? 'dashboard.php';
    $token = $_POST['csrf_token'] ?? '';

    if (!csrf_validate($token)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } elseif (admin_is_rate_limited()) {
        $wait = admin_rate_limit_remaining_seconds();
        $error = 'Too many login attempts. Try again in ' . $wait . ' seconds.';
    } elseif (admin_login($username, $password)) {
        $target = 'dashboard.php';
        if (strpos($next, 'register_student.php') !== false) {
            $target = 'register_student.php';
        }
        if (strpos($next, 'manage_students.php') !== false) {
            $target = 'manage_students.php';
        }
        if (strpos($next, 'dashboard.php') !== false) {
            $target = 'dashboard.php';
        }
        header("Location: " . $target);
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="icon" type="image/jpeg" href="../assets/css/logo.jpg">
    <link rel="stylesheet" href="../assets/css/admin_login.css?v=2">
</head>
<body>
    <form class="card" method="post" action="">
        <h2>Admin Login</h2>
        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        
        <a class="back-link" href="../../src/home.php">Back</a>
    </form>
    <?php include "../../src/includes/footer.php"; ?>
</body>
</html>
