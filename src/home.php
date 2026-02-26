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
    <link rel="stylesheet" href="<?php echo $assetsPrefix; ?>assets/css/index.css">
</head>
<body>
    

    <div class="glass-container">
        <img src="<?php echo $assetsPrefix; ?>assets/css/logo.jpg" alt="BCNSHS Logo" class="logo">
        
        <h1>BCNSHS</h1>
        <p class="subtitle">Attendance Management System</p>

        <div class="button-group">
            <button class="btn-primary" onclick="location.href='<?php echo $publicPrefix; ?>residents/scan_qr.php'">
                Student Attendance(Scan QR)
            </button>
            
            <button class="btn-outline" onclick="location.href='<?php echo $publicPrefix; ?>admin/login.php'">
                Admin Dashboard
            </button>
            
            <button class="btn-outline" onclick="location.href='<?php echo $publicPrefix; ?>admin/register_student.php'">
                Register New Student
            </button>
        </div>

        <footer class="footer-note">
            Established 1969 • Bais City National High School
        </footer>
    </div>

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
    window.location.replace("index.php");
}
</script>

</body>
</html>
