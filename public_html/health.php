<?php
header('Content-Type: application/json');

require_once __DIR__ . '/api/db_config.php';

// Simple health check
$dbOk = false;
if ($conn) {
    $result = @mysqli_query($conn, "SELECT 1");
    $dbOk = $result !== false;
}

http_response_code($dbOk ? 200 : 503);
echo json_encode([
    'status' => $dbOk ? 'healthy' : 'unhealthy',
    'database' => $dbOk ? 'connected' : 'disconnected',
    'timestamp' => time()
]);
