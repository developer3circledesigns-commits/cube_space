<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../jwt_helper.php';
admin_require_lib('csrf.php');
admin_require_lib('cache.php');
admin_require_lib('events.php');
cubespace_require_project('src/autoload.php');

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


// =====================================================
// EXTRAS (Feature Highlights & SEO Text)
// =====================================================
if ($action === 'get_extras') {
    $officeId = (int)($_GET['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }
    $extStmt = mysqli_prepare($conn, "SELECT feature_highlights, seo_text FROM managed_offices WHERE id = ?");
    mysqli_stmt_bind_param($extStmt, 'i', $officeId);
    mysqli_stmt_execute($extStmt);
    $extResult = mysqli_stmt_get_result($extStmt);
    $extras = mysqli_fetch_assoc($extResult) ?: [];
    mysqli_stmt_close($extStmt);
    if (isset($extras['feature_highlights'])) {
        $extras['feature_highlights'] = json_decode($extras['feature_highlights'], true) ?: [];
    }
    echo json_encode(['success' => true, 'extras' => $extras]);
    exit;
}

// =====================================================
// LEASING OPTIONS
// =====================================================
if ($action === 'list_leasing') {
    $officeId = (int)($_GET['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }
    $stmt = mysqli_prepare($conn, "SELECT * FROM office_leasing_options WHERE office_id = ? ORDER BY sort_order ASC, id ASC");
    mysqli_stmt_bind_param($stmt, 'i', $officeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'leasing' => $items]);
    exit;
}

if ($action === 'create_leasing') {
    CSRFManager::require();
    $officeId = (int)($_POST['office_id'] ?? 0);
    $optionTitle = trim($_POST['option_title'] ?? '');
    $optionDesc = trim($_POST['option_desc'] ?? '');
    $optionPrice = trim($_POST['option_price'] ?? '');
    $optionImage = trim($_POST['option_image'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (!$officeId || !$optionTitle) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id and option_title are required']));
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO office_leasing_options (office_id, option_title, option_desc, option_price, option_image, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'issssii', $officeId, $optionTitle, $optionDesc, $optionPrice, $optionImage, $sortOrder, $isActive);
    if (mysqli_stmt_execute($stmt)) {
        $newId = mysqli_insert_id($conn);
        log_activity($conn, 'create', 'office_leasing_options', $newId, ['office_id' => $officeId, 'option_title' => $optionTitle]);
        publish_event('leasing_created', 'leasing', $newId, $optionTitle);
        try { (new \CubeSpace\EmailService())->notifyAdminAction('create', "leasing option #$newId", "Office ID: $officeId\nTitle: $optionTitle\nDescription: $optionDesc\nPrice: $optionPrice\nSort Order: $sortOrder\nActive: " . ($isActive ? 'Yes' : 'No')); } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'Leasing option created successfully', 'id' => $newId]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create leasing option']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

if ($action === 'update_leasing') {
    CSRFManager::require();
    $id = (int)($_POST['id'] ?? 0);
    $officeId = (int)($_POST['office_id'] ?? 0);
    $optionTitle = trim($_POST['option_title'] ?? '');
    $optionDesc = trim($_POST['option_desc'] ?? '');
    $optionPrice = trim($_POST['option_price'] ?? '');
    $optionImage = trim($_POST['option_image'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (!$id || !$officeId || !$optionTitle) {
        http_response_code(400);
        die(json_encode(['error' => 'id, office_id, and option_title are required']));
    }

    $stmt = mysqli_prepare($conn,
        "UPDATE office_leasing_options SET office_id=?, option_title=?, option_desc=?, option_price=?, option_image=?, sort_order=?, is_active=? WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'issssiii', $officeId, $optionTitle, $optionDesc, $optionPrice, $optionImage, $sortOrder, $isActive, $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'update', 'office_leasing_options', $id, ['office_id' => $officeId, 'option_title' => $optionTitle]);
        publish_event('leasing_updated', 'leasing', $id, $optionTitle);
        try { (new \CubeSpace\EmailService())->notifyAdminAction('update', "leasing option #$id", "Office ID: $officeId\nTitle: $optionTitle\nDescription: $optionDesc\nPrice: $optionPrice\nSort Order: $sortOrder\nActive: " . ($isActive ? 'Yes' : 'No')); } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'Leasing option updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update leasing option']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

if ($action === 'delete_leasing') {
    CSRFManager::require();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'id is required']));
    }

    $stmt = mysqli_prepare($conn, "SELECT office_id FROM office_leasing_options WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        http_response_code(404);
        die(json_encode(['error' => 'Leasing option not found']));
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM office_leasing_options WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'delete', 'office_leasing_options', $id, ['office_id' => $row['office_id']]);
        publish_event('leasing_deleted', 'leasing', $id);
        try { (new \CubeSpace\EmailService())->notifyAdminAction('delete', "leasing option #$id", "Office ID: {$row['office_id']}"); } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'Leasing option deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete leasing option']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// =====================================================
// FEATURE HIGHLIGHTS & SEO TEXT
// =====================================================
if ($action === 'update_extras') {
    CSRFManager::require();
    $officeId = (int)($_POST['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }

    $featureHighlights = $_POST['feature_highlights'] ?? null;
    if ($featureHighlights !== null) {
        if (is_string($featureHighlights)) {
            $decoded = json_decode($featureHighlights, true);
            $featureHighlights = json_encode($decoded ?? []);
        } else {
            $featureHighlights = json_encode($featureHighlights);
        }
    }

    $seoText = $_POST['seo_text'] ?? null;

    $stmt = mysqli_prepare($conn, "UPDATE managed_offices SET feature_highlights = ?, seo_text = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $featureHighlights, $seoText, $officeId);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'update', 'managed_offices', $officeId, ['office_id' => $officeId, 'fields' => ['feature_highlights', 'seo_text']]);
        publish_event('listing_updated', 'managed_offices', $officeId, 'Extras updated');
        try { (new \CubeSpace\EmailService())->notifyAdminAction('update', "managed office #$officeId", 'Extras (feature highlights, SEO text) updated'); } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'Office extras updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update office extras']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

} catch (Throwable $e) {
    log_app_error('detail_crud.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
