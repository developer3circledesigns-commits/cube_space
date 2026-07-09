<?php
require_once __DIR__ . '/init.php';
admin_load_db();
require_once __DIR__ . '/jwt_helper.php';

header('Content-Type: application/json');

$refreshToken = $_COOKIE['refresh_token'] ?? '';

if (!$refreshToken) {
    http_response_code(401);
    die(json_encode(['error' => 'No refresh token']));
}

$payload = jwt_decode($refreshToken);
if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
    clear_auth_cookies();
    http_response_code(401);
    die(json_encode(['error' => 'Invalid refresh token']));
}

// Verify admin still exists and is active
$stmt = mysqli_prepare($conn, "SELECT id, is_active FROM admins WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $payload['sub']);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$admin || !$admin['is_active']) {
    clear_auth_cookies();
    http_response_code(401);
    die(json_encode(['error' => 'Account not found or deactivated']));
}

$newAccess = generate_access_token($payload['sub'], $payload['user']);
$newRefresh = generate_refresh_token($payload['sub'], $payload['user']);

set_auth_cookies($newAccess, $newRefresh);

echo json_encode([
    'success' => true,
    'access_token' => $newAccess
]);
