<?php
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/../lib/csrf.php';

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

// If access token expired, try refresh token
if (!$loggedIn) {
    $refreshToken = $_COOKIE['refresh_token'] ?? '';
    if ($refreshToken) {
        $payload = jwt_decode($refreshToken);
        if ($payload && ($payload['type'] ?? '') === 'refresh') {
            $newAccess = generate_access_token($payload['sub'], $payload['user']);
            $newRefresh = generate_refresh_token($payload['sub'], $payload['user']);
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

// Pass the validated token to JS so it always has the current non-expired one
$jsAccessToken = $loggedIn ? ($token ?: '') : '';

require_once __DIR__ . '/../api/db_config.php';

$currentPage = $_GET['page'] ?? 'dashboard';
$adminListPage = max(1, (int)($_GET['p'] ?? 1));
$adminPerPage = 50;
$adminOffset = ($adminListPage - 1) * $adminPerPage;
$totalContacts = 0; $totalAdmins = 0; $totalManaged = 0; $totalOffice = 0; $totalActivity = 0;
$recentContacts = null;

// Only run dashboard queries when on dashboard
if ($currentPage === 'dashboard') {
    $stats = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            (SELECT COUNT(*) FROM contacts) as total_contacts,
            (SELECT COUNT(*) FROM admins) as total_admins,
            (SELECT COUNT(*) FROM managed_offices) as total_managed,
            (SELECT COUNT(*) FROM furnished_offices) + (SELECT COUNT(*) FROM unfurnished_offices) as total_office,
            (SELECT COUNT(*) FROM activity_log) as total_activity"
    ));
    $totalContacts = (int)($stats['total_contacts'] ?? 0);
    $totalAdmins   = (int)($stats['total_admins'] ?? 0);
    $totalManaged  = (int)($stats['total_managed'] ?? 0);
    $totalOffice   = (int)($stats['total_office'] ?? 0);
    $totalActivity = (int)($stats['total_activity'] ?? 0);
    $recentContacts = mysqli_query($conn, "SELECT id, name, phone, email, interest, status, created_at, listing_code FROM contacts ORDER BY created_at DESC LIMIT 5");
}

$createMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['page'] ?? '') === 'admins') {
    $csrfPost = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrfPost)) {
        $createMsg = 'Invalid form token. Please refresh the page.';
    } else {
        $newUser = trim($_POST['new_username'] ?? '');
        $newEmail = trim($_POST['new_email'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $newRole = trim($_POST['new_role'] ?? 'admin');
        if ($newUser && $newEmail && strlen($newPass) >= 8) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $createMsg = 'Invalid email format';
            } else {
                if (!in_array($newRole, ['admin', 'super_admin'])) $newRole = 'admin';
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO admins (username, email, password, role) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'ssss', $newUser, $newEmail, $hash, $newRole);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                    $createMsg = 'Admin created successfully';
                } else {
                    $createMsg = 'Username or email already exists or creation failed';
                }
            }
        } else {
            $createMsg = 'Username, email required and password must be 8+ characters';
        }
    }
}
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
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<?php if (!$loggedIn): ?>
<div class="min-vh-100 d-flex align-items-center justify-content-center bg-light px-3">
    <div class="card shadow-sm border-0" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <img src="/assets/images/final-logo.png" alt="CubeSpace" style="height: 44px;" loading="lazy">
                <h5 class="mt-3 mb-1 fw-bold">Admin Login</h5>
                <p class="text-muted small">Sign in to your account</p>
            </div>
            <form id="adminLoginForm">
                <div id="loginMsg" class="alert alert-danger d-none"></div>
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required placeholder="Enter username">
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Enter password">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Sign In</button>
            </form>
            <div class="text-center mt-2">
                <a href="#" id="showForgotPassword" class="text-decoration-none small text-primary">Forgot Password?</a>
            </div>
            <form id="forgotPasswordForm" style="display:none;" class="mt-3">
                <div id="forgotMsg" class="alert alert-info d-none"></div>
                <div class="mb-3">
                    <label for="forgotUsername" class="form-label small fw-semibold text-secondary">Username</label>
                    <input type="text" id="forgotUsername" name="username" class="form-control" required placeholder="Enter your admin username">
                </div>
                <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold">Generate Reset Token</button>
                <div class="text-center mt-2">
                    <a href="#" id="showResetPassword" class="text-decoration-none small text-primary">Have a token? Reset password</a>
                </div>
            </form>
            <form id="resetPasswordForm" style="display:none;" class="mt-3">
                <div id="resetMsg" class="alert alert-info d-none"></div>
                <div class="mb-3">
                    <label for="resetToken" class="form-label small fw-semibold text-secondary">Reset Token</label>
                    <input type="text" id="resetToken" name="token" class="form-control" required placeholder="Paste the reset token here">
                </div>
                <div class="mb-3">
                    <label for="newPassword" class="form-label small fw-semibold text-secondary">New Password</label>
                    <input type="password" id="newPassword" name="password" class="form-control" required minlength="6" placeholder="At least 6 characters">
                </div>
                <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">Reset Password</button>
                <div class="text-center mt-2">
                    <a href="#" id="backToLogin" class="text-decoration-none small text-muted">Back to Login</a>
                </div>
            </form>
            <div class="text-center mt-3">
                <a href="/" class="text-decoration-none small text-muted"><i class="fa-solid fa-arrow-left me-1"></i>Back to website</a>
            </div>
        </div>
    </div>
</div>
<script>
// Toggle between login and forgot password forms
document.getElementById('showForgotPassword').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('adminLoginForm').style.display = 'none';
    document.getElementById('showForgotPassword').parentElement.style.display = 'none';
    document.getElementById('forgotPasswordForm').style.display = 'block';
});

document.getElementById('showResetPassword').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('forgotPasswordForm').style.display = 'none';
    document.getElementById('resetPasswordForm').style.display = 'block';
});

document.getElementById('backToLogin').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('resetPasswordForm').style.display = 'none';
    document.getElementById('forgotPasswordForm').style.display = 'none';
    document.getElementById('adminLoginForm').style.display = 'block';
    document.getElementById('showForgotPassword').parentElement.style.display = 'block';
});

document.getElementById('adminLoginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const msg = document.getElementById('loginMsg');
    const btn = this.querySelector('button');
    const fd = new FormData(this);
    btn.disabled = true; btn.textContent = 'Signing in...';
    msg.classList.add('d-none');
    try {
        const r = await fetch('/admin/login.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            if (d.access_token) sessionStorage.setItem('admin_access_token', d.access_token);
            window.location.reload();
        } else {
            msg.className = 'alert alert-danger'; msg.classList.remove('d-none'); msg.textContent = d.error;
        }
    } catch (err) {
        msg.className = 'alert alert-danger'; msg.classList.remove('d-none'); msg.textContent = err.message || 'Network error';
        console.error('Admin login error:', err);
    }
    btn.disabled = false; btn.textContent = 'Sign In';
});

document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const msg = document.getElementById('forgotMsg');
    const btn = this.querySelector('button');
    const fd = new FormData(this);
    btn.disabled = true; btn.textContent = 'Generating...';
    msg.classList.add('d-none');
    try {
        const r = await fetch('/admin/api/forgot_password.php', { method: 'POST', body: fd });
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch (_) {
            msg.className = 'alert alert-danger'; msg.classList.remove('d-none');
            msg.textContent = 'Unexpected response (status ' + r.status + '). Check console for details.';
            console.error('Forgot password raw response:', text);
            btn.disabled = false; btn.textContent = 'Generate Reset Token';
            return;
        }
        if (d.success) {
            msg.className = 'alert alert-success'; msg.classList.remove('d-none'); 
            msg.innerHTML = 'Reset Token: <strong>' + d.reset_token + '</strong><br><small>Copy this token. It expires in 1 hour.</small>';
            this.reset();
        } else {
            msg.className = 'alert alert-danger'; msg.classList.remove('d-none'); msg.textContent = d.error;
        }
    } catch (err) {
        msg.className = 'alert alert-danger'; msg.classList.remove('d-none'); msg.textContent = err.message || 'Network error';
        console.error('Forgot password error:', err);
    }
    btn.disabled = false; btn.textContent = 'Generate Reset Token';
});

document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const msg = document.getElementById('resetMsg');
    const btn = this.querySelector('button');
    const fd = new FormData(this);
    btn.disabled = true; btn.textContent = 'Resetting...';
    msg.classList.add('d-none');
    try {
        const r = await fetch('/admin/api/reset_password.php', { method: 'POST', body: fd });
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch (_) {
            msg.className = 'alert alert-danger'; msg.classList.remove('d-none');
            msg.textContent = 'Unexpected response (status ' + r.status + '). Check console for details.';
            console.error('Reset password raw response:', text);
            btn.disabled = false; btn.textContent = 'Reset Password';
            return;
        }
        if (d.success) {
            msg.className = 'alert alert-success'; msg.classList.remove('d-none'); 
            msg.textContent = d.message;
            this.reset();
            setTimeout(() => {
                document.getElementById('resetPasswordForm').style.display = 'none';
                document.getElementById('adminLoginForm').style.display = 'block';
                document.getElementById('showForgotPassword').parentElement.style.display = 'block';
            }, 2000);
        } else {
            msg.className = 'alert alert-danger'; msg.classList.remove('d-none'); msg.textContent = d.error;
        }
    } catch (err) {
        msg.className = 'alert alert-danger'; msg.classList.remove('d-none'); msg.textContent = err.message || 'Network error';
        console.error('Reset password error:', err);
    }
    btn.disabled = false; btn.textContent = 'Reset Password';
});
</script>
<?php else: ?>
<div class="d-flex" style="min-height: 100vh;">
    <aside class="admin-sidebar d-none d-md-flex">
        <div class="sidebar-header">
            <img src="/assets/images/final-logo.png" alt="CubeSpace" loading="lazy">
            <span>Admin</span>
        </div>
        <nav class="nav nav-pills flex-column flex-grow-1 overflow-auto pt-2">
            <a href="/admin/" target="_blank" rel="noopener noreferrer" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i>Dashboard</a>
            <a href="/admin/?page=contacts" target="_blank" rel="noopener noreferrer" class="nav-link <?= $currentPage === 'contacts' ? 'active' : '' ?>"><i class="fa-solid fa-message"></i>Contacts</a>
            <a href="/admin/?page=managed-office" target="_blank" rel="noopener noreferrer" class="nav-link <?= $currentPage === 'managed-office' ? 'active' : '' ?>"><i class="fa-solid fa-briefcase"></i>Managed Office</a>
            <a href="/admin/?page=office-space" target="_blank" rel="noopener noreferrer" class="nav-link <?= $currentPage === 'office-space' ? 'active' : '' ?>"><i class="fa-solid fa-building"></i>Office Space</a>
            <a href="/admin/?page=admins" target="_blank" rel="noopener noreferrer" class="nav-link <?= $currentPage === 'admins' ? 'active' : '' ?>"><i class="fa-solid fa-user-shield"></i>Admins</a>
            <a href="/admin/?page=activity" target="_blank" rel="noopener noreferrer" class="nav-link <?= $currentPage === 'activity' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i>Activity Log</a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <i class="fa-solid fa-circle-user"></i>
                <span><?= htmlspecialchars($adminUser) ?></span>
            </div>
            <a href="/admin/logout.php" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm w-100"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
        </div>
    </aside>

    <div class="offcanvas offcanvas-start text-bg-dark admin-offcanvas" tabindex="-1" id="adminOffcanvas" aria-label="Admin Navigation">
        <div class="offcanvas-header">
            <span class="fw-bold"><img src="/assets/images/final-logo.png" alt="CubeSpace" style="height: 28px;" class="me-2" loading="lazy">Admin</span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav nav-pills flex-column gap-1">
                <a href="/admin/" target="_blank" rel="noopener noreferrer" class="nav-link text-white <?= $currentPage === 'dashboard' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-gauge-high me-2 w-auto"></i>Dashboard</a>
                <a href="/admin/?page=contacts" target="_blank" rel="noopener noreferrer" class="nav-link text-white <?= $currentPage === 'contacts' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-message me-2 w-auto"></i>Contacts</a>
                <a href="/admin/?page=managed-office" target="_blank" rel="noopener noreferrer" class="nav-link text-white <?= $currentPage === 'managed-office' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-briefcase me-2 w-auto"></i>Managed Office</a>
                <a href="/admin/?page=office-space" target="_blank" rel="noopener noreferrer" class="nav-link text-white <?= $currentPage === 'office-space' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-building me-2 w-auto"></i>Office Space</a>
                <a href="/admin/?page=admins" target="_blank" rel="noopener noreferrer" class="nav-link text-white <?= $currentPage === 'admins' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-user-shield me-2 w-auto"></i>Admins</a>
                <a href="/admin/?page=activity" target="_blank" rel="noopener noreferrer" class="nav-link text-white <?= $currentPage === 'activity' ? 'active bg-primary' : 'text-white-50' ?>" data-bs-dismiss="offcanvas"><i class="fa-solid fa-clock-rotate-left me-2 w-auto"></i>Activity Log</a>
            </nav>
            <hr class="border-secondary">
            <a href="/admin/logout.php" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm w-100"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
        </div>
    </div>

    <button class="admin-mobile-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminOffcanvas" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars"></i>
    </button>

    <main class="admin-main">
        <?php
        $page = $_GET['page'] ?? 'dashboard';
        if ($page === 'dashboard'): ?>
        <div class="page-header">
            <h4>Dashboard</h4>
            <small class="text-muted">Welcome back, <?= htmlspecialchars($adminUser) ?></small>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg col-xl">
                <div class="stat-card card p-3 text-center">
                    <div class="stat-icon text-primary"><i class="fa-solid fa-message"></i></div>
                    <div class="stat-value"><?= $totalContacts ?></div>
                    <div class="stat-label">Contacts</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg col-xl">
                <div class="stat-card card p-3 text-center">
                    <div class="stat-icon text-info"><i class="fa-solid fa-briefcase"></i></div>
                    <div class="stat-value"><?= $totalManaged ?></div>
                    <div class="stat-label">Managed</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg col-xl">
                <div class="stat-card card p-3 text-center">
                    <div class="stat-icon text-warning"><i class="fa-solid fa-building"></i></div>
                    <div class="stat-value"><?= $totalOffice ?></div>
                    <div class="stat-label">Office Spaces</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg col-xl">
                <div class="stat-card card p-3 text-center">
                    <div class="stat-icon text-secondary"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="stat-value"><?= $totalActivity ?></div>
                    <div class="stat-label">Activity</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg col-xl">
                <div class="stat-card card p-3 text-center">
                    <div class="stat-icon text-danger"><i class="fa-solid fa-user-shield"></i></div>
                    <div class="stat-value"><?= $totalAdmins ?></div>
                    <div class="stat-label">Admins</div>
                </div>
            </div>
        </div>
        <div class="container-fluid px-0">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                            <h6 class="fw-bold mb-0">Recent Contacts</h6>
                            <a href="/admin/?page=contacts" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr><th scope="col">Product Code</th><th scope="col">Name</th><th scope="col">Phone</th><th scope="col">Interest</th><th scope="col">Status</th><th scope="col">Date</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentContacts): while ($c = mysqli_fetch_assoc($recentContacts)): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($c['listing_code'] ?? '—') ?></code></td>
                                        <td class="fw-medium"><?= htmlspecialchars($c['name']) ?></td>
                                        <td><?= htmlspecialchars($c['phone']) ?></td>
                                        <td><?= htmlspecialchars($c['interest'] ?? '—') ?></td>
                                        <td><span class="badge bg-<?= $c['status'] === 'new' ? 'danger' : ($c['status'] === 'contacted' ? 'warning text-dark' : 'success') ?>"><?= $c['status'] ?></span></td>
                                        <td class="text-muted"><?= date('d M', strtotime($c['created_at'])) ?></td>
                                    </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($page === 'contacts'):
            $mode = $_GET['mode'] ?? 'list';
            $statusFilter = $_GET['status'] ?? '';

            if ($mode === 'view'):
                $id = (int)($_GET['id'] ?? 0);
                $stmt = mysqli_prepare($conn, "SELECT * FROM contacts WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                if (!$row) { echo '<div class="alert alert-warning">Contact not found.</div>'; exit; }
        ?>
        <div class="page-header">
            <h4>Contact #<?= $row['id'] ?></h4>
            <a href="/admin/?page=contacts" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2">Updated successfully.</div><?php endif; ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form id="contactDetailForm">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label small text-muted">Name</label><div class="fw-medium"><?= htmlspecialchars($row['name']) ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Phone</label><div class="fw-medium"><?= htmlspecialchars($row['phone']) ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Email</label><div class="fw-medium"><?= htmlspecialchars($row['email']) ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Interest</label><div class="fw-medium"><?= htmlspecialchars($row['interest'] ?? '—') ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Company</label><div class="fw-medium"><?= htmlspecialchars($row['company'] ?? '—') ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Seats</label><div class="fw-medium"><?= htmlspecialchars($row['seats'] ?? '—') ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Listing Code</label><div class="fw-medium"><code><?= htmlspecialchars($row['listing_code'] ?? '—') ?></code></div></div>
                        <?php if ($row['message']): ?>
                        <div class="col-12"><label class="form-label small text-muted">Message</label><div class="fw-medium"><?= nl2br(htmlspecialchars($row['message'])) ?></div></div>
                        <?php endif; ?>
                        <div class="col-md-4"><label class="form-label small text-muted">Submitted</label><div class="fw-medium"><?= $row['created_at'] ?></div></div>
                        <div class="col-md-4">
                            <label for="contact_status" class="form-label small text-muted">Status</label>
                            <select name="status" id="contact_status" class="form-select form-select-sm">
                                <option value="new" <?= $row['status']==='new'?'selected':'' ?>>New</option>
                                <option value="contacted" <?= $row['status']==='contacted'?'selected':'' ?>>Contacted</option>
                                <option value="closed" <?= $row['status']==='closed'?'selected':'' ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="admin_notes" class="form-label small text-muted">Admin Notes</label>
                            <textarea name="admin_notes" id="admin_notes" rows="2" class="form-control form-control-sm" placeholder="Internal notes..."><?= htmlspecialchars($row['admin_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                        <a href="javascript:void(0)" onclick="confirmDeleteContact(<?= $row['id'] ?>)" class="btn btn-outline-danger btn-sm">Delete</a>
                        <a href="/admin/?page=contacts" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    </div>
                </form>
                <div id="contactFormResult" class="alert d-none mt-2"></div>
            </div>
        </div>
        <?php else:
            $where = '';
            $params = [];
            if ($statusFilter && in_array($statusFilter, ['new','contacted','closed'])) {
                $where = " WHERE status = ?";
                $params[] = $statusFilter;
            }
            $totalStmt = !empty($params)
                ? mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM contacts$where")
                : null;
            if ($totalStmt) {
                mysqli_stmt_bind_param($totalStmt, 's', ...$params);
                mysqli_stmt_execute($totalStmt);
                $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['cnt'];
                mysqli_stmt_close($totalStmt);
            } else {
                $total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM contacts"))['cnt'];
            }
            $orderSql = " ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset";
            if (!empty($params)) {
                $stmt = mysqli_prepare($conn, "SELECT * FROM contacts$where$orderSql");
                mysqli_stmt_bind_param($stmt, 's', ...$params);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
            } else {
                $result = mysqli_query($conn, "SELECT * FROM contacts$orderSql");
            }
        ?>
        <div class="page-header">
            <h4>Contact Submissions</h4>
            <span class="badge bg-primary"><?= $total ?> total</span>
        </div>
        <div class="filter-btns">
            <a href="/admin/?page=contacts" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
            <a href="/admin/?page=contacts&status=new" class="btn btn-sm <?= $statusFilter === 'new' ? 'btn-primary' : 'btn-outline-primary' ?>">New</a>
            <a href="/admin/?page=contacts&status=contacted" class="btn btn-sm <?= $statusFilter === 'contacted' ? 'btn-primary' : 'btn-outline-primary' ?>">Contacted</a>
            <a href="/admin/?page=contacts&status=closed" class="btn btn-sm <?= $statusFilter === 'closed' ? 'btn-primary' : 'btn-outline-primary' ?>">Closed</a>
        </div>
        <div class="bulk-bar">
            <select id="bulkActionSelect" class="form-select form-select-sm" aria-label="Bulk actions">
                <option value="">-- Bulk Actions --</option>
                <option value="delete">Delete Selected</option>
                <option value="status-new">Mark as New</option>
                <option value="status-contacted">Mark as Contacted</option>
                <option value="status-closed">Mark as Closed</option>
            </select>
            <button class="btn btn-sm btn-secondary" onclick="applyBulkAction()">Apply</button>
        </div>
        <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success py-2">Deleted successfully.</div><?php endif; ?>
        <div class="admin-card">
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" class="form-check-input checkAll" onchange="toggleAllCheckboxes(this)"></th>
                                <th scope="col">Product Code</th><th scope="col">ID</th><th scope="col">Name</th><th scope="col">Phone</th><th scope="col">Email</th><th scope="col">Interest</th><th scope="col">Status</th><th scope="col">Actions</th><th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input bulk-checkbox" value="<?= $row['id'] ?>"></td>
                                <td><code><?= htmlspecialchars($row['listing_code'] ?? '—') ?></code></td>
                                <td class="text-muted"><?= $row['id'] ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['interest'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $row['status'] === 'new' ? 'danger' : ($row['status'] === 'contacted' ? 'warning text-dark' : 'success') ?>"><?= $row['status'] ?></span></td>
                                <td>
                                    <a href="/admin/?page=contacts&mode=view&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
                                    <a href="javascript:void(0)" onclick="confirmDeleteContact(<?= $row['id'] ?>)" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                                </td>
                                <td class="text-muted"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if ($total > $adminPerPage): ?><div class="mt-3"><?php render_admin_pagination($total, $adminListPage, $adminPerPage, '/admin/?page=' . urlencode($page) . ($statusFilter ? '&status=' . urlencode($statusFilter) : '')); ?></div><?php endif; ?>
        <?php endif; ?>
        <?php elseif ($page === 'managed-office'):
            $mode = $_GET['mode'] ?? 'list';
            $type = 'managed';
            $table = 'managed_offices';
            $typeLabel = 'Managed Office';
            if (!in_array($table, ['managed_offices', 'office_spaces'], true)) { echo '<div class="alert alert-danger">Invalid table</div>'; exit; }

            if ($mode === 'add' || $mode === 'edit'):
                $editId = (int)($_GET['id'] ?? 0);
                $listing = ['title'=>'', 'listing_type'=>$type, 'description'=>'', 'city'=>'', 'area'=>'', 'address'=>'', 'price'=>'', 'price_label'=>'', 'total_seats'=>'', 'total_area_sqft'=>'', 'amenities'=>'[]', 'images'=>'[]', 'status'=>'draft', 'featured'=>0, 'office_space_type'=>'rent'];
                if ($mode === 'edit' && $editId) {
                    $stmt = mysqli_prepare($conn, "SELECT * FROM $table WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'i', $editId);
                    mysqli_stmt_execute($stmt);
                    $r = mysqli_stmt_get_result($stmt);
                    $listing = mysqli_fetch_assoc($r);
                    mysqli_stmt_close($stmt);
                    if (!$listing) { echo '<div class="alert alert-warning">Listing not found.</div>'; exit; }
                }
                $amenities = json_decode($listing['amenities'] ?? '[]', true);
                $images = json_decode($listing['images'] ?? '[]', true);
                $cities = mysqli_query($conn, "SELECT DISTINCT city FROM $table WHERE city != '' ORDER BY city");
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><?= $mode === 'add' ? 'Add' : 'Edit' ?> <?= $typeLabel ?></h4>
            <a href="/admin/?page=managed-office" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form id="listingForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="id" value="<?= $editId ?>">
                    <input type="hidden" name="listing_type" value="<?= $type ?>">
                    <input type="hidden" name="existing_images" id="existingImages" value='<?= htmlspecialchars(json_encode($images)) ?>'>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control form-control-sm" required value="<?= htmlspecialchars($listing['title']) ?>" placeholder="e.g. DLF Downtown">
                        </div>
                        <div class="col-md-3">
                            <label for="city" class="form-label small fw-semibold">City</label>
                            <select name="city" id="city" class="form-select form-select-sm">
                                <option value="">- Select -</option>
                                <?php if ($cities): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
                                <option value="<?= htmlspecialchars($c['city']) ?>" <?= $listing['city']===$c['city']?'selected':'' ?>><?= htmlspecialchars(ucfirst($c['city'])) ?></option>
                                <?php endwhile; endif; ?>
                                <option value="chennai" <?= $listing['city']==='chennai'?'selected':'' ?>>Chennai</option>
                                <option value="bangalore" <?= $listing['city']==='bangalore'?'selected':'' ?>>Bangalore</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="area" class="form-label small fw-semibold">Area / Locality</label>
                            <input type="text" name="area" id="area" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['area']??'') ?>" placeholder="e.g. OMR">
                        </div>
                        <div class="col-md-3">
                            <label for="total_seats" class="form-label small fw-semibold">Total Seats</label>
                            <input type="number" name="total_seats" id="total_seats" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['total_seats']??'') ?>" placeholder="e.g. 50">
                        </div>
                        <div class="col-md-3">
                            <label for="total_area_sqft" class="form-label small fw-semibold">Area (sq.ft)</label>
                            <input type="number" name="total_area_sqft" id="total_area_sqft" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['total_area_sqft']??'') ?>" placeholder="e.g. 2000">
                        </div>
                        <div class="col-md-3">
                            <label for="price" class="form-label small fw-semibold">Price</label>
                            <input type="number" step="0.01" name="price" id="price" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['price']??'') ?>" placeholder="e.g. 150000">
                        </div>
                        <div class="col-md-3">
                            <label for="price_label" class="form-label small fw-semibold">Price Label</label>
                            <input type="text" name="price_label" id="price_label" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['price_label']??'') ?>" placeholder="e.g. ₹1.5 Lakhs/mo">
                        </div>
                        <div class="col-md-3">
                            <label for="officeSpaceType" class="form-label small fw-semibold">Office Space Type</label>
                            <select name="office_space_type" class="form-select form-select-sm" id="officeSpaceType">
                                <option value="rent" <?= ($listing['office_space_type'] ?? 'rent') === 'rent' ? 'selected' : '' ?>>Rent (Monthly)</option>
                                <option value="lease" <?= ($listing['office_space_type'] ?? 'rent') === 'lease' ? 'selected' : '' ?>>Lease (Yearly)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label small fw-semibold">Status</label>
                            <select name="status" id="status" class="form-select form-select-sm">
                                <option value="draft" <?= $listing['status']==='draft'?'selected':'' ?>>Draft</option>
                                <option value="published" <?= $listing['status']==='published'?'selected':'' ?>>Published</option>
                                <option value="archived" <?= $listing['status']==='archived'?'selected':'' ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label small fw-semibold">Address</label>
                            <textarea name="address" id="address" class="form-control form-control-sm" rows="2" placeholder="Full address"><?= htmlspecialchars($listing['address']??'') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label small fw-semibold">Description</label>
                            <textarea name="description" id="description" class="form-control form-control-sm" rows="3" placeholder="Describe the property"><?= htmlspecialchars($listing['description']??'') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="amenities" class="form-label small fw-semibold">Amenities (comma separated)</label>
                            <input type="text" name="amenities" id="amenities" class="form-control form-control-sm" value="<?= htmlspecialchars(implode(', ', $amenities)) ?>" placeholder="WiFi, AC, Parking">
                        </div>
                        <div class="col-md-6">
                            <label for="feature_highlights" class="form-label small fw-semibold">Feature Highlights (one per line)</label>
                            <textarea name="feature_highlights" id="feature_highlights" class="form-control form-control-sm" rows="2" placeholder="Fully Furnished&#10;24/7 Power Backup"><?= htmlspecialchars(implode("\n", json_decode($listing['feature_highlights'] ?? '[]', true) ?: [])) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="seo_text" class="form-label small fw-semibold">SEO Text</label>
                            <textarea name="seo_text" id="seo_text" class="form-control form-control-sm" rows="3" placeholder="<h3>About this Workspace</h3>"><?= htmlspecialchars($listing['seo_text'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="images" class="form-label small fw-semibold">Images</label>
                            <input type="file" name="images[]" id="images" class="form-control form-control-sm" accept="image/*" multiple>
                            <?php if (!empty($images)): ?>
                            <div class="d-flex flex-wrap gap-2 mt-2" id="existingImagesContainer">
                                <?php foreach ($images as $img):
                                    $imgPath = $_SERVER['DOCUMENT_ROOT'] . $img;
                                    $imgExists = file_exists($imgPath);
                                ?>
                                <div class="position-relative" data-src="<?= htmlspecialchars($img) ?>">
                                    <?php if ($imgExists): ?>
                                    <img src="<?= htmlspecialchars($img) ?>" class="rounded border" style="width: 70px; height: 70px; object-fit: cover;" loading="lazy" alt="Listing image">
                                    <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center rounded border bg-light" style="width:70px;height:70px;"><i class="fa-solid fa-image text-muted"></i></div>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" style="font-size: 10px; line-height: 1; padding: 1px 5px;" onclick="this.parentElement.remove()">&times;</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="featured" value="1" class="form-check-input" id="featuredCheck" <?= $listing['featured']?'checked':'' ?>>
                                <label class="form-check-label small" for="featuredCheck">Featured listing</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><?= $mode === 'add' ? 'Create Listing' : 'Update Listing' ?></button>
                        <a href="/admin/?page=managed-office" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    </div>
                </form>
                <div id="formResult" class="alert d-none mt-2"></div>
            </div>
        </div>
        <?php else:
            $statusFilter = $_GET['status'] ?? '';
            $cityFilter = $_GET['city'] ?? '';
            $featuredFilter = $_GET['featured'] ?? '';

            $where = [];
            $params = [];
            if ($statusFilter && in_array($statusFilter, ['draft','published','archived'])) {
                $where[] = "status = ?";
                $params[] = $statusFilter;
            }
            if ($cityFilter) {
                $where[] = "city = ?";
                $params[] = $cityFilter;
            }
            if ($featuredFilter === 'yes') {
                $where[] = "featured = 1";
            } elseif ($featuredFilter === 'no') {
                $where[] = "featured = 0";
            }
            $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
            $totalStmt = !empty($params)
                ? mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM $table$whereClause")
                : null;
            if ($totalStmt) {
                mysqli_stmt_bind_param($totalStmt, str_repeat('s', count($params)), ...$params);
                mysqli_stmt_execute($totalStmt);
                $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['cnt'];
                mysqli_stmt_close($totalStmt);
            } else {
                $total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM $table"))['cnt'];
            }
            $orderSql = " ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset";
            if (!empty($params)) {
                $stmt = mysqli_prepare($conn, "SELECT * FROM $table$whereClause$orderSql");
                mysqli_stmt_bind_param($stmt, str_repeat('s', count($params)), ...$params);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
            } else {
                $result = mysqli_query($conn, "SELECT * FROM $table$whereClause$orderSql");
            }
            $cities = mysqli_query($conn, "SELECT DISTINCT city FROM $table WHERE city != '' ORDER BY city");
        ?>
        <div class="page-header">
            <h4><?= $typeLabel ?></h4>
            <div class="d-flex gap-2">
                <span class="badge bg-primary"><?= $total ?> listings</span>
                <a href="/admin/?page=managed-office&mode=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Add</a>
            </div>
        </div>
        <div class="filter-btns">
            <a href="/admin/?page=managed-office" class="btn btn-sm <?= !$statusFilter && !$cityFilter && !$featuredFilter ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
            <a href="/admin/?page=managed-office&status=draft" class="btn btn-sm <?= $statusFilter === 'draft' ? 'btn-primary' : 'btn-outline-primary' ?>">Draft</a>
            <a href="/admin/?page=managed-office&status=published" class="btn btn-sm <?= $statusFilter === 'published' ? 'btn-primary' : 'btn-outline-primary' ?>">Published</a>
            <a href="/admin/?page=managed-office&status=archived" class="btn btn-sm <?= $statusFilter === 'archived' ? 'btn-primary' : 'btn-outline-primary' ?>">Archived</a>
            <?php if ($cities): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
            <a href="/admin/?page=managed-office&city=<?= htmlspecialchars(urlencode($c['city'])) ?>" class="btn btn-sm <?= $cityFilter === $c['city'] ? 'btn-primary' : 'btn-outline-primary' ?>"><?= htmlspecialchars(ucfirst($c['city'])) ?></a>
            <?php endwhile; endif; ?>
            <a href="/admin/?page=managed-office&featured=yes" class="btn btn-sm <?= $featuredFilter === 'yes' ? 'btn-primary' : 'btn-outline-primary' ?>"><i class="fa-solid fa-star me-1"></i>Featured</a>
        </div>
        <div class="bulk-bar">
            <select id="bulkActionSelect" class="form-select form-select-sm" aria-label="Bulk actions">
                <option value="">-- Bulk Actions --</option>
                <option value="delete">Delete Selected</option>
                <option value="status-draft">Mark as Draft</option>
                <option value="status-published">Mark as Published</option>
                <option value="status-archived">Mark as Archived</option>
                <option value="featured-1">Mark as Featured</option>
                <option value="featured-0">Mark as Unfeatured</option>
            </select>
            <button class="btn btn-sm btn-secondary" onclick="applyBulkAction()">Apply</button>
        </div>
        <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success py-2">Listing deleted.</div><?php endif; ?>
        <?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2">Listing saved.</div><?php endif; ?>
        <div class="admin-card">
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" class="form-check-input checkAll" onchange="toggleAllCheckboxes(this)"></th>
                                <th scope="col">ID</th><th scope="col">Title</th><th scope="col">City</th><th scope="col">Area</th><th scope="col">Seats</th><th scope="col">Price</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Featured</th><th scope="col">Actions</th><th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)):
                                $rowImages = json_decode($row['images'] ?? '[]', true);
                            ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input bulk-checkbox" value="<?= $row['id'] ?>"></td>
                                <td class="text-muted"><?= $row['id'] ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['city']) ?></td>
                                <td><?= htmlspecialchars($row['area'] ?? '—') ?></td>
                                <td><?= $row['total_seats'] ?? '—' ?></td>
                                <td><?= $row['price'] ? '₹' . number_format($row['price']) . '<small class="text-muted ms-1">' . ($row['office_space_type'] === 'lease' ? '/yr' : '/mo') . '</small>' : '—' ?></td>
                                <td><span class="badge bg-<?= ($row['office_space_type'] ?? 'rent') === 'lease' ? 'info' : 'secondary' ?>"><?= htmlspecialchars(($row['office_space_type'] ?? 'rent')) ?></span></td>
                                <td><span class="badge bg-<?= $row['status'] === 'published' ? 'success' : ($row['status'] === 'draft' ? 'secondary' : 'warning text-dark') ?>"><?= $row['status'] ?></span></td>
                                <td><?= $row['featured'] ? '<i class="fa-solid fa-star text-warning"></i>' : '—' ?></td>
                                <td>
                                    <a href="/office_detail.php?slug=<?= htmlspecialchars($row['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
                                    <a href="/admin/?page=managed-office&mode=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="/admin/?page=office-details&office_id=<?= $row['id'] ?>&tab=reviews" class="btn btn-sm btn-outline-secondary" title="Details"><i class="fa-solid fa-list-check"></i></a>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id'] ?>, 'managed', '<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>')" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                                </td>
                                <td class="text-muted"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if ($total > $adminPerPage): ?><div class="mt-3"><?php render_admin_pagination($total, $adminListPage, $adminPerPage, '/admin/?page=' . urlencode($page) . ($statusFilter ? '&status=' . urlencode($statusFilter) : '')); ?></div><?php endif; ?>
        <?php endif; ?>
        <?php elseif ($page === 'office-space'):
            $mode = $_GET['mode'] ?? 'list';
            $editType = $_GET['type'] ?? 'commercial';
            $typeLabel = 'Office Space';

            if ($mode === 'add' || $mode === 'edit'):
                $editId = (int)($_GET['id'] ?? 0);
                if ($mode === 'edit' && $editId) {
                    $editTable = get_listing_table($editType);
                    $stmt = mysqli_prepare($conn, "SELECT * FROM $editTable WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'i', $editId);
                    mysqli_stmt_execute($stmt);
                    $r = mysqli_stmt_get_result($stmt);
                    $listing = mysqli_fetch_assoc($r);
                    mysqli_stmt_close($stmt);
                    if (!$listing) { echo '<div class="alert alert-warning">Listing not found.</div>'; exit; }
                    $type = $editType;
                } else {
                    $listing = ['title'=>'', 'listing_type'=>'commercial', 'description'=>'', 'city'=>'', 'area'=>'', 'address'=>'', 'price'=>'', 'price_label'=>'', 'total_seats'=>'', 'total_area_sqft'=>'', 'available_sqft'=>'', 'min_inventory'=>'', 'inventory_type'=>'', 'amenities'=>'[]', 'images'=>'[]', 'status'=>'draft', 'featured'=>0, 'office_space_type'=>'rent', 'feature_highlights'=>'[]', 'seo_text'=>''];
                    $type = 'commercial';
                }
                $isFurnished = in_array($type, ['furnished', 'unfurnished'], true);
                $amenities = json_decode($listing['amenities'] ?? '[]', true);
                $images = json_decode($listing['images'] ?? '[]', true);
                $cities = mysqli_query($conn, "SELECT DISTINCT city FROM (SELECT city FROM furnished_offices WHERE city != '' UNION SELECT city FROM unfurnished_offices WHERE city != '' UNION SELECT city FROM office_spaces WHERE city != '') c ORDER BY city");
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><?= $mode === 'add' ? 'Add' : 'Edit' ?> <?= $typeLabel ?></h4>
            <a href="/admin/?page=office-space" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form id="listingForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="id" value="<?= $editId ?>">
                    <input type="hidden" name="existing_images" id="existingImages" value='<?= htmlspecialchars(json_encode($images)) ?>'>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control form-control-sm" required value="<?= htmlspecialchars($listing['title']) ?>" placeholder="e.g. RMZ Millenia">
                        </div>
                        <div class="col-md-3">
                            <label for="listingFurnishingType" class="form-label small fw-semibold">Furnishing Type</label>
                            <select name="listing_type" id="listingFurnishingType" class="form-select form-select-sm">
                                <option value="commercial" <?= $type === 'commercial' ? 'selected' : '' ?>>All Products (commercial)</option>
                                <option value="furnished" <?= $type === 'furnished' ? 'selected' : '' ?>>Managed Furnished Office</option>
                                <option value="unfurnished" <?= $type === 'unfurnished' ? 'selected' : '' ?>>Furnished / Unfurnished Office</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="city" class="form-label small fw-semibold">City</label>
                            <select name="city" id="city" class="form-select form-select-sm">
                                <option value="">- Select -</option>
                                <?php if ($cities): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
                                <option value="<?= htmlspecialchars($c['city']) ?>" <?= ($listing['city']??'')===$c['city']?'selected':'' ?>><?= htmlspecialchars(ucfirst($c['city'])) ?></option>
                                <?php endwhile; endif; ?>
                                <option value="chennai" <?= ($listing['city']??'')==='chennai'?'selected':'' ?>>Chennai</option>
                                <option value="bangalore" <?= ($listing['city']??'')==='bangalore'?'selected':'' ?>>Bangalore</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="area" class="form-label small fw-semibold">Area / Locality</label>
                            <input type="text" name="area" id="area" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['area']??'') ?>" placeholder="e.g. OMR">
                        </div>
                        <div class="col-md-3">
                            <label for="total_seats" class="form-label small fw-semibold">Total Seats</label>
                            <input type="number" name="total_seats" id="total_seats" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['total_seats']??'') ?>" placeholder="e.g. 100">
                        </div>
                        <div class="col-md-3">
                            <label for="total_area_sqft" class="form-label small fw-semibold">Area (sq.ft)</label>
                            <input type="number" name="total_area_sqft" id="total_area_sqft" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['total_area_sqft']??'') ?>" placeholder="e.g. 5000">
                        </div>
                        <?php if ($isFurnished): ?>
                        <div class="col-md-3">
                            <label for="available_sqft" class="form-label small fw-semibold">Available Sq. Ft.</label>
                            <input type="number" name="available_sqft" id="available_sqft" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['available_sqft']??'') ?>" placeholder="e.g. 2000">
                        </div>
                        <div class="col-md-3">
                            <label for="min_inventory" class="form-label small fw-semibold">Min Inventory</label>
                            <input type="number" name="min_inventory" id="min_inventory" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['min_inventory']??'') ?>" placeholder="e.g. 10">
                        </div>
                        <div class="col-md-3">
                            <label for="inventory_type" class="form-label small fw-semibold">Inventory Type</label>
                            <select name="inventory_type" id="inventory_type" class="form-select form-select-sm">
                                <option value="seats" <?= ($listing['inventory_type']??'')==='seats'?'selected':'' ?>>Seats</option>
                                <option value="cabins" <?= ($listing['inventory_type']??'')==='cabins'?'selected':'' ?>>Cabins</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <label for="price" class="form-label small fw-semibold">Price</label>
                            <input type="number" step="0.01" name="price" id="price" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['price']??'') ?>" placeholder="e.g. 300000">
                        </div>
                        <div class="col-md-3">
                            <label for="price_label" class="form-label small fw-semibold">Price Label</label>
                            <input type="text" name="price_label" id="price_label" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['price_label']??'') ?>" placeholder="e.g. ₹3 Lakhs/mo">
                        </div>
                        <div class="col-md-3">
                            <label for="officeSpaceType2" class="form-label small fw-semibold">Office Space Type</label>
                            <select name="office_space_type" class="form-select form-select-sm" id="officeSpaceType2">
                                <option value="rent" <?= ($listing['office_space_type'] ?? 'rent') === 'rent' ? 'selected' : '' ?>>Rent (Monthly)</option>
                                <option value="lease" <?= ($listing['office_space_type'] ?? 'rent') === 'lease' ? 'selected' : '' ?>>Lease (Yearly)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label small fw-semibold">Status</label>
                            <select name="status" id="status" class="form-select form-select-sm">
                                <option value="draft" <?= $listing['status']==='draft'?'selected':'' ?>>Draft</option>
                                <option value="published" <?= $listing['status']==='published'?'selected':'' ?>>Published</option>
                                <option value="archived" <?= $listing['status']==='archived'?'selected':'' ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label small fw-semibold">Address</label>
                            <textarea name="address" id="address" class="form-control form-control-sm" rows="2" placeholder="Full address"><?= htmlspecialchars($listing['address']??'') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label small fw-semibold">Description</label>
                            <textarea name="description" id="description" class="form-control form-control-sm" rows="3" placeholder="Describe the property"><?= htmlspecialchars($listing['description']??'') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="amenities" class="form-label small fw-semibold">Amenities (comma separated)</label>
                            <input type="text" name="amenities" id="amenities" class="form-control form-control-sm" value="<?= htmlspecialchars(implode(', ', $amenities)) ?>" placeholder="WiFi, AC, Parking">
                        </div>
                        <?php if (!$isFurnished): ?>
                        <div class="col-md-6">
                            <label for="feature_highlights" class="form-label small fw-semibold">Feature Highlights (one per line)</label>
                            <textarea name="feature_highlights" id="feature_highlights" class="form-control form-control-sm" rows="2" placeholder="Fully Furnished&#10;24/7 Power Backup"><?= htmlspecialchars(implode("\n", json_decode($listing['feature_highlights'] ?? '[]', true) ?: [])) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="seo_text" class="form-label small fw-semibold">SEO Text</label>
                            <textarea name="seo_text" id="seo_text" class="form-control form-control-sm" rows="3" placeholder="<h3>About this Workspace</h3>"><?= htmlspecialchars($listing['seo_text'] ?? '') ?></textarea>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label for="images" class="form-label small fw-semibold">Images</label>
                            <input type="file" name="images[]" id="images" class="form-control form-control-sm" accept="image/*" multiple>
                            <?php if (!empty($images)): ?>
                            <div class="d-flex flex-wrap gap-2 mt-2" id="existingImagesContainer">
                                <?php foreach ($images as $img):
                                    $imgPath = $_SERVER['DOCUMENT_ROOT'] . $img;
                                    $imgExists = file_exists($imgPath);
                                ?>
                                <div class="position-relative" data-src="<?= htmlspecialchars($img) ?>">
                                    <?php if ($imgExists): ?>
                                    <img src="<?= htmlspecialchars($img) ?>" class="rounded border" style="width: 70px; height: 70px; object-fit: cover;" loading="lazy" alt="Listing image">
                                    <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center rounded border bg-light" style="width:70px;height:70px;"><i class="fa-solid fa-image text-muted"></i></div>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" style="font-size: 10px; line-height: 1; padding: 1px 5px;" onclick="this.parentElement.remove()">&times;</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="featured" value="1" class="form-check-input" id="featuredCheck" <?= $listing['featured']?'checked':'' ?>>
                                <label class="form-check-label small" for="featuredCheck">Featured listing</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><?= $mode === 'add' ? 'Create Listing' : 'Update Listing' ?></button>
                        <a href="/admin/?page=office-space" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    </div>
                </form>
                <div id="formResult" class="alert d-none mt-2"></div>
            </div>
        </div>
        <?php else:
            $statusFilter = $_GET['status'] ?? '';
            $cityFilter = $_GET['city'] ?? '';
            $featuredFilter = $_GET['featured'] ?? '';

            $where = [];
            $params = [];
            if ($statusFilter && in_array($statusFilter, ['draft','published','archived'])) {
                $where[] = "status = ?";
                $params[] = $statusFilter;
            }
            if ($cityFilter) {
                $where[] = "city = ?";
                $params[] = $cityFilter;
            }
            if ($featuredFilter === 'yes') {
                $where[] = "featured = 1";
            } elseif ($featuredFilter === 'no') {
                $where[] = "featured = 0";
            }
            $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
            $params2x = !empty($params) ? array_merge($params, $params) : [];
            $totalSql = !empty($params)
                ? "SELECT COUNT(*) as cnt FROM ((SELECT 1 FROM furnished_offices$whereClause) UNION ALL (SELECT 1 FROM unfurnished_offices$whereClause)) t"
                : null;
            if ($totalSql) {
                $totalStmt = mysqli_prepare($conn, $totalSql);
                if ($totalStmt) {
                    $typesStr = str_repeat('s', count($params2x));
                    mysqli_stmt_bind_param($totalStmt, $typesStr, ...$params2x);
                    mysqli_stmt_execute($totalStmt);
                    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['cnt'];
                    mysqli_stmt_close($totalStmt);
                } else {
                    $total = 0;
                }
            } else {
                $total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT (SELECT COUNT(*) FROM furnished_offices) + (SELECT COUNT(*) FROM unfurnished_offices) as cnt"))['cnt'];
            }
            $orderSql = " ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset";
            $colList = "id, title, description, city, area, address, price, price_label, total_seats, total_area_sqft, amenities, images, status, featured, office_space_type, NULL as available_sqft, NULL as min_inventory, NULL as inventory_type, feature_highlights, seo_text, created_at, 'furnished' as listing_type_db FROM furnished_offices";
            $colList2 = "id, title, description, city, area, address, price, price_label, total_seats, total_area_sqft, amenities, images, status, featured, office_space_type, available_sqft, min_inventory, inventory_type, NULL as feature_highlights, NULL as seo_text, created_at, 'unfurnished' as listing_type_db FROM unfurnished_offices";
            if (!empty($params)) {
                $sql = "SELECT * FROM (($colList$whereClause) UNION ALL ($colList2$whereClause)) combined$orderSql";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    $typesStr = str_repeat('s', count($params2x));
                    mysqli_stmt_bind_param($stmt, $typesStr, ...$params2x);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                } else {
                    $result = null;
                }
            } else {
                $sql = "SELECT * FROM (($colList) UNION ALL ($colList2)) combined$orderSql";
                $result = mysqli_query($conn, $sql);
            }
            $cities = mysqli_query($conn, "SELECT DISTINCT city FROM (SELECT city FROM furnished_offices WHERE city != '' UNION SELECT city FROM unfurnished_offices WHERE city != '') c ORDER BY city");
        ?>
        <div class="page-header">
            <h4><?= $typeLabel ?></h4>
            <div class="d-flex gap-2">
                <span class="badge bg-primary"><?= $total ?> listings</span>
                <a href="/admin/?page=office-space&mode=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Add</a>
            </div>
        </div>
        <div class="filter-btns">
            <a href="/admin/?page=office-space" class="btn btn-sm <?= !$statusFilter && !$cityFilter && !$featuredFilter ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
            <a href="/admin/?page=office-space&status=draft" class="btn btn-sm <?= $statusFilter === 'draft' ? 'btn-primary' : 'btn-outline-primary' ?>">Draft</a>
            <a href="/admin/?page=office-space&status=published" class="btn btn-sm <?= $statusFilter === 'published' ? 'btn-primary' : 'btn-outline-primary' ?>">Published</a>
            <a href="/admin/?page=office-space&status=archived" class="btn btn-sm <?= $statusFilter === 'archived' ? 'btn-primary' : 'btn-outline-primary' ?>">Archived</a>
            <?php if ($cities): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
            <a href="/admin/?page=office-space&city=<?= htmlspecialchars(urlencode($c['city'])) ?>" class="btn btn-sm <?= $cityFilter === $c['city'] ? 'btn-primary' : 'btn-outline-primary' ?>"><?= htmlspecialchars(ucfirst($c['city'])) ?></a>
            <?php endwhile; endif; ?>
            <a href="/admin/?page=office-space&featured=yes" class="btn btn-sm <?= $featuredFilter === 'yes' ? 'btn-primary' : 'btn-outline-primary' ?>"><i class="fa-solid fa-star me-1"></i>Featured</a>
        </div>
        <div class="bulk-bar">
            <select id="bulkActionSelect" class="form-select form-select-sm" aria-label="Bulk actions">
                <option value="">-- Bulk Actions --</option>
                <option value="delete">Delete Selected</option>
                <option value="status-draft">Mark as Draft</option>
                <option value="status-published">Mark as Published</option>
                <option value="status-archived">Mark as Archived</option>
                <option value="featured-1">Mark as Featured</option>
                <option value="featured-0">Mark as Unfeatured</option>
            </select>
            <button class="btn btn-sm btn-secondary" onclick="applyBulkAction()">Apply</button>
        </div>
        <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success py-2">Deleted.</div><?php endif; ?>
        <?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2">Saved.</div><?php endif; ?>
        <div class="admin-card">
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" class="form-check-input checkAll" onchange="toggleAllCheckboxes(this)"></th>
                                <th scope="col">ID</th><th scope="col">Title</th><th scope="col">City</th><th scope="col">Area</th><th scope="col">Seats</th><th scope="col">Price</th><th scope="col">Furnishing</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Featured</th><th scope="col">Actions</th><th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)):
                                $rowImages = json_decode($row['images'] ?? '[]', true);
                                $lt = $row['listing_type_db'] ?? 'furnished';
                            ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input bulk-checkbox" value="<?= $row['id'] ?>" data-type="<?= htmlspecialchars($lt) ?>"></td>
                                <td class="text-muted"><?= $row['id'] ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['city']) ?></td>
                                <td><?= htmlspecialchars($row['area'] ?? '—') ?></td>
                                <td><?= $row['total_seats'] ?? '—' ?></td>
                                <td><?= $row['price'] ? '₹' . number_format($row['price']) . '<small class="text-muted ms-1">' . ($row['office_space_type'] === 'lease' ? '/yr' : '/mo') . '</small>' : '—' ?></td>
                                <td><span class="badge bg-<?= $lt === 'furnished' ? 'success' : 'secondary' ?>"><?= htmlspecialchars(ucfirst($lt)) ?></span></td>
                                <td><span class="badge bg-<?= ($row['office_space_type'] ?? 'rent') === 'lease' ? 'info' : 'secondary' ?>"><?= htmlspecialchars(($row['office_space_type'] ?? 'rent')) ?></span></td>
                                <td><span class="badge bg-<?= $row['status'] === 'published' ? 'success' : ($row['status'] === 'draft' ? 'secondary' : 'warning text-dark') ?>"><?= $row['status'] ?></span></td>
                                <td><?= $row['featured'] ? '<i class="fa-solid fa-star text-warning"></i>' : '—' ?></td>
                                <td>
                                    <a href="/office_detail.php?slug=<?= htmlspecialchars(urlencode($row['slug'] ?? '')) ?>" class="btn btn-sm btn-outline-info" title="View" target="_blank"><i class="fa-solid fa-eye"></i></a>
                                    <a href="/admin/?page=office-space&mode=edit&id=<?= $row['id'] ?>&type=<?= htmlspecialchars($lt) ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($lt) ?>', '<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>')" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                                </td>
                                <td class="text-muted"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if ($total > $adminPerPage): ?><div class="mt-3"><?php render_admin_pagination($total, $adminListPage, $adminPerPage, '/admin/?page=' . urlencode($page) . ($statusFilter ? '&status=' . urlencode($statusFilter) : '')); ?></div><?php endif; ?>
        <?php endif; ?>
        <?php elseif ($page === 'activity'):
            $activityTotal = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM activity_log"))['cnt'];
            $result = mysqli_query($conn, "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset");
        ?>
        <div class="page-header">
            <h4>Activity Log</h4>
            <span class="badge bg-primary"><?= $activityTotal ?> entries</span>
        </div>
        <div class="admin-card">
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th scope="col">ID</th><th scope="col">Admin</th><th scope="col">Action</th><th scope="col">Table</th><th scope="col">Record</th><th scope="col">IP</th><th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)):
                                $details = json_decode($row['details'] ?? '{}', true);
                            ?>
                            <tr>
                                <td class="text-muted"><?= $row['id'] ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($row['admin_username']) ?></td>
                                <td><span class="badge bg-<?= $row['action'] === 'delete' ? 'danger' : ($row['action'] === 'create' ? 'success' : 'info') ?>"><?= htmlspecialchars($row['action']) ?></span></td>
                                <td><?= htmlspecialchars($row['table_name'] ?? '—') ?></td>
                                <td><?= $row['record_id'] ?? '—' ?></td>
                                <td class="text-muted"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></td>
                                <td class="text-muted"><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if ($activityTotal > $adminPerPage): ?><div class="mt-3"><?php render_admin_pagination($activityTotal, $adminListPage, $adminPerPage, '/admin/?page=' . urlencode($page)); ?></div><?php endif; ?>
        <?php elseif ($page === 'admins'):
            $result = mysqli_query($conn, "SELECT id, username, created_at FROM admins ORDER BY created_at DESC");
        ?>
        <div class="page-header">
            <h4>Manage Admins</h4>
        </div>
        <?php if ($createMsg): ?>
        <div class="alert alert-<?= strpos($createMsg, 'successfully') !== false ? 'success' : 'danger' ?> py-2"><?= htmlspecialchars($createMsg) ?></div>
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-md-5">
                <div class="admin-card">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Create New Admin</h6>
                            <form method="POST" action="/admin/?page=admins">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <div class="mb-2">
                                    <label for="new_username" class="form-label small fw-semibold">Username</label>
                                    <input type="text" id="new_username" name="new_username" required class="form-control form-control-sm" placeholder="Username">
                                </div>
                                <div class="mb-2">
                                    <label for="new_email" class="form-label small fw-semibold">Email</label>
                                    <input type="email" id="new_email" name="new_email" required class="form-control form-control-sm" placeholder="Email">
                                </div>
                                <div class="mb-2">
                                    <label for="new_password" class="form-label small fw-semibold">Password (min 8 chars)</label>
                                    <input type="password" id="new_password" name="new_password" required class="form-control form-control-sm" placeholder="Password (min 8 chars)">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Create Admin</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="admin-card">
                        <div class="table-wrap">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead>
                                        <tr><th scope="col">ID</th><th scope="col">Username</th><th scope="col">Created</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td class="text-muted"><?= $row['id'] ?></td>
                                            <td class="fw-medium"><?= htmlspecialchars($row['username']) ?></td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($page === 'office-details'):
            $detailTab = $_GET['tab'] ?? 'reviews';
            $selectedOffice = (int)($_GET['office_id'] ?? 0);
            $allOffices = mysqli_query($conn, "SELECT id, title, slug FROM managed_offices WHERE status='published' ORDER BY title");
        ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Office Details Management</h4>
        </div>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <label for="detailOfficeSelect" class="form-label small fw-semibold">Select Office</label>
                <select id="detailOfficeSelect" class="form-select form-select-sm" onchange="window.location='/admin/?page=office-details&office_id='+this.value+'&tab=<?= $detailTab ?>'">
                    <option value="0">— Select an office —</option>
                    <?php while ($o = mysqli_fetch_assoc($allOffices)): ?>
                    <option value="<?= $o['id'] ?>" <?= $selectedOffice==$o['id']?'selected':'' ?>><?= htmlspecialchars($o['title']) ?> (ID: <?= $o['id'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <?php if ($selectedOffice): ?>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <a href="/admin/?page=office-details&office_id=<?= $selectedOffice ?>&tab=reviews" class="btn btn-sm <?= $detailTab==='reviews'?'btn-primary':'btn-outline-primary' ?>">Reviews</a>
            <a href="/admin/?page=office-details&office_id=<?= $selectedOffice ?>&tab=faq" class="btn btn-sm <?= $detailTab==='faq'?'btn-primary':'btn-outline-primary' ?>">FAQ</a>
            <a href="/admin/?page=office-details&office_id=<?= $selectedOffice ?>&tab=building" class="btn btn-sm <?= $detailTab==='building'?'btn-primary':'btn-outline-primary' ?>">Building</a>
            <a href="/admin/?page=office-details&office_id=<?= $selectedOffice ?>&tab=leasing" class="btn btn-sm <?= $detailTab==='leasing'?'btn-primary':'btn-outline-primary' ?>">Leasing</a>
            <a href="/admin/?page=office-details&office_id=<?= $selectedOffice ?>&tab=extras" class="btn btn-sm <?= $detailTab==='extras'?'btn-primary':'btn-outline-primary' ?>">Extras</a>
            <a href="/admin/?page=office-details&office_id=<?= $selectedOffice ?>&tab=connectivity" class="btn btn-sm <?= $detailTab==='connectivity'?'btn-primary':'btn-outline-primary' ?>">Connectivity</a>
        </div>
        <?php if ($detailTab === 'reviews'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Reviews</h6>
            <button class="btn btn-primary btn-sm" onclick="showReviewForm()"><i class="fa-solid fa-plus me-1"></i>Add Review</button>
        </div>
        <div id="reviewFormWrap" class="card border-0 shadow-sm mb-3 d-none">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Add / Edit Review</h6>
                <form id="reviewForm" onsubmit="saveReview(event)">
                    <input type="hidden" name="id" id="reviewId" value="">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="reviewName" class="form-label small">Name *</label>
                            <input type="text" name="reviewer_name" class="form-control form-control-sm" id="reviewName" required>
                        </div>
                        <div class="col-md-3">
                            <label for="reviewRating" class="form-label small">Rating *</label>
                            <select name="rating" class="form-select form-select-sm" id="reviewRating">
                                <option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="reviewStatus" class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm" id="reviewStatus">
                                <option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="reviewText" class="form-label small">Review Text</label>
                            <textarea name="review_text" class="form-control form-control-sm" id="reviewText" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="hideReviewForm()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-dark">
                            <tr><th scope="col">ID</th><th scope="col">Name</th><th scope="col">Rating</th><th scope="col">Review</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col">Actions</th></tr>
                        </thead>
                        <tbody id="reviewsTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php elseif ($detailTab === 'faq'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">FAQ</h6>
            <button class="btn btn-primary btn-sm" onclick="showFaqForm()"><i class="fa-solid fa-plus me-1"></i>Add FAQ</button>
        </div>
        <div id="faqFormWrap" class="card border-0 shadow-sm mb-3 d-none">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Add / Edit FAQ</h6>
                <form id="faqForm" onsubmit="saveFaq(event)">
                    <input type="hidden" name="id" id="faqId" value="">
                    <div class="mb-2">
                        <label for="faqQuestion" class="form-label small">Question *</label>
                        <input type="text" name="question" class="form-control form-control-sm" id="faqQuestion" required>
                    </div>
                    <div class="mb-2">
                        <label for="faqAnswer" class="form-label small">Answer *</label>
                        <textarea name="answer" class="form-control form-control-sm" id="faqAnswer" rows="3" required></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="faqSort" class="form-label small">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control form-control-sm" id="faqSort" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="faqActive" class="form-label small">Active</label>
                            <select name="is_active" class="form-select form-select-sm" id="faqActive">
                                <option value="1">Yes</option><option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="hideFaqForm()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-dark">
                            <tr><th scope="col">ID</th><th scope="col">Question</th><th scope="col">Answer</th><th scope="col">Order</th><th scope="col">Active</th><th scope="col">Actions</th></tr>
                        </thead>
                        <tbody id="faqTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php elseif ($detailTab === 'building'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Building Details</h6>
                <form id="buildingForm" onsubmit="saveBuilding(event)">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="bldgName" class="form-label small">Building Name</label>
                            <input type="text" name="building_name" class="form-control form-control-sm" id="bldgName">
                        </div>
                        <div class="col-md-4">
                            <label for="bldgYear" class="form-label small">Year Built</label>
                            <input type="text" name="year_built" class="form-control form-control-sm" id="bldgYear">
                        </div>
                        <div class="col-md-4">
                            <label for="bldgFloors" class="form-label small">Total Floors</label>
                            <input type="number" name="total_floors" class="form-control form-control-sm" id="bldgFloors">
                        </div>
                        <div class="col-md-4">
                            <label for="bldgPlate" class="form-label small">Floor Plate Area</label>
                            <input type="text" name="floor_plate_area" class="form-control form-control-sm" id="bldgPlate">
                        </div>
                        <div class="col-md-4">
                            <label for="bldgElevators" class="form-label small">Elevators</label>
                            <input type="number" name="elevators" class="form-control form-control-sm" id="bldgElevators">
                        </div>
                        <div class="col-md-4">
                            <label for="bldgParking" class="form-label small">Parking</label>
                            <input type="text" name="parking" class="form-control form-control-sm" id="bldgParking">
                        </div>
                    </div>
                    <h6 class="fw-bold mt-3 mb-2">Connectivity</h6>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label for="bldgMetro" class="form-label small">Nearest Metro</label>
                            <input type="text" name="nearest_metro" class="form-control form-control-sm" id="bldgMetro">
                        </div>
                        <div class="col-md-3">
                            <label for="bldgRailway" class="form-label small">Nearest Railway</label>
                            <input type="text" name="nearest_railway" class="form-control form-control-sm" id="bldgRailway">
                        </div>
                        <div class="col-md-3">
                            <label for="bldgAirport" class="form-label small">Airport</label>
                            <input type="text" name="airport" class="form-control form-control-sm" id="bldgAirport">
                        </div>
                        <div class="col-md-3">
                            <label for="bldgBus" class="form-label small">Bus Stop</label>
                            <input type="text" name="bus_stop" class="form-control form-control-sm" id="bldgBus">
                        </div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm">Save Building Details</button></div>
                </form>
            </div>
        </div>
        <?php elseif ($detailTab === 'leasing'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Leasing Options</h6>
            <button class="btn btn-primary btn-sm" onclick="showLeasingForm()"><i class="fa-solid fa-plus me-1"></i>Add Option</button>
        </div>
        <div id="leasingFormWrap" class="card border-0 shadow-sm mb-3 d-none">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Add / Edit Leasing Option</h6>
                <form id="leasingForm" onsubmit="saveLeasing(event)">
                    <input type="hidden" name="id" id="leasingId" value="">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="leasingTitle" class="form-label small">Title *</label>
                            <input type="text" name="option_title" class="form-control form-control-sm" id="leasingTitle" required>
                        </div>
                        <div class="col-md-3">
                            <label for="leasingPrice" class="form-label small">Price</label>
                            <input type="text" name="option_price" class="form-control form-control-sm" id="leasingPrice">
                        </div>
                        <div class="col-md-3">
                            <label for="leasingSort" class="form-label small">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control form-control-sm" id="leasingSort" value="0">
                        </div>
                        <div class="col-12">
                            <label for="leasingDesc" class="form-label small">Description</label>
                            <textarea name="option_desc" class="form-control form-control-sm" id="leasingDesc" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="leasingActive" class="form-label small">Active</label>
                            <select name="is_active" class="form-select form-select-sm" id="leasingActive">
                                <option value="1">Yes</option><option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="hideLeasingForm()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-dark">
                            <tr><th scope="col">ID</th><th scope="col">Title</th><th scope="col">Price</th><th scope="col">Order</th><th scope="col">Active</th><th scope="col">Actions</th></tr>
                        </thead>
                        <tbody id="leasingTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php elseif ($detailTab === 'extras'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Feature Highlights & SEO</h6>
                <form id="extrasForm" onsubmit="saveExtras(event)">
                    <div class="mb-2">
                        <label for="extrasHighlights" class="form-label small">Feature Highlights (one per line)</label>
                        <textarea name="feature_highlights" class="form-control form-control-sm" id="extrasHighlights" rows="4" placeholder="Fully Furnished&#10;24/7 Power Backup"></textarea>
                    </div>
                    <div class="mb-2">
                        <label for="extrasSeo" class="form-label small">SEO Text (HTML allowed)</label>
                        <textarea name="seo_text" class="form-control form-control-sm" id="extrasSeo" rows="6" placeholder="<h3>About this Workspace</h3>"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save Extras</button>
                </form>
            </div>
        </div>
        <?php elseif ($detailTab === 'connectivity'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Connectivity & Transit</h6>
                <form id="connectivityForm" onsubmit="saveConnectivity(event)">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="connMetro" class="form-label small">Nearest Metro</label>
                            <input type="text" name="nearest_metro" class="form-control form-control-sm" id="connMetro" placeholder="e.g. Anna Nagar Tower">
                        </div>
                        <div class="col-md-6">
                            <label for="connRailway" class="form-label small">Nearest Railway Station</label>
                            <input type="text" name="nearest_railway" class="form-control form-control-sm" id="connRailway" placeholder="e.g. Chennai Central">
                        </div>
                        <div class="col-md-6">
                            <label for="connAirport" class="form-label small">Airport</label>
                            <input type="text" name="airport" class="form-control form-control-sm" id="connAirport" placeholder="e.g. Chennai International Airport">
                        </div>
                        <div class="col-md-6">
                            <label for="connBus" class="form-label small">Bus Stop</label>
                            <input type="text" name="bus_stop" class="form-control form-control-sm" id="connBus" placeholder="e.g. Koyambedu Bus Terminus">
                        </div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm">Save Connectivity</button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <script>
        const DETAIL_API = '/admin/api/detail_crud.php';
        const OFFICE_ID = <?= $selectedOffice ?>;
        async function apiPost(action, data) {
            showLoading(true);
            try {
                const fd = new FormData();
                fd.append('office_id', OFFICE_ID);
                for (const [k,v] of Object.entries(data)) fd.append(k, v);
                const headers = { 'Authorization': 'Bearer ' + getToken() };
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta) headers['X-CSRF-Token'] = csrfMeta.content;
                const r = await fetch(DETAIL_API + '?action=' + action, { method: 'POST', headers: headers, body: fd });
                const result = await r.json();
                return result;
            } catch (err) {
                if (window.CubeToast) {
                    CubeToast.error(err.message || 'Network error');
                } else {
                    showAlertModal(err.message || 'Network error', 'error');
                }
                return { success: false, error: err.message };
            } finally {
                showLoading(false);
            }
        }
        async function apiGet(action) {
            showLoading(true);
            try {
                const headers = { 'Authorization': 'Bearer ' + getToken() };
                const r = await fetch(DETAIL_API + '?action=' + action + '&office_id=' + OFFICE_ID, { headers: headers });
                return await r.json();
            } catch (err) {
                if (window.CubeToast) {
                    CubeToast.error(err.message || 'Network error');
                } else {
                    showAlertModal(err.message || 'Network error', 'error');
                }
                return { error: err.message };
            } finally {
                showLoading(false);
            }
        }
        function esc(s) { return document.createElement('div').appendChild(document.createTextNode(s||'')).parentNode.innerHTML; }
        <?php if ($detailTab === 'reviews'): ?>
        async function loadReviews() {
            const d = await apiGet('list_reviews');
            const tb = document.getElementById('reviewsTableBody');
            if (!d.reviews || !d.reviews.length) { tb.innerHTML = '<tr><td colspan="7" class="text-center py-4"><i class="fa-solid fa-inbox text-muted mb-2 d-block" style="font-size: 1.5rem;"></i><span class="text-muted fw-medium">No data found</span></td></tr>'; return; }
            tb.innerHTML = d.reviews.map(r => '<tr><td class="text-muted">'+r.id+'</td><td class="fw-medium">'+esc(r.reviewer_name)+'</td><td>'+r.rating+'/5</td><td>'+esc((r.review_text||'').substring(0,80))+'</td><td><span class="badge bg-'+((r.status==='approved'?'success':r.status==='rejected'?'danger':'warning text-dark'))+'">'+r.status+'</span></td><td class="text-muted">'+r.created_at+'</td><td><a href="javascript:void(0)" onclick="editReview('+r.id+');return false;" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen-to-square"></i></a> <a href="javascript:void(0)" onclick="deleteReview('+r.id+');return false;" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></a></td></tr>').join('');
        }
        function showReviewForm() { document.getElementById('reviewFormWrap').classList.remove('d-none'); document.getElementById('reviewId').value=''; document.getElementById('reviewName').value=''; document.getElementById('reviewRating').value='5'; document.getElementById('reviewText').value=''; document.getElementById('reviewStatus').value='approved'; }
        function hideReviewForm() { document.getElementById('reviewFormWrap').classList.add('d-none'); }
        function editReview(id) { apiGet('list_reviews').then(d => { const r = d.reviews.find(x=>x.id==id); if(r){document.getElementById('reviewFormWrap').classList.remove('d-none');document.getElementById('reviewId').value=r.id;document.getElementById('reviewName').value=r.reviewer_name;document.getElementById('reviewRating').value=r.rating;document.getElementById('reviewText').value=r.review_text||'';document.getElementById('reviewStatus').value=r.status;} }); }
        async function saveReview(e) { e.preventDefault(); const fd=new FormData(document.getElementById('reviewForm')); const data={}; fd.forEach((v,k)=>data[k]=v); const action=data.id?'update_review':'create_review'; const d=await apiPost(action,data); if(d.success){loadReviews();hideReviewForm();}else{showAlertModal(d.error||'Error','error');} }
        async function deleteReview(id) { showConfirmDialog('Delete this review?', async function(){ const d=await apiPost('delete_review',{id:id}); if(d.success)loadReviews(); }); }
        loadReviews();
        <?php elseif ($detailTab === 'faq'): ?>
        async function loadFaq() {
            const d = await apiGet('list_faq');
            const tb = document.getElementById('faqTableBody');
            if (!d.faq || !d.faq.length) { tb.innerHTML = '<tr><td colspan="6" class="text-center py-4"><i class="fa-solid fa-inbox text-muted mb-2 d-block" style="font-size: 1.5rem;"></i><span class="text-muted fw-medium">No data found</span></td></tr>'; return; }
            tb.innerHTML = d.faq.map(f => '<tr><td class="text-muted">'+f.id+'</td><td>'+esc(f.question)+'</td><td>'+esc((f.answer||'').substring(0,60))+'</td><td>'+f.sort_order+'</td><td>'+(f.is_active?'<span class="badge bg-success">Yes</span>':'<span class="badge bg-secondary">No</span>')+'</td><td><a href="javascript:void(0)" onclick="editFaq('+f.id+');return false;" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen-to-square"></i></a> <a href="javascript:void(0)" onclick="deleteFaq('+f.id+');return false;" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></a></td></tr>').join('');
        }
        function showFaqForm() { document.getElementById('faqFormWrap').classList.remove('d-none'); document.getElementById('faqId').value=''; document.getElementById('faqQuestion').value=''; document.getElementById('faqAnswer').value=''; document.getElementById('faqSort').value='0'; document.getElementById('faqActive').value='1'; }
        function hideFaqForm() { document.getElementById('faqFormWrap').classList.add('d-none'); }
        function editFaq(id) { apiGet('list_faq').then(d => { const f = d.faq.find(x=>x.id==id); if(f){document.getElementById('faqFormWrap').classList.remove('d-none');document.getElementById('faqId').value=f.id;document.getElementById('faqQuestion').value=f.question;document.getElementById('faqAnswer').value=f.answer;document.getElementById('faqSort').value=f.sort_order;document.getElementById('faqActive').value=f.is_active;} }); }
        async function saveFaq(e) { e.preventDefault(); const fd=new FormData(document.getElementById('faqForm')); const data={}; fd.forEach((v,k)=>data[k]=v); const action=data.id?'update_faq':'create_faq'; const d=await apiPost(action,data); if(d.success){loadFaq();hideFaqForm();}else{showAlertModal(d.error||'Error','error');} }
        async function deleteFaq(id) { showConfirmDialog('Delete this FAQ?', async function(){ const d=await apiPost('delete_faq',{id:id}); if(d.success)loadFaq(); }); }
        loadFaq();
        <?php elseif ($detailTab === 'building'): ?>
        (async function() {
            const d = await apiGet('get_building');
            if (d.building) {
                const b = d.building;
                document.getElementById('bldgName').value = b.building_name||'';
                document.getElementById('bldgYear').value = b.year_built||'';
                document.getElementById('bldgFloors').value = b.total_floors||'';
                document.getElementById('bldgPlate').value = b.floor_plate_area||'';
                document.getElementById('bldgElevators').value = b.elevators||'';
                document.getElementById('bldgParking').value = b.parking||'';
                document.getElementById('bldgMetro').value = b.nearest_metro||'';
                document.getElementById('bldgRailway').value = b.nearest_railway||'';
                document.getElementById('bldgAirport').value = b.airport||'';
                document.getElementById('bldgBus').value = b.bus_stop||'';
            }
        })();
        async function saveBuilding(e) { e.preventDefault(); const fd=new FormData(document.getElementById('buildingForm')); const data={}; fd.forEach((v,k)=>data[k]=v); const d=await apiPost('save_building',data); if(d.success){showAlertModal('Saved successfully!','success');}else{showAlertModal(d.error||'Error','error');} }
        <?php elseif ($detailTab === 'leasing'): ?>
        async function loadLeasing() {
            const d = await apiGet('list_leasing');
            const tb = document.getElementById('leasingTableBody');
            if (!d.leasing || !d.leasing.length) { tb.innerHTML = '<tr><td colspan="6" class="text-center py-4"><i class="fa-solid fa-inbox text-muted mb-2 d-block" style="font-size: 1.5rem;"></i><span class="text-muted fw-medium">No data found</span></td></tr>'; return; }
            tb.innerHTML = d.leasing.map(l => '<tr><td class="text-muted">'+l.id+'</td><td>'+esc(l.option_title)+'</td><td>'+esc(l.option_price||'—')+'</td><td>'+l.sort_order+'</td><td>'+(l.is_active?'<span class="badge bg-success">Yes</span>':'<span class="badge bg-secondary">No</span>')+'</td><td><a href="javascript:void(0)" onclick="editLeasing('+l.id+');return false;" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen-to-square"></i></a> <a href="javascript:void(0)" onclick="deleteLeasing('+l.id+');return false;" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></a></td></tr>').join('');
        }
        function showLeasingForm() { document.getElementById('leasingFormWrap').classList.remove('d-none'); document.getElementById('leasingId').value=''; document.getElementById('leasingTitle').value=''; document.getElementById('leasingPrice').value=''; document.getElementById('leasingDesc').value=''; document.getElementById('leasingSort').value='0'; document.getElementById('leasingActive').value='1'; }
        function hideLeasingForm() { document.getElementById('leasingFormWrap').classList.add('d-none'); }
        function editLeasing(id) { apiGet('list_leasing').then(d => { const l = d.leasing.find(x=>x.id==id); if(l){document.getElementById('leasingFormWrap').classList.remove('d-none');document.getElementById('leasingId').value=l.id;document.getElementById('leasingTitle').value=l.option_title;document.getElementById('leasingPrice').value=l.option_price||'';document.getElementById('leasingDesc').value=l.option_desc||'';document.getElementById('leasingSort').value=l.sort_order;document.getElementById('leasingActive').value=l.is_active;} }); }
        async function saveLeasing(e) { e.preventDefault(); const fd=new FormData(document.getElementById('leasingForm')); const data={}; fd.forEach((v,k)=>data[k]=v); const action=data.id?'update_leasing':'create_leasing'; const d=await apiPost(action,data); if(d.success){loadLeasing();hideLeasingForm();}else{showAlertModal(d.error||'Error','error');} }
        async function deleteLeasing(id) { showConfirmDialog('Delete this option?', async function(){ const d=await apiPost('delete_leasing',{id:id}); if(d.success)loadLeasing(); }); }
        loadLeasing();
        <?php elseif ($detailTab === 'extras'): ?>
        (async function() {
            const d = await apiGet('get_building');
            if (d.extras) {
                document.getElementById('extrasHighlights').value = (Array.isArray(d.extras.feature_highlights) ? d.extras.feature_highlights.join('\n') : '');
                document.getElementById('extrasSeo').value = d.extras.seo_text || '';
            }
        })();
        async function saveExtras(e) {
            e.preventDefault();
            const highlights = document.getElementById('extrasHighlights').value.split('\n').map(s=>s.trim()).filter(Boolean);
            const seoText = document.getElementById('extrasSeo').value;
            const data = { feature_highlights: JSON.stringify(highlights), seo_text: seoText };
            const d = await apiPost('update_extras', data);
            if (d.success) { showAlertModal('Saved successfully!', 'success'); } else { showAlertModal(d.error || 'Error', 'error'); }
        }
        <?php elseif ($detailTab === 'connectivity'): ?>
        (async function() {
            const d = await apiGet('get_connectivity');
            if (d.connectivity) {
                const c = d.connectivity;
                document.getElementById('connMetro').value = c.nearest_metro||'';
                document.getElementById('connRailway').value = c.nearest_railway||'';
                document.getElementById('connAirport').value = c.airport||'';
                document.getElementById('connBus').value = c.bus_stop||'';
            }
        })();
        async function saveConnectivity(e) {
            e.preventDefault();
            const fd = new FormData(document.getElementById('connectivityForm'));
            const data = {};
            fd.forEach((v,k) => data[k] = v);
            const d = await apiPost('save_connectivity', data);
            if (d.success) { showAlertModal('Saved successfully!', 'success'); } else { showAlertModal(d.error || 'Error', 'error'); }
        }
        <?php endif; ?>
        </script>
        <?php endif; ?>
        </main>
    </div>
    <?php endif; ?>

<div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100">
    <div class="d-flex flex-column align-items-center gap-2">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span class="text-muted small fw-semibold">Loading...</span>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-label="Confirm deletion" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fa-solid fa-triangle-exclamation text-warning mb-3" style="font-size: 2rem;"></i>
                <p class="mb-0 fw-medium" id="confirmMessage">Are you sure?</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm px-3" id="confirmYesBtn">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fa-solid fa-circle-check text-success mb-3 d-none" id="alertIconSuccess" style="font-size: 2rem;"></i>
                <i class="fa-solid fa-circle-exclamation text-danger mb-3 d-none" id="alertIconError" style="font-size: 2rem;"></i>
                <i class="fa-solid fa-circle-info text-primary mb-3 d-none" id="alertIconInfo" style="font-size: 2rem;"></i>
                <p class="mb-0 fw-medium" id="alertMessage">Message</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button type="button" class="btn btn-primary btn-sm px-3" data-bs-dismiss="modal" id="alertOkBtn">OK</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/api-client.js"></script>
<script src="../assets/js/toast.js"></script>
<script src="../assets/js/realtime.js"></script>
<script>
(function syncToken() {
    var metaToken = (document.querySelector('meta[name="access-token"]') || {}).content || '';
    if (metaToken) sessionStorage.setItem('admin_access_token', metaToken);
})();
function getToken() {
    var meta = document.querySelector('meta[name="access-token"]');
    return (meta && meta.content) || sessionStorage.getItem('admin_access_token') || '';
}
function showLoading(show) {
    var el = document.getElementById('loadingOverlay');
    if (el) {
        if (show) el.classList.remove('d-none');
        else el.classList.add('d-none');
    }
}
function showConfirmDialog(message, callback) {
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmYesBtn').onclick = function() {
        var modalEl = document.getElementById('confirmModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        callback();
    };
    var modalEl = document.getElementById('confirmModal');
    var modal = new bootstrap.Modal(modalEl);
    modalEl.addEventListener('hidden.bs.modal', function handler() {
        modalEl.removeEventListener('hidden.bs.modal', handler);
        document.getElementById('confirmYesBtn').onclick = null;
    });
    modal.show();
}
function showAlertModal(message, type) {
    type = type || 'info';
    document.getElementById('alertMessage').textContent = message;
    document.getElementById('alertIconSuccess').classList.add('d-none');
    document.getElementById('alertIconError').classList.add('d-none');
    document.getElementById('alertIconInfo').classList.add('d-none');
    document.getElementById('alertIcon' + type.charAt(0).toUpperCase() + type.slice(1)).classList.remove('d-none');
    var modalEl = document.getElementById('alertModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}
function confirmDelete(id, type, title) {
    showConfirmDialog('Delete "' + title + '" (ID: ' + id + ')? This cannot be undone.', function() {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('listing_type', type);
        const headers = {};
        const token = getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
        fetch('/admin/api/listing_crud.php?action=delete', { method: 'POST', headers: headers, body: fd })
            .then(r => r.json()).then(d => { if (d.success) window.location.reload(); else showAlertModal(d.error, 'error'); });
    });
}
function confirmDeleteContact(id) {
    showConfirmDialog('Delete this contact submission?', function() {
        const fd = new FormData(); fd.append('id', id);
        const headers = {};
        const token = getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
        fetch('/admin/api/contact_crud.php?action=delete', { method: 'POST', headers: headers, body: fd })
            .then(r => r.json()).then(d => { if (d.success) window.location.href = '/admin/?page=contacts&deleted=1'; else showAlertModal(d.error, 'error'); });
    });
}
function toggleAllCheckboxes(master) {
    document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = master.checked);
}
document.querySelectorAll('[id^="officeSpaceType"]').forEach(sel => {
    sel.addEventListener('change', function() {
        const form = this.closest('form');
        if (!form) return;
        const priceInput = form.querySelector('input[name="price"]');
        const labelInput = form.querySelector('input[name="price_label"]');
        const isLease = this.value === 'lease';
        priceInput.placeholder = isLease ? 'e.g. 1500000 (per year)' : 'e.g. 150000 (per month)';
        if (priceInput.value && !labelInput.value) {
            const num = parseFloat(priceInput.value);
            if (num) {
                if (isLease) {
                    labelInput.value = '₹' + (num >= 100000 ? (num / 100000).toFixed(1) + ' Lakhs/yr' : num.toLocaleString() + '/yr');
                } else {
                    labelInput.value = '₹' + (num >= 100000 ? (num / 100000).toFixed(1) + ' Lakhs/mo' : num.toLocaleString() + '/mo');
                }
            }
        }
    });
    sel.dispatchEvent(new Event('change'));
});
// Cross-tab token migration (legacy)
var lsToken = localStorage.getItem('admin_access_token');
if (lsToken && !getToken()) { sessionStorage.setItem('admin_access_token', lsToken); }
localStorage.removeItem('admin_access_token');
document.querySelectorAll('#listingForm').forEach(function(form) {
    form.addEventListener('submit', handleListingForm);
});
function handleListingForm(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const result = document.getElementById('formResult');
    if (!result) return;
    const fd = new FormData(form);
    const isEdit = parseInt(fd.get('id')) > 0;
    const action = isEdit ? 'update' : 'create';
    const title = fd.get('title');
    if (!title || !title.trim()) {
        result.className = 'alert alert-danger mt-2'; result.textContent = 'Title is required'; result.classList.remove('d-none');
        return;
    }
    const price = fd.get('price');
    const officeType = fd.get('office_space_type');
    if (price && parseFloat(price) <= 0) {
        result.className = 'alert alert-danger mt-2'; result.textContent = 'Price must be greater than 0'; result.classList.remove('d-none');
        return;
    }
    function proceedSubmit() {
        btn.disabled = true;
        btn.textContent = isEdit ? 'Updating...' : 'Creating...';
        result.className = 'alert d-none mt-2';
        (async function() {
        try {
            let headers = { 'Authorization': 'Bearer ' + getToken(), 'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' };
            let r = await fetch('/admin/api/listing_crud.php?action=' + action, { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
            if (r.status === 401) {
                const refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
                if (refreshRes.ok) {
                    const refreshData = await refreshRes.json();
                    if (refreshData.access_token) {
                        sessionStorage.setItem('admin_access_token', refreshData.access_token);
                        headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                        r = await fetch('/admin/api/listing_crud.php?action=' + action, { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                    }
                } 
            }
            if (r.status === 401) { window.location.reload(); return; }
            const d = await r.json();
            result.classList.remove('d-none');
            if (d.success) {
                result.className = 'alert alert-success mt-2';
                result.textContent = d.message;
                setTimeout(function() {
                    const page = new URLSearchParams(window.location.search).get('page') || 'managed-office';
                    window.location.href = window.location.pathname.replace(/\/index\.php$/, '') + '?page=' + page + '&saved=1';
                }, 1000);
            } else {
                result.className = 'alert alert-danger mt-2';
                result.textContent = d.error || 'Operation failed';
            }
        } catch (err) {
            result.classList.remove('d-none');
            result.className = 'alert alert-danger mt-2';
            result.textContent = err.message || 'Network error';
        }
        btn.disabled = false;
        btn.textContent = isEdit ? 'Update Listing' : 'Create Listing';
    })();
}
    if (!price && officeType === 'lease') {
        showConfirmDialog('No price set for Lease. The listing will show without a price. Continue?', proceedSubmit);
    } else {
        proceedSubmit();
    }
}

// Submit handlers for Contact Detail Form
const contactDetailForm = document.getElementById('contactDetailForm');
if (contactDetailForm) {
    contactDetailForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const result = document.getElementById('contactFormResult');
        if (!result) return;
        const fd = new FormData(this);
        btn.disabled = true; btn.textContent = 'Saving...';
        result.className = 'alert d-none mt-2';
        try {
            let headers = {
                'Authorization': 'Bearer ' + getToken(),
                'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
            };
            let r = await fetch('/admin/api/contact_crud.php?action=update', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
            if (r.status === 401) {
                const refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
                if (refreshRes.ok) {
                    const refreshData = await refreshRes.json();
                    if (refreshData.access_token) {
                        sessionStorage.setItem('admin_access_token', refreshData.access_token);
                        headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                        r = await fetch('/admin/api/contact_crud.php?action=update', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                    }
                }
            }
            if (r.status === 401) { window.location.reload(); return; }
            const d = await r.json();
            result.classList.remove('d-none');
            if (d.success) {
                result.className = 'alert alert-success mt-2';
                result.textContent = d.message || 'Updated successfully';
                setTimeout(function() {
                    window.location.href = '/admin/?page=contacts&saved=1';
                }, 1000);
            } else {
                result.className = 'alert alert-danger mt-2';
                result.textContent = d.error || 'Operation failed';
            }
        } catch (err) {
            result.classList.remove('d-none');
            result.className = 'alert alert-danger mt-2';
            result.textContent = err.message || 'Network error';
        }
        btn.disabled = false; btn.textContent = 'Save Changes';
    });
}

// Bulk Actions Handler
async function applyBulkAction() {
    const bulkBar = document.querySelector('.bulk-bar:not(.d-none)') || document.querySelector('.bulk-bar');
    if (!bulkBar) return;
    const actionVal = bulkBar.querySelector('select').value;
    if (!actionVal) return;
    const checkedBoxes = document.querySelectorAll('.bulk-checkbox:checked');
    if (checkedBoxes.length === 0) {
        showAlertModal('Please select at least one record.', 'info');
        return;
    }
    const ids = Array.from(checkedBoxes).map(cb => parseInt(cb.value));
    const types = Array.from(checkedBoxes).map(cb => cb.dataset.type || '');
    const page = new URLSearchParams(window.location.search).get('page') || 'dashboard';
    let url = '/admin/api/bulk_crud.php';
    let fd = new FormData();
    fd.append('page', page);
    ids.forEach((id, i) => {
        fd.append('ids[]', id);
        if (types[i]) fd.append('types[]', types[i]);
    });
    let apiAction = '';
    if (actionVal === 'delete') {
        apiAction = 'bulk_delete';
    } else if (actionVal.startsWith('status-')) {
        apiAction = 'bulk_status';
        fd.append('status', actionVal.replace('status-', ''));
    } else if (actionVal.startsWith('featured-')) {
        apiAction = 'bulk_featured';
        fd.append('featured', actionVal.replace('featured-', ''));
    }
    const doAction = async function() {
        try {
            let headers = {
                'Authorization': 'Bearer ' + getToken(),
                'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
            };
            const r = await fetch(url + '?action=' + apiAction, { method: 'POST', headers: headers, body: fd });
            const d = await r.json();
            if (d.success) {
                showAlertModal(d.message || 'Bulk operation completed.', 'success');
                window.location.reload();
            } else {
                showAlertModal(d.error || 'Bulk operation failed.', 'error');
            }
        } catch (err) {
            showAlertModal('Error: ' + err.message, 'error');
        }
    };
    if (apiAction === 'bulk_delete') {
        showConfirmDialog('Are you sure you want to delete ' + checkedBoxes.length + ' selected item(s)?', doAction);
    } else {
        doAction();
    }
}

// Initialize Realtime updates in admin panel
if (getToken()) {
    CubeRealtime.init({ adminMode: true, interval: 30000 });
    CubeRealtime.on('*', function(eventType, eventData) {
        if (eventType === 'contact_created' || eventType === 'partner_created') {
            if (window.CubeToast) {
                CubeToast.info('New ' + eventType.replace('_', ' ') + ': ' + (eventData.summary || ''));
            }
        }
    });
}
</script>
</body>
</html>