<?php
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../api/db_config.php';
require_once __DIR__ . '/../jwt_helper.php';
require_once __DIR__ . '/../../lib/cache.php';
require_once __DIR__ . '/../../lib/events.php';

header('Content-Type: application/json');

$jwtPayload = require_jwt_auth();
secure_session_start();
$_SESSION['admin_id'] = $jwtPayload['sub'];
$_SESSION['admin_user'] = $jwtPayload['user'];

$action = $_GET['action'] ?? '';

function log_activity($conn, $action, $table, $recordId, $details = null) {
    $uid = (int)$_SESSION['admin_id'];
    $uname = $_SESSION['admin_user'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $det = $details ? json_encode($details) : null;
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_log (admin_id, admin_username, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isssiss', $uid, $uname, $action, $table, $recordId, $det, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT * FROM contacts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    if ($row) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Contact not found']);
    }
    exit;
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (!$id || !$status) {
        http_response_code(400);
        die(json_encode(['error' => 'ID and status are required']));
    }

    if (!in_array($status, ['new', 'contacted', 'closed'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid status value']));
    }

    $stmt = mysqli_prepare($conn, "UPDATE contacts SET status=?, admin_notes=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssi', $status, $adminNotes, $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'update', 'contacts', $id, ['status' => $status]);
        publish_event('contact_updated', 'contact', $id, "Status changed to $status");
        echo json_encode(['success' => true, 'message' => 'Updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update contact']);
    }
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = mysqli_prepare($conn, "SELECT id FROM contacts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if (!$row) {
        http_response_code(404);
        die(json_encode(['error' => 'Contact not found']));
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM contacts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'delete', 'contacts', $id, []);
        publish_event('contact_deleted', 'contact', $id, 'Contact deleted');
        echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete contact']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
