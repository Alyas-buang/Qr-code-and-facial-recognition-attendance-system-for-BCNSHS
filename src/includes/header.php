<?php
$headerLogoSrc = $headerLogoSrc ?? '../assets/css/logo.jpg';
$headerHomeHref = $headerHomeHref ?? '../../src/home.php';
$headerTitle = $headerTitle ?? 'Bais City National High School';
$headerSubtitle = $headerSubtitle ?? 'Attendance Management System';
?>
<header class="navbar sticky top-0 z-50 bg-primary text-primary-content shadow-md">
    <div class="mx-auto flex w-full max-w-7xl items-center gap-3 px-4 py-3">
        <a href="<?php echo htmlspecialchars($headerHomeHref, ENT_QUOTES); ?>" class="flex w-full items-center gap-3 no-underline">
            <img src="<?php echo htmlspecialchars($headerLogoSrc, ENT_QUOTES); ?>" alt="BCNSHS Logo" class="hoverable-media h-12 w-12 rounded-full border border-primary-content border-opacity-40 object-cover">
            <span class="min-w-0">
                <span class="block truncate text-lg font-bold"><?php echo htmlspecialchars($headerTitle, ENT_QUOTES); ?></span>
                <span class="block truncate text-sm opacity-80"><?php echo htmlspecialchars($headerSubtitle, ENT_QUOTES); ?></span>
            </span>
        </a>
    </div>
</header>
