<?php
try {
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



if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); die(json_encode(['error' => 'ID required'])); }
    $stmt = mysqli_prepare($conn, "SELECT id, username, email, role, is_active, last_login, created_at FROM admins WHERE id = ?");
    if (!$stmt) { http_response_code(500); die(json_encode(['error' => 'DB error: ' . mysqli_error($conn)])); }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$r) { http_response_code(404); die(json_encode(['error' => 'Admin not found'])); }
    echo json_encode(['success' => true, 'data' => $r]);
    exit;
}

function check_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    }
    if (!validate_csrf_token($token)) { http_response_code(400); die(json_encode(['error' => 'Invalid form token'])); }
}

if ($action === 'create') {
    check_csrf();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $isActive = (int)($_POST['is_active'] ?? 1);

    if (!$username || !$email || strlen($password) < 8) {
        http_response_code(400); die(json_encode(['error' => 'Username, email required and password must be 8+ characters']));
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); die(json_encode(['error' => 'Invalid email format']));
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare($conn, "INSERT INTO admins (username, email, password, is_active) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500); die(json_encode(['error' => 'Database error: ' . mysqli_error($conn)]));
    }
    mysqli_stmt_bind_param($stmt, 'sssi', $username, $email, $hash, $isActive);
    if (mysqli_stmt_execute($stmt)) {
        $newId = mysqli_insert_id($conn);
        log_activity($conn, 'create', 'admins', $newId, ['username' => $username, 'email' => $email]);
        publish_event('admin_created', 'admins', $newId, "Admin '$username' created");
        echo json_encode(['success' => true, 'message' => 'Admin created successfully', 'id' => $newId]);
    } else {
        $err = mysqli_errno($conn) === 1062 ? 'Username or email already exists' : 'Database error: ' . mysqli_error($conn);
        http_response_code(409); die(json_encode(['error' => $err]));
    }
    exit;
}

if ($action === 'update') {
    check_csrf();

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { http_response_code(400); die(json_encode(['error' => 'ID required'])); }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $isActive = (int)($_POST['is_active'] ?? 1);
    $password = $_POST['password'] ?? '';

    if (!$username || !$email) {
        http_response_code(400); die(json_encode(['error' => 'Username and email are required']));
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); die(json_encode(['error' => 'Invalid email format']));
    }

    $s = mysqli_prepare($conn, "SELECT id FROM admins WHERE (username=? OR email=?) AND id!=?");
    if (!$s) { http_response_code(500); die(json_encode(['error' => 'DB error: ' . mysqli_error($conn)])); }
    mysqli_stmt_bind_param($s, 'ssi', $username, $email, $id);
    mysqli_stmt_execute($s);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($s))) {
        http_response_code(409); die(json_encode(['error' => 'Username or email already in use']));
    }

    if ($password) {
        if (strlen($password) < 8) { http_response_code(400); die(json_encode(['error' => 'Password must be 8+ characters'])); }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($conn, "UPDATE admins SET username=?, email=?, password=?, is_active=? WHERE id=?");
        if (!$stmt) { http_response_code(500); die(json_encode(['error' => 'DB error: ' . mysqli_error($conn)])); }
        mysqli_stmt_bind_param($stmt, 'sssii', $username, $email, $hash, $isActive, $id);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE admins SET username=?, email=?, is_active=? WHERE id=?");
        if (!$stmt) { http_response_code(500); die(json_encode(['error' => 'DB error: ' . mysqli_error($conn)])); }
        mysqli_stmt_bind_param($stmt, 'ssii', $username, $email, $isActive, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'update', 'admins', $id, ['username' => $username, 'email' => $email, 'is_active' => $isActive, 'password_changed' => !!$password]);
        publish_event('admin_updated', 'admins', $id, "Admin '$username' updated");
        echo json_encode(['success' => true, 'message' => 'Admin updated successfully']);
    } else {
        http_response_code(500); die(json_encode(['error' => 'Update failed: ' . mysqli_error($conn)]));
    }
    exit;
}

if ($action === 'delete') {
    CSRFManager::require();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { http_response_code(400); die(json_encode(['error' => 'ID required'])); }

    $s = mysqli_prepare($conn, "SELECT username FROM admins WHERE id=?");
    if (!$s) { http_response_code(500); die(json_encode(['error' => 'DB error: ' . mysqli_error($conn)])); }
    mysqli_stmt_bind_param($s, 'i', $id);
    mysqli_stmt_execute($s);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    if (!$row) { http_response_code(404); die(json_encode(['error' => 'Admin not found'])); }

    if ($id === (int)$jwtPayload['sub']) {
        http_response_code(400); die(json_encode(['error' => 'You cannot delete your own account']));
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM admins WHERE id=?");
    if (!$stmt) { http_response_code(500); die(json_encode(['error' => 'DB error: ' . mysqli_error($conn)])); }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        log_activity($conn, 'delete', 'admins', $id, ['username' => $row['username']]);
        publish_event('admin_deleted', 'admins', $id, "Admin '{$row['username']}' deleted");
        echo json_encode(['success' => true, 'message' => 'Admin deleted successfully']);
    } else {
        http_response_code(500); die(json_encode(['error' => 'Delete failed: ' . mysqli_error($conn)]));
    }
    exit;
}

if ($action === 'toggle_active') {
    CSRFManager::require();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { http_response_code(400); die(json_encode(['error' => 'ID required'])); }

    if ($id === (int)$jwtPayload['sub']) {
        http_response_code(400); die(json_encode(['error' => 'You cannot deactivate your own account']));
    }

    $s = mysqli_prepare($conn, "SELECT is_active, username FROM admins WHERE id=?");
    if (!$s) { http_response_code(500); die(json_encode(['error' => 'DB error: ' . mysqli_error($conn)])); }
    mysqli_stmt_bind_param($s, 'i', $id);
    mysqli_stmt_execute($s);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    if (!$row) { http_response_code(404); die(json_encode(['error' => 'Admin not found'])); }

    $newActive = $row['is_active'] ? 0 : 1;
    $stmt = mysqli_prepare($conn, "UPDATE admins SET is_active=? WHERE id=?");
    if (!$stmt) { http_response_code(500); die(json_encode(['error' => 'DB error: ' . mysqli_error($conn)])); }
    mysqli_stmt_bind_param($stmt, 'ii', $newActive, $id);
    if (mysqli_stmt_execute($stmt)) {
        $label = $newActive ? 'activated' : 'deactivated';
        log_activity($conn, 'toggle_active', 'admins', $id, ['username' => $row['username'], 'is_active' => $newActive]);
        publish_event('admin_updated', 'admins', $id, "Admin '{$row['username']}' $label");
        echo json_encode(['success' => true, 'message' => "Admin $label successfully", 'is_active' => $newActive]);
    } else {
        http_response_code(500); die(json_encode(['error' => 'Toggle failed: ' . mysqli_error($conn)]));
    }
    exit;
}

if ($action === 'export') {
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';

    $where = [];
    $params = [];
    $types = '';

    if ($search) {
        $where[] = "(username LIKE ? OR email LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp;
        $types .= 'ss';
    }
    if ($status === 'active') {
        $where[] = "is_active=1";
    } elseif ($status === 'inactive') {
        $where[] = "is_active=0";
    }

    $sql = "SELECT id, username, email, role, is_active, last_login, created_at FROM admins";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY created_at DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admins_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Username', 'Email', 'Role', 'Status', 'Last Login', 'Created At']);
    while ($r = mysqli_fetch_assoc($rows)) {
        fputcsv($out, [
            $r['id'],
            $r['username'],
            $r['email'],
            $r['role'],
            $r['is_active'] ? 'Active' : 'Inactive',
            $r['last_login'] ? date('Y-m-d H:i', strtotime($r['last_login'])) : 'Never',
            $r['created_at'] ? date('Y-m-d H:i', strtotime($r['created_at'])) : ''
        ]);
    }
    fclose($out);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

} catch (Throwable $e) {
    log_app_error('admin_crud.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
