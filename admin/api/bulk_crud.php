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

function get_table($page) {
    $map = [
        'contacts'      => ['table' => 'contacts',      'id_col' => 'id', 'images_col' => null],
        'managed-office'=> ['table' => 'managed_offices', 'id_col' => 'id', 'images_col' => 'images'],
        'office-space'  => ['table' => 'office_spaces',  'id_col' => 'id', 'images_col' => 'images'],
        'furnished'     => ['table' => 'furnished_offices', 'id_col' => 'id', 'images_col' => 'images'],
        'unfurnished'   => ['table' => 'unfurnished_offices', 'id_col' => 'id', 'images_col' => 'images'],
        'activity'      => ['table' => 'activity_log',   'id_col' => 'id', 'images_col' => null],
    ];
    return $map[$page] ?? null;
}

function bulk_dual_table_update($conn, $ids, $types, $field, $value, $actionName) {
    $af = 0;
    mysqli_begin_transaction($conn);
    try {
        $grouped = [];
        foreach ($ids as $i => $id) {
            $t = $types[$i] ?? 'furnished';
            $grouped[$t][] = (int)$id;
        }
        foreach ($grouped as $tbl => $idList) {
            $tblInfo = get_table($tbl);
            if (!$tblInfo) continue;
            $tblName = $tblInfo['table'];
            $idCol = $tblInfo['id_col'];
            $idPh = implode(',', array_fill(0, count($idList), '?'));
            $stmt = mysqli_prepare($conn, "UPDATE $tblName SET $field=? WHERE $idCol IN ($idPh)");
            $typesStr = (is_int($value) ? 'i' : 's') . str_repeat('i', count($idList));
            $params = array_merge([$value], $idList);
            mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
            if (mysqli_stmt_execute($stmt)) $af += mysqli_stmt_affected_rows($stmt);
        }
        log_activity($conn, $actionName, 'office_spaces', 0, ['ids' => $ids, 'count' => $af]);
        publish_event('bulk_operation', 'office_spaces', 0, "$af record(s) updated");
        JsonCache::incrementGlobalVersion();
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => "$af record(s) updated successfully"]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update records']);
    }
}

if ($action === 'bulk_delete') {
    $page = trim($_POST['page'] ?? '');
    $ids = $_POST['ids'] ?? [];
    $types = $_POST['types'] ?? [];

    if (!$page || !is_array($ids) || empty($ids)) {
        http_response_code(400);
        die(json_encode(['error' => 'Page and IDs are required']));
    }

    if ($page === 'office-space' && !empty($types)) {
        $af = 0;
        mysqli_begin_transaction($conn);
        try {
            $grouped = [];
            foreach ($ids as $i => $id) {
                $t = $types[$i] ?? 'furnished';
                $grouped[$t][] = (int)$id;
            }
            foreach ($grouped as $tbl => $idList) {
                $tblInfo = get_table($tbl);
                if (!$tblInfo) continue;
                $tblName = $tblInfo['table'];
                $idCol = $tblInfo['id_col'];
                $idPh = implode(',', array_fill(0, count($idList), '?'));
                if ($tblInfo['images_col']) {
                    $s = mysqli_prepare($conn, "SELECT $idCol, {$tblInfo['images_col']} FROM $tblName WHERE $idCol IN ($idPh)");
                    mysqli_stmt_bind_param($s, str_repeat('i', count($idList)), ...$idList);
                    mysqli_stmt_execute($s);
                    $rs = mysqli_stmt_get_result($s);
                    while ($r = mysqli_fetch_assoc($rs)) {
                        $imgs = json_decode($r[$tblInfo['images_col']] ?? '[]', true);
                        foreach ($imgs as $img) { $fp = __DIR__ . '/../..' . $img; if (file_exists($fp)) unlink($fp); }
                    }
                }
                $del = mysqli_prepare($conn, "DELETE FROM $tblName WHERE $idCol IN ($idPh)");
                mysqli_stmt_bind_param($del, str_repeat('i', count($idList)), ...$idList);
                if (mysqli_stmt_execute($del)) $af += mysqli_stmt_affected_rows($del);
            }
            log_activity($conn, 'bulk_delete', 'office_spaces', 0, ['ids' => $ids, 'count' => $af]);
            publish_event('bulk_operation', 'office_spaces', 0, "$af record(s) deleted");
            JsonCache::incrementGlobalVersion();
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => "$af record(s) deleted successfully"]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete records']);
        }
        exit;
    }

    $info = get_table($page);
    if (!$info) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid page']));
    }

    $table = $info['table'];
    $imagesCol = $info['images_col'];
    $idCol = $info['id_col'];

    $idList = array_map('intval', $ids);
    $idPlaceholders = implode(',', array_fill(0, count($idList), '?'));

    if ($imagesCol) {
        $stmt = mysqli_prepare($conn, "SELECT $idCol, $imagesCol FROM $table WHERE $idCol IN ($idPlaceholders)");
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($idList)), ...$idList);
        mysqli_stmt_execute($stmt);
        $rows = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($rows)) {
            $images = json_decode($row[$imagesCol] ?? '[]', true);
            foreach ($images as $img) {
                $filePath = __DIR__ . '/../..' . $img;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM $table WHERE $idCol IN ($idPlaceholders)");
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($idList)), ...$idList);
    
    mysqli_begin_transaction($conn);
    try {
        if (mysqli_stmt_execute($stmt)) {
            $affected = mysqli_stmt_affected_rows($stmt);
            log_activity($conn, 'bulk_delete', $table, 0, ['ids' => $idList, 'count' => $affected]);
            publish_event('bulk_operation', $table, 0, "$affected record(s) deleted");
            JsonCache::incrementGlobalVersion();
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => "$affected record(s) deleted successfully"]);
        } else {
            throw new Exception('Failed to delete records');
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete records']);
    }
    exit;
}

if ($action === 'bulk_status') {
    $page = trim($_POST['page'] ?? '');
    $ids = $_POST['ids'] ?? [];
    $types = $_POST['types'] ?? [];
    $status = trim($_POST['status'] ?? '');

    if (!$page || !is_array($ids) || empty($ids) || !$status) {
        http_response_code(400);
        die(json_encode(['error' => 'Page, IDs, and status are required']));
    }

    if ($page === 'office-space' && !empty($types)) {
        if (!in_array($status, ['draft', 'published', 'archived'])) {
            http_response_code(400);
            die(json_encode(['error' => 'Invalid status for listings']));
        }
        bulk_dual_table_update($conn, $ids, $types, 'status', $status, 'bulk_status');
        exit;
    }

    $info = get_table($page);
    if (!$info) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid page']));
    }

    $table = $info['table'];
    $idCol = $info['id_col'];
    $idList = array_map('intval', $ids);
    $idPlaceholders = implode(',', array_fill(0, count($idList), '?'));

    if ($table === 'contacts') {
        if (!in_array($status, ['new', 'contacted', 'closed'])) {
            http_response_code(400);
            die(json_encode(['error' => 'Invalid status for contacts']));
        }
    } elseif (in_array($table, ['managed_offices', 'office_spaces', 'furnished_offices', 'unfurnished_offices'])) {
        if (!in_array($status, ['draft', 'published', 'archived'])) {
            http_response_code(400);
            die(json_encode(['error' => 'Invalid status for listings']));
        }
    } else {
        http_response_code(400);
        die(json_encode(['error' => 'Status changes not supported for this table']));
    }

    $stmt = mysqli_prepare($conn, "UPDATE $table SET status=? WHERE $idCol IN ($idPlaceholders)");
    $typesStr = 's' . str_repeat('i', count($idList));
    $params = array_merge([$status], $idList);
    mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
    
    mysqli_begin_transaction($conn);
    try {
        if (mysqli_stmt_execute($stmt)) {
            $affected = mysqli_stmt_affected_rows($stmt);
            log_activity($conn, 'bulk_status', $table, 0, ['ids' => $idList, 'status' => $status, 'count' => $affected]);
            publish_event('bulk_operation', $table, 0, "$affected record(s) updated to $status");
            JsonCache::incrementGlobalVersion();
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => "$affected record(s) updated to $status"]);
        } else {
            throw new Exception('Failed to update records');
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update records']);
    }
    exit;
}

if ($action === 'bulk_featured') {
    $page = trim($_POST['page'] ?? '');
    $ids = $_POST['ids'] ?? [];
    $types = $_POST['types'] ?? [];
    $featured = (int)($_POST['featured'] ?? 0);

    if (!$page || !is_array($ids) || empty($ids)) {
        http_response_code(400);
        die(json_encode(['error' => 'Page and IDs are required']));
    }

    if ($page === 'office-space' && !empty($types)) {
        bulk_dual_table_update($conn, $ids, $types, 'featured', $featured, 'bulk_featured');
        exit;
    }

    $info = get_table($page);
    if (!$info) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid page']));
    }

    $table = $info['table'];
    $idCol = $info['id_col'];

    if (!in_array($table, ['managed_offices', 'office_spaces', 'furnished_offices', 'unfurnished_offices'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Featured toggle not supported for this table']));
    }

    $idList = array_map('intval', $ids);
    $idPlaceholders = implode(',', array_fill(0, count($idList), '?'));

    $stmt = mysqli_prepare($conn, "UPDATE $table SET featured=? WHERE $idCol IN ($idPlaceholders)");
    $typesStr = 'i' . str_repeat('i', count($idList));
    $params = array_merge([$featured], $idList);
    mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
    
    mysqli_begin_transaction($conn);
    try {
        if (mysqli_stmt_execute($stmt)) {
            $affected = mysqli_stmt_affected_rows($stmt);
            $label = $featured ? 'featured' : 'unfeatured';
            log_activity($conn, 'bulk_featured', $table, 0, ['ids' => $idList, 'featured' => $featured, 'count' => $affected]);
            publish_event('bulk_operation', $table, 0, "$affected record(s) marked as $label");
            JsonCache::incrementGlobalVersion();
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => "$affected record(s) marked as $label"]);
        } else {
            throw new Exception('Failed to update records');
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update records']);
    }
    exit;
}

if ($action === 'bulk_delete_activity') {
    $ids = $_POST['ids'] ?? [];

    if (!is_array($ids) || empty($ids)) {
        http_response_code(400);
        die(json_encode(['error' => 'IDs are required']));
    }

    $idList = array_map('intval', $ids);
    $idPlaceholders = implode(',', array_fill(0, count($idList), '?'));

    $stmt = mysqli_prepare($conn, "DELETE FROM activity_log WHERE id IN ($idPlaceholders)");
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($idList)), ...$idList);
    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_stmt_affected_rows($stmt);
        log_activity($conn, 'bulk_delete', 'activity_log', 0, ['ids' => $idList, 'count' => $affected]);
        echo json_encode(['success' => true, 'message' => "$affected activity log entry(s) deleted"]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete activity log entries']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
