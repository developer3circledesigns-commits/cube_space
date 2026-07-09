<?php
$host = getenv('DB_HOST') ?: ($_SERVER['DB_HOST'] ?? null);
$user = getenv('DB_USER') ?: ($_SERVER['DB_USER'] ?? null);
$pass = getenv('DB_PASS') ?: ($_SERVER['DB_PASS'] ?? null);
$db   = getenv('DB_NAME') ?: ($_SERVER['DB_NAME'] ?? null);
$port = getenv('DB_PORT') ?: ($_SERVER['DB_PORT'] ?? null);

if (empty($host)) {
    $host = file_exists('/.dockerenv') ? 'mysql' : '127.0.0.1';
}
if (empty($user)) {
    $user = 'root';
}
if ($pass === null || $pass === '') {
    $pass = '';
}
if (empty($db)) {
    $db = 'u814177917_cubespace';
}
if (empty($port)) {
    $port = 3306;
}

define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASS', $pass);
define('DB_NAME', $db);
define('DB_PORT', (int) $port);
