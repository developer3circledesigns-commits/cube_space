<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test database connection
$host = getenv('DB_HOST') ?: $_SERVER['DB_HOST'] ?? 'db';
$user = getenv('DB_USER') ?: $_SERVER['DB_USER'] ?? 'cubespace';
$pass = getenv('DB_PASS') ?: $_SERVER['DB_PASS'] ?? 'cubespace_password';
$db   = getenv('DB_NAME') ?: $_SERVER['DB_NAME'] ?? 'u814177917_cubespace';
$port = getenv('DB_PORT') ?: $_SERVER['DB_PORT'] ?? 3306;

echo "Attempting connection to $host:$port\n";
echo "User: $user\n";
echo "Database: $db\n\n";

$conn = mysqli_connect($host, $user, $pass, '', (int) $port);

if (!$conn) {
    echo 'Connection failed: ' . mysqli_connect_error() . "\n";
    exit(1);
}

echo "Connection successful!\n";

if (!mysqli_select_db($conn, $db)) {
    echo 'Database not found: ' . mysqli_error($conn) . "\n";
    $createDatabaseSql = 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db) . '`';
    if (mysqli_query($conn, $createDatabaseSql) && mysqli_select_db($conn, $db)) {
        echo "Database created and selected\n";
    } else {
        echo 'Failed to create database: ' . mysqli_error($conn) . "\n";
        exit(1);
    }
} else {
    echo "Database selected successfully\n";
}

mysqli_set_charset($conn, 'utf8mb4');

// Test query
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "Tables in database: " . $row['cnt'] . "\n";
} else {
    echo "Query failed: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
echo "\nConnection test completed successfully!";
?>
