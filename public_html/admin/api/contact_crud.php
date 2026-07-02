<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../jwt_helper.php';
admin_require_lib('csrf.php');
admin_require_lib('cache.php');
admin_require_lib('events.php');

header('Content-Type: application/json');

$jwtPayload = require_jwt_auth();
secure_session_start();
$_SESSION['admin_id'] = $jwtPayload['sub'];
$_SESSION['admin_user'] = $jwtPayload['user'];

$action = $_GET['action'] ?? '';

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {


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
    CSRFManager::require();
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

    if ($status === 'contacted') {
        $stmt = mysqli_prepare($conn, "UPDATE contacts SET status=?, admin_notes=?, contacted_at=NOW() WHERE id=?");
    } elseif ($status === 'closed') {
        $stmt = mysqli_prepare($conn, "UPDATE contacts SET status=?, admin_notes=?, closed_at=NOW() WHERE id=?");
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE contacts SET status=?, admin_notes=? WHERE id=?");
    }
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

if ($action === 'export') {
    $statusFilter = trim($_GET['status'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $where = '';
    $params = [];
    $types = '';

    $conditions = [];
    if ($statusFilter && in_array($statusFilter, ['new','contacted','closed'])) {
        $conditions[] = "status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    if ($search) {
        $conditions[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ? OR company LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        $types .= 'ssss';
    }
    if (!empty($conditions)) {
        $where = " WHERE " . implode(' AND ', $conditions);
    }

    $sql = "SELECT * FROM contacts$where ORDER BY created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contacts_export_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Name','Phone','Email','Interest','Company','Seats','Message','Office ID','Listing Code','Source','IP Address','User Agent','Status','Admin Notes','Contacted At','Closed At','Submitted At']);
    while ($row = mysqli_fetch_assoc($result)) {
        $interest = $row['interest'] === 'managed' ? 'Managed Furnished Office' : ($row['interest'] === 'furnished' ? 'Furnished / Unfurnished Office' : $row['interest']);
        fputcsv($out, [
            $row['id'], $row['name'], $row['phone'], $row['email'],
            $interest, $row['company'], $row['seats'], $row['message'],
            $row['office_id'], $row['listing_code'], $row['source'],
            $row['submitted_ip'], $row['user_agent'], $row['status'],
            $row['admin_notes'], $row['contacted_at'], $row['closed_at'], $row['created_at']
        ]);
    }
    fclose($out);
    exit;
}

if ($action === 'reply') {
    CSRFManager::require();
    $id = (int)($_POST['id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (!$id || !$subject || !$body) {
        http_response_code(400);
        die(json_encode(['error' => 'ID, subject, and body are required']));
    }

    $stmt = mysqli_prepare($conn, "SELECT name, email FROM contacts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $contact = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$contact || !$contact['email']) {
        http_response_code(400);
        die(json_encode(['error' => 'Contact not found or has no email address']));
    }

    require_once cubespace_project_root() . '/src/EmailService.php';
    $mail = new \CubeSpace\EmailService();
    $htmlBody = nl2br(htmlspecialchars($body));
    $fullHtml = '<p>Dear ' . htmlspecialchars($contact['name']) . ',</p>' . $htmlBody . '<hr><p style="color:#888;font-size:12px;">CubeSpace Team</p>';

    if (!$mail->isEnabled()) {
        log_activity($conn, 'email_reply', 'contacts', $id, ['to' => $contact['email'], 'subject' => $subject, 'note' => 'mail_disabled']);
        echo json_encode(['success' => true, 'warning' => true, 'message' => 'Mail is not configured. Reply logged for delivery after deployment.']);
        exit;
    }

    $sent = $mail->send($contact['email'], $subject, $fullHtml, $mail->getAdminEmail());

    if ($sent) {
        log_activity($conn, 'email_reply', 'contacts', $id, ['to' => $contact['email'], 'subject' => $subject]);
        echo json_encode(['success' => true, 'message' => 'Email sent to ' . $contact['email']]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send email. Check mail configuration.']);
    }
    exit;
}

if ($action === 'delete') {
    CSRFManager::require();
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

} catch (Throwable $e) {
    log_app_error('contact_crud.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
