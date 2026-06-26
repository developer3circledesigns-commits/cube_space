<?php
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../jwt_helper.php';
require_once __DIR__ . '/../../api/db_config.php';

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
