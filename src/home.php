<?php
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isDirectHome = str_ends_with($scriptName, '/src/home.php');
$assetsPrefix = $isDirectHome ? '' : 'src/';
$publicPrefix = $isDirectHome ? '../public/' : 'public/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BCNSHS Attendance System</title>
    <link rel="icon" type="image/jpeg" href="<?php echo $assetsPrefix; ?>assets/css/logo.jpg">
    <link rel="stylesheet" href="<?php echo $publicPrefix; ?>assets/css/app.css">
    <link rel="stylesheet" href="<?php echo $publicPrefix; ?>assets/css/legacy-theme.css">
    <link rel="stylesheet" href="<?php echo $publicPrefix; ?>assets/css/animations.css">
    <script src="<?php echo $publicPrefix; ?>assets/js/anime.min.js"></script>
    <script src="<?php echo $publicPrefix; ?>assets/js/anime-utils.js"></script>
    <script src="<?php echo $publicPrefix; ?>assets/js/form-utils.js"></script>
</head>
<body class="home-page min-h-screen bg-base-200 p-4 sm:p-6">
    <div class="home-card card mx-auto mt-6 w-full max-w-lg bg-base-100 shadow-2xl">
        <div class="card-body text-center">
            <img src="<?php echo $assetsPrefix; ?>assets/css/logo.jpg" alt="BCNSHS Logo" class="home-logo hoverable-media mx-auto h-24 w-24 rounded-full object-cover">

            <h1 class="text-3xl font-bold">BCNSHS</h1>
            <p class="text-base-content opacity-70">Attendance Management System</p>

            <div class="mt-4 grid gap-3">
                <button class="btn btn-primary" onclick="location.href='<?php echo $publicPrefix; ?>residents/scan_qr.php'">
                    Student Attendance (Scan QR)
                </button>

                <button class="btn btn-outline btn-primary" onclick="location.href='<?php echo $publicPrefix; ?>admin/login.php'">
                    Admin Dashboard
                </button>

                <button class="btn btn-outline btn-primary" onclick="location.href='<?php echo $publicPrefix; ?>admin/register_student.php'">
                    Register New Student
                </button>
            </div>

            <footer class="mt-6 text-xs text-base-content opacity-60">
                Established 1969 | Bais City National High School
            </footer>
        </div>
    </div>
</body>
</html>
