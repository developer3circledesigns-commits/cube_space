<?php
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../api/db_config.php';

$token = trim($_POST['token'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$token || !$password) {
    echo json_encode(['success' => false, 'error' => 'Token and password are required']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
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
