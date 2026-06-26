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

// =====================================================
// REVIEWS
// =====================================================
if ($action === 'list_reviews') {
    $officeId = (int)($_GET['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }
    $stmt = mysqli_prepare($conn, "SELECT * FROM office_reviews WHERE office_id = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $officeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $reviews = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $reviews[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'reviews' => $reviews]);
    exit;
}

if ($action === 'create_review') {
    $officeId = (int)($_POST['office_id'] ?? 0);
    $reviewerName = trim($_POST['reviewer_name'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);
    $reviewText = trim($_POST['review_text'] ?? '');
    $status = trim($_POST['status'] ?? 'approved');

    if (!$officeId || !$reviewerName) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id and reviewer_name are required']));
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO office_reviews (office_id, reviewer_name, rating, review_text, status) VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'isiss', $officeId, $reviewerName, $rating, $reviewText, $status);
    if (mysqli_stmt_execute($stmt)) {
        $newId = mysqli_insert_id($conn);
        log_activity($conn, 'create', 'office_reviews', $newId, ['office_id' => $officeId, 'reviewer_name' => $reviewerName]);
        publish_event('review_created', 'review', $newId, $reviewerName);
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'Review created successfully', 'id' => $newId]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create review']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

if ($action === 'update_review') {
    $id = (int)($_POST['id'] ?? 0);
    $officeId = (int)($_POST['office_id'] ?? 0);
    $reviewerName = trim($_POST['reviewer_name'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);
    $reviewText = trim($_POST['review_text'] ?? '');
    $status = trim($_POST['status'] ?? 'approved');

    if (!$id || !$officeId || !$reviewerName) {
        http_response_code(400);
        die(json_encode(['error' => 'id, office_id, and reviewer_name are required']));
    }

    $stmt = mysqli_prepare($conn,
        "UPDATE office_reviews SET office_id=?, reviewer_name=?, rating=?, review_text=?, status=? WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'isissi', $officeId, $reviewerName, $rating, $reviewText, $status, $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'update', 'office_reviews', $id, ['office_id' => $officeId, 'reviewer_name' => $reviewerName]);
        publish_event('review_updated', 'review', $id, $reviewerName);
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'Review updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update review']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

if ($action === 'delete_review') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'id is required']));
    }

    $stmt = mysqli_prepare($conn, "SELECT office_id FROM office_reviews WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        http_response_code(404);
        die(json_encode(['error' => 'Review not found']));
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM office_reviews WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'delete', 'office_reviews', $id, ['office_id' => $row['office_id']]);
        publish_event('review_deleted', 'review', $id);
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete review']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// =====================================================
// FAQ
// =====================================================
if ($action === 'list_faq') {
    $officeId = (int)($_GET['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }
    $stmt = mysqli_prepare($conn, "SELECT * FROM office_faq WHERE office_id = ? ORDER BY sort_order ASC, id ASC");
    mysqli_stmt_bind_param($stmt, 'i', $officeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'faq' => $items]);
    exit;
}

if ($action === 'create_faq') {
    $officeId = (int)($_POST['office_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (!$officeId || !$question || !$answer) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id, question, and answer are required']));
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO office_faq (office_id, question, answer, sort_order, is_active) VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'issii', $officeId, $question, $answer, $sortOrder, $isActive);
    if (mysqli_stmt_execute($stmt)) {
        $newId = mysqli_insert_id($conn);
        log_activity($conn, 'create', 'office_faq', $newId, ['office_id' => $officeId, 'question' => $question]);
        publish_event('faq_created', 'faq', $newId, mb_substr($question, 0, 100));
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'FAQ item created successfully', 'id' => $newId]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create FAQ item']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

if ($action === 'update_faq') {
    $id = (int)($_POST['id'] ?? 0);
    $officeId = (int)($_POST['office_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (!$id || !$officeId || !$question || !$answer) {
        http_response_code(400);
        die(json_encode(['error' => 'id, office_id, question, and answer are required']));
    }

    $stmt = mysqli_prepare($conn,
        "UPDATE office_faq SET office_id=?, question=?, answer=?, sort_order=?, is_active=? WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'issiii', $officeId, $question, $answer, $sortOrder, $isActive, $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'update', 'office_faq', $id, ['office_id' => $officeId, 'question' => $question]);
        publish_event('faq_updated', 'faq', $id, mb_substr($question, 0, 100));
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'FAQ item updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update FAQ item']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

if ($action === 'delete_faq') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'id is required']));
    }

    $stmt = mysqli_prepare($conn, "SELECT office_id FROM office_faq WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        http_response_code(404);
        die(json_encode(['error' => 'FAQ item not found']));
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM office_faq WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'delete', 'office_faq', $id, ['office_id' => $row['office_id']]);
        publish_event('faq_deleted', 'faq', $id);
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'FAQ item deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete FAQ item']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// =====================================================
// BUILDING DETAILS
// =====================================================
if ($action === 'get_building') {
    $officeId = (int)($_GET['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }
    $stmt = mysqli_prepare($conn, "SELECT * FROM office_building_details WHERE office_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $officeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Also get extras from managed_offices
    $extStmt = mysqli_prepare($conn, "SELECT feature_highlights, seo_text FROM managed_offices WHERE id = ?");
    mysqli_stmt_bind_param($extStmt, 'i', $officeId);
    mysqli_stmt_execute($extStmt);
    $extResult = mysqli_stmt_get_result($extStmt);
    $extras = mysqli_fetch_assoc($extResult) ?: [];
    mysqli_stmt_close($extStmt);
    if (isset($extras['feature_highlights'])) {
        $extras['feature_highlights'] = json_decode($extras['feature_highlights'], true) ?: [];
    }

    echo json_encode(['success' => true, 'building' => $row, 'extras' => $extras]);
    exit;
}

if ($action === 'save_building') {
    $officeId = (int)($_POST['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }

    $buildingName = trim($_POST['building_name'] ?? '');
    $yearBuilt = trim($_POST['year_built'] ?? '');
    $totalFloors = isset($_POST['total_floors']) && $_POST['total_floors'] !== '' ? (int)$_POST['total_floors'] : 0;
    $floorPlateArea = trim($_POST['floor_plate_area'] ?? '');
    $elevators = isset($_POST['elevators']) && $_POST['elevators'] !== '' ? (int)$_POST['elevators'] : 0;
    $parking = trim($_POST['parking'] ?? '');
    $nearestMetro = trim($_POST['nearest_metro'] ?? '');
    $nearestRailway = trim($_POST['nearest_railway'] ?? '');
    $airport = trim($_POST['airport'] ?? '');
    $busStop = trim($_POST['bus_stop'] ?? '');

    $checkStmt = mysqli_prepare($conn, "SELECT id FROM office_building_details WHERE office_id = ?");
    mysqli_stmt_bind_param($checkStmt, 'i', $officeId);
    mysqli_stmt_execute($checkStmt);
    $exists = mysqli_num_rows(mysqli_stmt_get_result($checkStmt)) > 0;
    mysqli_stmt_close($checkStmt);

    if ($exists) {
        $stmt = mysqli_prepare($conn,
            "UPDATE office_building_details SET building_name=?, year_built=?, total_floors=?, floor_plate_area=?, elevators=?, parking=?, nearest_metro=?, nearest_railway=?, airport=?, bus_stop=? WHERE office_id=?"
        );
        mysqli_stmt_bind_param($stmt, 'ssisssssssi',
            $buildingName, $yearBuilt, $totalFloors, $floorPlateArea, $elevators,
            $parking, $nearestMetro, $nearestRailway, $airport, $busStop, $officeId
        );
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, 'update', 'office_building_details', $officeId, ['office_id' => $officeId]);
            publish_event('building_updated', 'building', $officeId);
            JsonCache::incrementGlobalVersion();
            echo json_encode(['success' => true, 'message' => 'Building details updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update building details']);
        }
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO office_building_details (office_id, building_name, year_built, total_floors, floor_plate_area, elevators, parking, nearest_metro, nearest_railway, airport, bus_stop) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'isssissssss',
            $officeId, $buildingName, $yearBuilt, $totalFloors, $floorPlateArea, $elevators,
            $parking, $nearestMetro, $nearestRailway, $airport, $busStop
        );
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, 'create', 'office_building_details', $officeId, ['office_id' => $officeId]);
            publish_event('building_updated', 'building', $officeId);
            JsonCache::incrementGlobalVersion();
            echo json_encode(['success' => true, 'message' => 'Building details created successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create building details']);
        }
    }
    mysqli_stmt_close($stmt);
    exit;
}

// =====================================================
// CONNECTIVITY
// =====================================================
if ($action === 'get_connectivity') {
    $officeId = (int)($_GET['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }
    $stmt = mysqli_prepare($conn, "SELECT nearest_metro, nearest_railway, airport, bus_stop FROM office_building_details WHERE office_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $officeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'connectivity' => $row]);
    exit;
}

if ($action === 'save_connectivity') {
    $officeId = (int)($_POST['office_id'] ?? 0);
    if (!$officeId) {
        http_response_code(400);
        die(json_encode(['error' => 'office_id is required']));
    }
    $nearestMetro = trim($_POST['nearest_metro'] ?? '');
    $nearestRailway = trim($_POST['nearest_railway'] ?? '');
    $airport = trim($_POST['airport'] ?? '');
    $busStop = trim($_POST['bus_stop'] ?? '');

    $checkStmt = mysqli_prepare($conn, "SELECT id FROM office_building_details WHERE office_id = ?");
    mysqli_stmt_bind_param($checkStmt, 'i', $officeId);
    mysqli_stmt_execute($checkStmt);
    $exists = mysqli_num_rows(mysqli_stmt_get_result($checkStmt)) > 0;
    mysqli_stmt_close($checkStmt);

    if ($exists) {
        $stmt = mysqli_prepare($conn, "UPDATE office_building_details SET nearest_metro=?, nearest_railway=?, airport=?, bus_stop=? WHERE office_id=?");
        mysqli_stmt_bind_param($stmt, 'ssssi', $nearestMetro, $nearestRailway, $airport, $busStop, $officeId);
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, 'update', 'office_building_details', $officeId, ['office_id' => $officeId]);
            publish_event('building_updated', 'building', $officeId);
            JsonCache::incrementGlobalVersion();
            echo json_encode(['success' => true, 'message' => 'Connectivity details saved successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save connectivity details']);
        }
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO office_building_details (office_id, nearest_metro, nearest_railway, airport, bus_stop) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'issss', $officeId, $nearestMetro, $nearestRailway, $airport, $busStop);
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, 'create', 'office_building_details', $officeId, ['office_id' => $officeId]);
            publish_event('building_updated', 'building', $officeId);
            JsonCache::incrementGlobalVersion();
            echo json_encode(['success' => true, 'message' => 'Connectivity details saved successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save connectivity details']);
        }
    }
    mysqli_stmt_close($stmt);
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
