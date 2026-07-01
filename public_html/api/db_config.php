<?php
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

if ($host === false || $user === false || $pass === false || $db === false) {
    $configCandidates = [
        __DIR__ . '/../config/database.php',
        __DIR__ . '/../../config/database.php',
    ];
    foreach ($configCandidates as $configFile) {
        if (file_exists($configFile)) {
            require_once $configFile;
            $host = DB_HOST;
            $user = DB_USER;
            $pass = DB_PASS;
            $db   = DB_NAME;
            break;
        }
    }
}

if (!function_exists('cubespace_require_project')) {
function cubespace_require_project(string $relative): void {
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    foreach ([__DIR__ . '/../' . $relative, __DIR__ . '/../../' . $relative] as $path) {
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

if ($host === false || $user === false || $pass === false || $db === false) {
    error_log('CubeSpace: Missing database configuration');
    http_response_code(500);
    die(json_encode(['error' => 'Server configuration error']));
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($host, $user, $pass, $db, 3306);

if (!$conn) {
    error_log('CubeSpace: Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    die(json_encode(['error' => 'Service unavailable']));
}

mysqli_set_charset($conn, 'utf8mb4');
