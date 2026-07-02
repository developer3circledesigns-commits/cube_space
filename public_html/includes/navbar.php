<?php
require_once __DIR__ . '/bootstrap.php';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$isAdminPage = preg_match('#/admin/#', $_SERVER['SCRIPT_NAME']);
if (!function_exists('isActive')) {
    function isActive($pages) {
        global $currentPage;
        if (is_array($pages)) {
            return in_array($currentPage, $pages) ? ' class="active"' : '';
        }
        return $currentPage === $pages ? ' class="active"' : '';
    }
}
?>
<nav class="site-navbar">
    <div class="nav-container">
        <a href="index.php" class="logo" aria-label="CubeSpace Home">
            <img src="assets/images/final-logo.png" alt="CubeSpace" width="180" height="52">
        </a>
        <ul class="menu">
            <li><a href="index.php"<?= isActive('index.php') ?>>Home</a></li>
            <li class="mega-parent">
                <a href="#"<?= isActive(['managed_offices.php', 'furnished_offices.php', 'unfurnished_offices.php', 'office_detail.php']) ?>>Solutions <i class="fa-solid fa-chevron-down nav-chevron"></i></a>
                <div class="mega-menu">
                    <div class="mega-container">
                        <div class="mega-column">
                            <a href="managed_offices.php" class="mega-item">
                                <div class="icon-box"><i class="fa-solid fa-briefcase"></i></div>
                                <div class="mega-content">
                                    <h4>Managed Office Spaces</h4>
                                    <p>Managed Furnished office provided by Top Service providers</p>
                                </div>
                            </a>
                            <a href="furnished_offices.php" class="mega-item">
                                <div class="icon-box"><i class="fa-solid fa-building"></i></div>
                                <div class="mega-content">
                                    <h4>Furnished / Unfurnished Office Spaces</h4>
                                    <p>Furnished / Unfurnished office provided by Top Service providers</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </li>
            <li><a href="contact.php"<?= isActive('contact.php') ?>>Share your Requirement</a></li>
            <li>
                <a href="/admin/" class="admin-login-link" title="Admin Login" aria-label="Admin Login" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-circle-user"></i>
                    <span class="visually-hidden">Admin Login</span>
                </a>
            </li>
        </ul>
        <button class="mobile-menu" aria-label="Toggle Navigation Menu" aria-expanded="false" aria-controls="mobileNav">
            <i class="fa-solid fa-bars"></i>
        </button>
        <ul class="mobile-nav" id="mobileNav">
            <li><a href="index.php"<?= isActive('index.php') ?>><i class="fa-solid fa-house nav-mobile-icon"></i>Home</a></li>
            <li><a href="managed_offices.php"<?= isActive('managed_offices.php') ?>><i class="fa-solid fa-briefcase nav-mobile-icon"></i>Managed Offices</a></li>
            <li><a href="furnished_offices.php"<?= isActive('furnished_offices.php') ?>><i class="fa-solid fa-building nav-mobile-icon"></i>Furnished Offices</a></li>
            <li><a href="unfurnished_offices.php"<?= isActive('unfurnished_offices.php') ?>><i class="fa-solid fa-building-user nav-mobile-icon"></i>Unfurnished Offices</a></li>
            <li><a href="contact.php"<?= isActive('contact.php') ?>><i class="fa-solid fa-envelope nav-mobile-icon"></i>Contact Us</a></li>
            <li><a href="contact.php"<?= isActive('contact.php') ?>><i class="fa-solid fa-paper-plane nav-mobile-icon"></i>Share Requirement</a></li>
            <li><a href="/admin/" target="_blank" rel="noopener noreferrer"<?= $isAdminPage ? ' class="active"' : '' ?>><i class="fa-solid fa-user-shield nav-mobile-icon"></i>Admin Login</a></li>
        </ul>
    </div>
</nav>