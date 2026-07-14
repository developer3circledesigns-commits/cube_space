<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/jwt_helper.php';
admin_require_lib('csrf.php');

$adminUser = '';
$loggedIn = false;
$token = $_COOKIE['access_token'] ?? '';
if ($token) {
    $payload = jwt_decode($token);
    if ($payload && ($payload['type'] ?? '') === 'access') {
        $loggedIn = true;
        $adminUser = $payload['user'];
    }
}
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
        } else {
            clear_auth_cookies();
        }
    }
}
if ($loggedIn) {
    header('Location: dashboard.php');
    exit;
}
$csrfTokenLogin = CSRFManager::generateToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login - CubeSpace</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfTokenLogin) ?>">
    <meta name="access-token" content="">
    <?php include dirname(__DIR__) . '/includes/head-meta.php'; ?>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
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
                    <div class="position-relative">
                        <input type="password" id="password" name="password" class="form-control pe-5" required placeholder="Enter password">
                        <button type="button" id="togglePassword" class="btn position-absolute top-50 end-0 translate-middle-y border-0" style="cursor:pointer;background:transparent;padding:8px 12px;z-index:5;" tabindex="-1">
                            <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
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
            window.location.href = 'dashboard.php';
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
document.getElementById('togglePassword').addEventListener('click', function() {
    const pw = document.getElementById('password');
    const icon = document.getElementById('togglePasswordIcon');
    if (!pw || !icon) return;
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        pw.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
});
</script>
</body>
</html>
