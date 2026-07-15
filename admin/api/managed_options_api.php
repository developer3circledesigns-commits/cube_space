<?php
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
header('Content-Type: application/json');
try {
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../jwt_helper.php';
admin_require_lib('csrf.php');
admin_require_lib('config.php');

$jwtPayload = require_jwt_auth();
secure_session_start();
$_SESSION['admin_id'] = $jwtPayload['sub'];
$_SESSION['admin_user'] = $jwtPayload['user'];
cubespace_require_project('src/autoload.php');

$action = $_GET['action'] ?? '';
$type = $_POST['type'] ?? ''; // 'city' or 'area'

$allowedTypes = ['city', 'area'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid type']));
}

$table = $type === 'city' ? 'listing_cities' : 'listing_areas';
$valueField = $type;
$sourceTable = $type === 'city' ? 'managed_offices' : 'managed_offices';
$sourceColumn = $type;

if ($action === 'add') {
    CSRFManager::require();
    $value = trim($_POST['value'] ?? '');
    if (!$value) {
        http_response_code(400);
        die(json_encode(['error' => 'Value is required']));
    }
    $value = mb_strtolower($value);
    $city = $type === 'area' ? mb_strtolower(trim($_POST['city'] ?? '')) : '';
    if ($type === 'area' && !$city) {
        http_response_code(400);
        die(json_encode(['error' => 'City is required when adding an area']));
    }
    if ($city) {
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO $table ($valueField, city) VALUES (?, ?)");
        if (!$stmt) throw new Exception("Database error: " . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, 'ss', $value, $city);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO $table ($valueField) VALUES (?)");
        if (!$stmt) throw new Exception("Database error: " . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, 's', $value);
    }
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        try { (new \CubeSpace\EmailService())->notifyAdminAction('add', "$type: $value", "Added new $type"); } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
        echo json_encode(['success' => true, 'id' => mysqli_stmt_insert_id($stmt), 'value' => $value]);
    } else {
        http_response_code(409);
        echo json_encode(['error' => 'Already exists']);
    }
    mysqli_stmt_close($stmt);

} elseif ($action === 'delete') {
    CSRFManager::require();
    $value = trim($_POST['value'] ?? '');
    if (!$value) {
        http_response_code(400);
        die(json_encode(['error' => 'Value is required']));
    }
    $value = mb_strtolower($value);
    $stmt = mysqli_prepare($conn, "DELETE FROM $table WHERE $valueField = ?");
    mysqli_stmt_bind_param($stmt, 's', $value);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    try { (new \CubeSpace\EmailService())->notifyAdminAction('delete', "$type: $value", "Deleted $type"); } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
    echo json_encode(['success' => true, 'deleted' => $deleted]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
