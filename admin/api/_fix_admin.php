<?php
require_once __DIR__ . '/../../config/database.php';
$conn = new mysqli('127.0.0.1', 'root', 'root_password', 'cubespace', 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$hash = password_hash('admin123', PASSWORD_BCRYPT);
echo "Hash: $hash\n";
$stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
$stmt->bind_param('s', $hash);
$stmt->execute();
echo "Updated: " . $stmt->affected_rows . " rows\n";
$stmt->close();
$conn->close();
