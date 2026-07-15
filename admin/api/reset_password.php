<?php

header('Content-Type: application/json');

require_once __DIR__ . '/init.php';

$token = trim($_POST['token'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$token || !$password) {
    echo json_encode(['success' => false, 'error' => 'Token and password are required']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
    exit;
}

$r = @mysqli_query($conn, "SHOW COLUMNS FROM admins LIKE 'reset_token'");
if (!$r || mysqli_num_rows($r) == 0) {
    @mysqli_query($conn, "ALTER TABLE admins ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL AFTER email");
    @mysqli_query($conn, "ALTER TABLE admins ADD COLUMN reset_token_expiry DATETIME DEFAULT NULL AFTER reset_token");
    @mysqli_query($conn, "CREATE INDEX idx_reset_token ON admins (reset_token)");
}

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not available');
    }
    
    $stmt = mysqli_prepare($conn, "SELECT id FROM admins WHERE reset_token = ? AND reset_token_expiry > NOW()");
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $admin = mysqli_fetch_assoc($result);
    
    if (!$admin) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
        exit;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare($conn, "UPDATE admins SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'si', $hashedPassword, $admin['id']);
    mysqli_stmt_execute($stmt);
    
    echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
