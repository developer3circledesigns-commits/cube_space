<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/init.php';

admin_load_api_db();

function log_activity($conn, $action, $table, $recordId, $details = null): void {
    $uid = (int)($_SESSION['admin_id'] ?? 0);
    $uname = $_SESSION['admin_user'] ?? 'system';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $det = $details ? json_encode($details) : null;
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_log (admin_id, admin_username, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 'isssiss', $uid, $uname, $action, $table, $recordId, $det, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
