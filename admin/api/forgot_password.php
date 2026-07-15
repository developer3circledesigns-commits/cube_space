<?php

header('Content-Type: application/json');

require_once __DIR__ . '/init.php';
cubespace_require_project('src/autoload.php');

$username = trim($_POST['username'] ?? '');

if (!$username) {
    echo json_encode(['success' => false, 'error' => 'Username is required']);
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
    
    $stmt = mysqli_prepare($conn, "SELECT id, email FROM admins WHERE username = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $admin = mysqli_fetch_assoc($result);
    
    if (!$admin) {
        echo json_encode(['success' => true, 'message' => 'If the username exists, a reset link has been sent to the registered email.']);
        exit;
    }
    
    // Generate reset token
    $resetToken = bin2hex(random_bytes(16));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = mysqli_prepare($conn, "UPDATE admins SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }
    mysqli_stmt_bind_param($stmt, 'ssi', $resetToken, $expiry, $admin['id']);
    mysqli_stmt_execute($stmt);
    
    if (!empty($admin['email'])) {
        $mail = new \CubeSpace\EmailService();
        $mail->send($admin['email'], 'Password Reset', 'Your reset token: ' . $resetToken . ' (expires in 1 hour)');
    }
    echo json_encode(['success' => true, 'reset_token' => $resetToken, 'message' => 'Reset token generated. It expires in 1 hour.']);
} catch (Exception $e) {
    error_log('forgot_password.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
