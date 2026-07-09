<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/jwt_helper.php';
admin_require_lib('csrf.php');

CSRFManager::initialize();
$csrfToken = CSRFManager::generateToken();

function render_admin_pagination(int $total, int $page, int $perPage, string $baseUrl): void {
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($totalPages <= 1) return;
    echo '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center mb-0">';
    if ($page > 1) echo '<li class="page-item"><a href="' . $baseUrl . '&p=' . ($page-1) . '" class="page-link">&laquo; Prev</a></li>';
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $page) echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        else echo '<li class="page-item"><a href="' . $baseUrl . '&p=' . $i . '" class="page-link">' . $i . '</a></li>';
    }
    if ($page < $totalPages) echo '<li class="page-item"><a href="' . $baseUrl . '&p=' . ($page+1) . '" class="page-link">Next &raquo;</a></li>';
    echo '</ul></nav>';
}

$adminUser = '';
$loggedIn = false;
$adminRole = 'admin';
$token = $_COOKIE['access_token'] ?? '';
if ($token) {
    $payload = jwt_decode($token);
    if ($payload && ($payload['type'] ?? '') === 'access') {
        $loggedIn = true;
        $adminUser = $payload['user'];
        $adminId = $payload['sub'];
        $adminRole = $payload['role'] ?? 'admin';
    }
}

if (!$loggedIn) {
    $refreshToken = $_COOKIE['refresh_token'] ?? '';
    if ($refreshToken) {
        $payload = jwt_decode($refreshToken);
        if ($payload && ($payload['type'] ?? '') === 'refresh') {
            $newAccess = generate_access_token($payload['sub'], $payload['user'], $payload['role'] ?? 'admin');
            $newRefresh = generate_refresh_token($payload['sub'], $payload['user'], $payload['role'] ?? 'admin');
            set_auth_cookies($newAccess, $newRefresh);
            $token = $newAccess;
            $loggedIn = true;
            $adminUser = $payload['user'];
            $adminId = $payload['sub'];
            $adminRole = $payload['role'] ?? 'admin';
        } else {
            clear_auth_cookies();
        }
    }
}

if (!$loggedIn) {
    header('Location: index.php');
    exit;
}

$jsAccessToken = $loggedIn ? ($token ?: '') : '';

admin_load_db();

$newContactCount = 0;
$cntResult = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM contacts WHERE status='new'");
if ($cntResult) { $newContactCount = (int)mysqli_fetch_assoc($cntResult)['cnt']; }

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Panel - CubeSpace</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <meta name="access-token" content="<?= htmlspecialchars($jsAccessToken) ?>">
    <?php include dirname(__DIR__) . '/includes/head-meta.php'; ?>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=6">
</head>
<body>
<div class="d-flex" style="min-height: 100vh;">
    <aside class="admin-sidebar d-none d-md-flex">
        <div class="sidebar-header">
            <img src="/assets/images/final-logo.png" alt="CubeSpace" loading="lazy">
            <span>Admin</span>
        </div>
        <nav class="nav flex-column flex-grow-1 overflow-auto pt-2">
            <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i>Dashboard</a>
            <a href="contacts.php" class="nav-link <?= $currentPage === 'contacts' ? 'active' : '' ?>"><i class="fa-solid fa-message"></i>Enquiries<?php if ($newContactCount > 0): ?><span class="badge bg-danger ms-auto"><?= $newContactCount ?></span><?php endif; ?></a>
            <a href="managed-office.php" class="nav-link <?= $currentPage === 'managed-office' ? 'active' : '' ?>"><i class="fa-solid fa-briefcase"></i>Managed Office</a>
            <a href="office-space.php" class="nav-link <?= $currentPage === 'office-space' ? 'active' : '' ?>"><i class="fa-solid fa-building"></i>Furnished / Unfurnished</a>
            <a href="admins.php" class="nav-link <?= $currentPage === 'admins' ? 'active' : '' ?>"><i class="fa-solid fa-user-shield"></i>Admins</a>
            <a href="activity.php" class="nav-link <?= $currentPage === 'activity' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i>Activity Log</a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <i class="fa-solid fa-circle-user"></i>
                <span><?= htmlspecialchars($adminUser) ?></span>
            </div>
            <a href="logout.php" class="btn btn-outline-light btn-sm w-100"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
        </div>
    </aside>

    <div class="offcanvas offcanvas-start text-bg-dark admin-offcanvas" tabindex="-1" id="adminOffcanvas" aria-label="Admin Navigation">
        <div class="offcanvas-header">
            <span class="fw-bold"><img src="/assets/images/final-logo.png" alt="CubeSpace" style="height: 28px;" class="me-2" loading="lazy">Admin</span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav flex-column gap-1">
                <a href="dashboard.php" class="nav-link text-white <?= $currentPage === 'dashboard' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-gauge-high me-2 w-auto"></i>Dashboard</a>
                <a href="contacts.php" class="nav-link text-white <?= $currentPage === 'contacts' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-message me-2 w-auto"></i>Contacts<?php if ($newContactCount > 0): ?><span class="badge bg-danger ms-auto"><?= $newContactCount ?></span><?php endif; ?></a>
                <a href="managed-office.php" class="nav-link text-white <?= $currentPage === 'managed-office' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-briefcase me-2 w-auto"></i>Managed Office</a>
                <a href="office-space.php" class="nav-link text-white <?= $currentPage === 'office-space' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-building me-2 w-auto"></i>Office Space</a>
                <a href="admins.php" class="nav-link text-white <?= $currentPage === 'admins' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-user-shield me-2 w-auto"></i>Admins</a>
                <a href="activity.php" class="nav-link text-white <?= $currentPage === 'activity' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-clock-rotate-left me-2 w-auto"></i>Activity Log</a>
            </nav>
            <hr class="border-secondary">
            <a href="logout.php" class="btn btn-outline-light btn-sm w-100"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
        </div>
    </div>

    <button class="admin-mobile-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminOffcanvas" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars"></i>
    </button>

    <main class="admin-main">
