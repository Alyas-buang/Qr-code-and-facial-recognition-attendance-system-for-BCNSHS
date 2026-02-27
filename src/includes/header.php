<?php
$headerLogoSrc = $headerLogoSrc ?? '../assets/css/logo.jpg';
$headerHomeHref = $headerHomeHref ?? '../../src/home.php';
$headerTitle = $headerTitle ?? 'Bais City National High School';
$headerSubtitle = $headerSubtitle ?? 'Attendance Management System';
?>
<style>
.site-header {
    background: linear-gradient(110deg, #0f172a 0%, #1d4ed8 52%, #0ea5e9 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.22);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.28);
    position: sticky;
    top: 0;
    z-index: 1200;
}

.site-header__inner {
    max-width: 1380px;
    margin: 0 auto;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.site-header__brand {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    color: #f8fbff;
}

.site-header__logo {
    width: 58px;
    height: 58px;
    border-radius: 999px;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.55);
}

.site-header__text {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
}

.site-header__title {
    font-size: 1.2rem;
    font-weight: 800;
}

.site-header__subtitle {
    font-size: 0.9rem;
    color: rgba(239, 246, 255, 0.95);
    font-weight: 600;
}

/* Keep page-level back buttons visible below the sticky header */
.back-btn {
    z-index: 1301 !important;
    top: 92px !important;
}

@media (max-width: 768px) {
    .back-btn {
        top: 86px !important;
    }
}
</style>

<header class="site-header">
    <div class="site-header__inner">
        <a href="<?php echo htmlspecialchars($headerHomeHref, ENT_QUOTES); ?>" class="site-header__brand">
            <img src="<?php echo htmlspecialchars($headerLogoSrc, ENT_QUOTES); ?>" alt="BCNSHS Logo" class="site-header__logo">
            <span class="site-header__text">
                <span class="site-header__title"><?php echo htmlspecialchars($headerTitle, ENT_QUOTES); ?></span>
                <span class="site-header__subtitle"><?php echo htmlspecialchars($headerSubtitle, ENT_QUOTES); ?></span>
            </span>
        </a>
    </div>
</header>
