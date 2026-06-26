<?php
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/init.php';

$username = trim($_POST['username'] ?? '');

if (!$username) {
    echo json_encode(['success' => false, 'error' => 'Username is required']);
    exit;
}

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not available');
    }
    
    $stmt = mysqli_prepare($conn, "SELECT id FROM admins WHERE username = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $admin = mysqli_fetch_assoc($result);
    
    if (!$admin) {
        echo json_encode(['success' => false, 'error' => 'Username not found']);
        exit;
    }
    
    // Generate reset token
    $resetToken = bin2hex(random_bytes(16));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = mysqli_prepare($conn, "UPDATE admins SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'ssi', $resetToken, $expiry, $admin['id']);
    mysqli_stmt_execute($stmt);
    
    echo json_encode(['success' => true, 'reset_token' => $resetToken]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
