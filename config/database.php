<?php
$host = getenv('DB_HOST') ?: ($_SERVER['DB_HOST'] ?? null);
$user = getenv('DB_USER') ?: ($_SERVER['DB_USER'] ?? null);
$pass = getenv('DB_PASS') ?: ($_SERVER['DB_PASS'] ?? null);
$db   = getenv('DB_NAME') ?: ($_SERVER['DB_NAME'] ?? null);
$port = getenv('DB_PORT') ?: ($_SERVER['DB_PORT'] ?? null);

if (empty($host)) {
    $host = file_exists('/.dockerenv') ? 'mysql' : 'localhost';
}

if (empty($user)) {
    $user = 'u814177917_cubespace';
}

if ($pass === null || $pass === '') {
    $pass = 'cubespace@123C';
}

if (empty($db)) {
    $db = 'u814177917_cubespace';
}

if (empty($port)) {
    $port = 3306;
}

putenv("DB_HOST=$host");
putenv("DB_USER=$user");
putenv("DB_PASS=$pass");
putenv("DB_NAME=$db");
putenv("DB_PORT=$port");

// For web server environments, set $_SERVER as well
if (!isset($_SERVER['DB_HOST'])) {
    $_SERVER['DB_HOST'] = $host;
}
if (!isset($_SERVER['DB_USER'])) {
    $_SERVER['DB_USER'] = $user;
}
if (!isset($_SERVER['DB_PASS'])) {
    $_SERVER['DB_PASS'] = $pass;
}
if (!isset($_SERVER['DB_NAME'])) {
    $_SERVER['DB_NAME'] = $db;
}
if (!isset($_SERVER['DB_PORT'])) {
    $_SERVER['DB_PORT'] = $port;
}

// Validation for debugging
if (empty($host) || empty($user) || empty($pass) || empty($db)) {
    error_log('ERROR: Database configuration is missing or incomplete');
    error_log('Host: ' . ($host ?: 'EMPTY'));
    error_log('User: ' . ($user ?: 'EMPTY'));
    error_log('DB: ' . ($db ?: 'EMPTY'));
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Server configuration error: Database configuration missing']));
}

define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASS', $pass);
define('DB_NAME', $db);
define('DB_PORT', (int) $port);

error_log('Successfully loaded database configuration:');
error_log('  Host: ' . DB_HOST);
error_log('  User: ' . DB_USER);
error_log('  Database: ' . DB_NAME);
