<?php
require_once __DIR__ . "/auth.php";

if (admin_is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$next = $_GET['next'] ?? 'dashboard.php';
$configError = admin_auth_config_error();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $next = $_POST['next'] ?? 'dashboard.php';
    $token = $_POST['csrf_token'] ?? '';

    if ($configError !== null) {
        $error = 'Admin login is not configured. Please contact the system administrator.';
    } elseif (!csrf_validate($token)) {
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
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/legacy-theme.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <script src="../assets/js/anime.min.js"></script>
    <script src="../assets/js/anime-utils.js"></script>
    <script src="../assets/js/form-utils.js"></script>
</head>
<body class="admin-login-page min-h-screen bg-base-200 p-4 sm:p-6">
    <form class="login-card card mx-auto mt-10 w-full max-w-sm border border-base-300 bg-base-100 shadow-2xl" method="post" action="">
        <div class="card-body gap-3">
        <h2 class="card-title justify-center text-2xl">Admin Login</h2>
        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
        <input class="input input-bordered w-full" type="text" name="username" placeholder="Username" required>
        <input class="input input-bordered w-full" type="password" name="password" placeholder="Password" required>
        <button class="btn btn-primary w-full" type="submit" <?php echo $configError !== null ? 'disabled' : ''; ?>>Login</button>
        <?php if ($error): ?>
            <p class="alert alert-error py-2 text-sm"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        
        <a class="btn btn-ghost btn-sm" href="../../src/home.php">Back</a>
        </div>
    </form>
    <?php include "../../src/includes/footer.php"; ?>
</body>
</html>
