<?php
require_once __DIR__ . '/init.php';
admin_load_db();
require_once __DIR__ . '/jwt_helper.php';
admin_require_lib('ratelimit.php');

header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$username || !$password) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Username and password are required']));
}

// Rate limiting: max 5 attempts per IP in 5 minutes
$submittedIp = $_SERVER['REMOTE_ADDR'] ?? '';
$limiter = new RateLimiter(5, 300, 'login_');
if (!$limiter->check($submittedIp)) {
    http_response_code(429);
    die(json_encode(['success' => false, 'error' => 'Too many failed attempts. Please try again later.']));
}

$stmt = mysqli_prepare($conn, "SELECT id, username, password, role, is_active FROM admins WHERE username = ?");
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin  = mysqli_fetch_assoc($result);

if (!$admin || !password_verify($password, $admin['password'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Invalid credentials']));
}

if (!$admin['is_active']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Account is deactivated']));
}

$accessToken  = generate_access_token($admin['id'], $admin['username']);
$refreshToken = generate_refresh_token($admin['id'], $admin['username']);

set_auth_cookies($accessToken, $refreshToken);

$stmt2 = mysqli_prepare($conn, "UPDATE admins SET last_login = NOW() WHERE id = ?");
mysqli_stmt_bind_param($stmt2, 'i', $admin['id']);
mysqli_stmt_execute($stmt2);
mysqli_stmt_close($stmt2);

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'access_token' => $accessToken
]);
