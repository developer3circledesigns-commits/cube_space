<?php

header('Content-Type: application/json');

require_once __DIR__ . '/init.php';
cubespace_require_project('src/autoload.php');

$username = trim($_POST['username'] ?? '');

if (!$username) {
    echo json_encode(['success' => false, 'error' => 'Username is required']);
    exit;
}

$rateLimitKey = 'forgot_pwd_' . $_SERVER['REMOTE_ADDR'];
$tempDir = sys_get_temp_dir();
// Force Windows temp directory on Windows systems
if (DIRECTORY_SEPARATOR === '\\') {
    $tempDir = getenv('TEMP') ?: getenv('TMP') ?: 'C:\\Windows\\Temp';
}
// Ensure temp directory exists and is writable
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0777, true);
}
$rateLimitFile = $tempDir . DIRECTORY_SEPARATOR . md5($rateLimitKey);
$rateLimitPeriod = 300;
$rateLimitMax = 5;
$now = time();
if (file_exists($rateLimitFile)) {
    $data = json_decode(file_get_contents($rateLimitFile), true);
    if ($data && is_array($data)) {
        $attempts = $data['attempts'] ?? 0;
        $firstAttempt = $data['first_attempt'] ?? $now;
        if ($now - $firstAttempt > $rateLimitPeriod) {
            $attempts = 0;
            $firstAttempt = $now;
        }
        if ($attempts >= $rateLimitMax) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Too many requests. Please try again later.']);
            exit;
        }
    }
}
file_put_contents($rateLimitFile, json_encode([
    'attempts' => ($data['attempts'] ?? 0) + 1,
    'first_attempt' => $data['first_attempt'] ?? $now,
]), LOCK_EX);

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
        $sent = $mail->send($admin['email'], 'Password Reset', 'Your reset token: ' . $resetToken . ' (expires in 1 hour)');
        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'If the username exists, a reset link has been sent to the registered email.']);
        } else {
            echo json_encode(['success' => true, 'reset_token' => $resetToken, 'warning' => 'Email not configured']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'If the username exists, a reset link has been sent to the registered email.']);
    }
} catch (Exception $e) {
    log_app_error('forgot_password.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again later.']);
}
