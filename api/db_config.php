<?php
require_once __DIR__ . '/../lib/dotenv.php';
load_env();

$host = getenv('DB_HOST') ?: ($_SERVER['DB_HOST'] ?? null);
$user = getenv('DB_USER') ?: ($_SERVER['DB_USER'] ?? null);
$pass = getenv('DB_PASS') ?: ($_SERVER['DB_PASS'] ?? null);
$db   = getenv('DB_NAME') ?: ($_SERVER['DB_NAME'] ?? null);
$port = getenv('DB_PORT') ?: ($_SERVER['DB_PORT'] ?? null);

if (empty($host) || empty($user) || $pass === null || empty($db)) {
    $configCandidates = [
        __DIR__ . '/../config/database.php',
    ];
    foreach ($configCandidates as $configFile) {
        if (file_exists($configFile)) {
            require_once $configFile;
            $host = DB_HOST;
            $user = DB_USER;
            $pass = DB_PASS;
            $db   = DB_NAME;
            $port = DB_PORT;
            break;
        }
    }
}

if (empty($host) || $host === 'db') {
    $host = file_exists('/.dockerenv') ? 'mysql' : 'localhost';
}

if (empty($host) || empty($user) || $pass === null || empty($db)) {
    error_log('CubeSpace: Missing database configuration');
    http_response_code(500);
    die(json_encode(['error' => 'Server configuration error']));
}

if (empty($port)) {
    $port = 3306;
}

if (!function_exists('cubespace_require_project')) {
function cubespace_require_project(string $relative): void {
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    foreach ([__DIR__ . '/../' . $relative] as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
    error_log('CubeSpace: missing project file: ' . $relative);
    http_response_code(500);
    die(json_encode(['error' => 'Server configuration error']));
}
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($host, $user, $pass, '', (int) $port);

if (!$conn) {
    error_log('CubeSpace: Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    die(json_encode(['error' => 'Service unavailable']));
}

if (!mysqli_select_db($conn, $db)) {
    $createDatabaseSql = 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db) . '`';
    if (!mysqli_query($conn, $createDatabaseSql) || !mysqli_select_db($conn, $db)) {
        error_log('CubeSpace: Database not available: ' . mysqli_error($conn));
        http_response_code(500);
        die(json_encode(['error' => 'Service unavailable']));
    }
}

mysqli_set_charset($conn, 'utf8mb4');
