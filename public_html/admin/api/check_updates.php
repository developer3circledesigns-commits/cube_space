<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../jwt_helper.php';
admin_require_lib('csrf.php');

header('Content-Type: application/json');

require_jwt_auth();

$lastCheck = (int)($_GET['since'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT UNIX_TIMESTAMP(MAX(created_at)) as latest FROM activity_log");
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$latest = (int)($row['latest'] ?? 0);

echo json_encode([
    'changed' => $latest > $lastCheck,
    'timestamp' => $latest
]);
