<?php
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');

try {
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/validator.php';
require_once __DIR__ . '/../../api/db_config.php';
require_once __DIR__ . '/../jwt_helper.php';
require_once __DIR__ . '/../../lib/cache.php';
require_once __DIR__ . '/../../lib/events.php';

$jwtPayload = require_jwt_auth();
secure_session_start();
$_SESSION['admin_id'] = $jwtPayload['sub'];
$_SESSION['admin_user'] = $jwtPayload['user'];

$action = $_GET['action'] ?? '';

function slugify($text) {
    $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = mb_strtolower($text);
    return trim($text, '-');
}

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

$ALLOWED_IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

function validate_image_upload($tmpPath, $originalName, $allowedExts) {
    if (!file_exists($tmpPath)) return false;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMimes)) return false;
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return in_array($ext, $allowedExts);
}

if ($action === 'create' || $action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $listingType = trim($_POST['listing_type'] ?? '');
    $table = get_listing_table($listingType);
    if (!$table) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid listing type']));
    }
    $description = trim($_POST['description'] ?? '');
    $city = trim($_POST['city'] ?? 'chennai');
    $area = trim($_POST['area'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float)$_POST['price'] : null;
    
    // Validate latitude and longitude if provided
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
        http_response_code(400);
        die(json_encode(['error' => 'Latitude must be between -90 and 90']));
    }
    if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
        http_response_code(400);
        die(json_encode(['error' => 'Longitude must be between -180 and 180']));
    }

    $priceLabel = trim($_POST['price_label'] ?? '');
    $officeSpaceType = in_array(trim($_POST['office_space_type'] ?? ''), ['rent', 'lease']) ? trim($_POST['office_space_type']) : 'rent';
    $totalSeats = isset($_POST['total_seats']) && $_POST['total_seats'] !== '' ? (int)$_POST['total_seats'] : null;
    $totalAreaSqft = isset($_POST['total_area_sqft']) && $_POST['total_area_sqft'] !== '' ? (int)$_POST['total_area_sqft'] : 0;
    $status = trim($_POST['status'] ?? 'draft');
    $featured = !empty($_POST['featured']) ? 1 : 0;
    $featureHighlights = [];
    if (!empty($_POST['feature_highlights'])) {
        if (is_array($_POST['feature_highlights'])) {
            $featureHighlights = $_POST['feature_highlights'];
        } else {
            $featureHighlights = array_map('trim', explode(',', $_POST['feature_highlights']));
        }
    }
    $featureHighlightsJson = json_encode($featureHighlights);
    $seoText = trim($_POST['seo_text'] ?? '');

    if (!$title || !$listingType) {
        http_response_code(400);
        die(json_encode(['error' => 'Title and listing type are required']));
    }

    if (!in_array($table, ['managed_offices', 'office_spaces', 'furnished_offices', 'unfurnished_offices'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid listing type']));
    }

    $isFurnished = in_array($listingType, ['furnished', 'unfurnished'], true);

    $validator = new Validator($_POST);
    if (!$validator->validate([
        'title'            => 'required|max:255',
        'listing_type'     => 'required|in:managed,commercial,furnished,unfurnished',
        'city'             => 'required|max:100',
        'area'             => 'max:100',
        'address'          => 'max:1000',
        'price'            => 'numeric|min:0',
        'price_label'      => 'max:120',
        'total_seats'      => 'integer|min:0',
        'total_area_sqft'  => 'integer|min:0',
        'status'           => 'required|in:draft,published,archived',
        'office_space_type'=> 'required|in:rent,lease',
        'featured'         => 'in:0,1',
        'seo_text'         => 'max:2000',
    ])) {
        http_response_code(400);
        die(json_encode(['error' => $validator->firstError()]));
    }

    $slug = slugify($title);
    $checkStmt = mysqli_prepare($conn, "SELECT id FROM $table WHERE slug = ?");
    if ($action === 'update') {
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM $table WHERE slug = ? AND id != ?");
        mysqli_stmt_bind_param($checkStmt, 'si', $slug, $id);
    } else {
        mysqli_stmt_bind_param($checkStmt, 's', $slug);
    }
    mysqli_stmt_execute($checkStmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($checkStmt)) > 0) {
        $slug .= '-' . uniqid();
    }
    mysqli_stmt_close($checkStmt);

    $amenities = [];
    if (!empty($_POST['amenities'])) {
        if (is_array($_POST['amenities'])) {
            $amenities = $_POST['amenities'];
        } else {
            $amenities = array_map('trim', explode(',', $_POST['amenities']));
        }
    }
    $amenitiesJson = json_encode($amenities);

    $availableSqft = $isFurnished && isset($_POST['available_sqft']) && $_POST['available_sqft'] !== '' ? (int)$_POST['available_sqft'] : null;
    $minInventory = $isFurnished && isset($_POST['min_inventory']) && $_POST['min_inventory'] !== '' ? (int)$_POST['min_inventory'] : null;
    $inventoryType = $isFurnished ? trim($_POST['inventory_type'] ?? 'seats') : null;

    $imagePaths = [];
    $existingImages = json_decode($_POST['existing_images'] ?? '[]', true);

    if (!empty($_FILES['images'])) {
        $uploadDir = __DIR__ . '/../../uploads/listings/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $files = $_FILES['images'];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!validate_image_upload($files['tmp_name'][$i], $files['name'][$i], $ALLOWED_IMAGE_EXTS)) continue;
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $filename = uniqid('listing_') . '.' . $ext;
            move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename);
            $imagePaths[] = '/uploads/listings/' . $filename;
        }
    }

    $allImages = array_merge($existingImages, $imagePaths);
    $imagesJson = !empty($allImages) ? json_encode($allImages) : null;

    // Auto-generate listing code
    if ($action === 'create') {
        $prefixMap = ['managed' => 'MFO', 'commercial' => 'FUO', 'furnished' => 'FUR', 'unfurnished' => 'UNF'];
        $prefix = $prefixMap[$listingType] ?? 'FUO';
        $maxRes = mysqli_query($conn, "SELECT MAX(listing_code) as max_code FROM $table WHERE listing_code LIKE '$prefix%'");
        $maxRow = mysqli_fetch_assoc($maxRes);
        $nextNum = 1;
        if ($maxRow && $maxRow['max_code']) {
            $num = (int)substr($maxRow['max_code'], 3);
            $nextNum = $num + 1;
        }
        $listingCode = $prefix . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
    } else {
        $listingCode = $_POST['listing_code'] ?? null;
    }

    if ($action === 'create') {
        mysqli_begin_transaction($conn);
        try {
            if ($isFurnished) {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO $table (title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, available_sqft, min_inventory, inventory_type, amenities, images, status, featured, office_space_type, listing_type, listing_code)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, 'ssssssddsssiiiisssissss',
                    $title, $slug, $description, $city, $area, $address,
                    $latitude, $longitude,
                    $price, $priceLabel, $totalSeats, $totalAreaSqft, $availableSqft, $minInventory, $inventoryType,
                    $amenitiesJson, $imagesJson,
                    $status, $featured, $officeSpaceType,
                    $listingType, $listingCode
                );
            } else {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO $table (title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, amenities, images, status, featured, feature_highlights, seo_text, office_space_type, listing_type, listing_code)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, 'ssssssddssiisssissss',
                    $title, $slug, $description, $city, $area, $address,
                    $latitude, $longitude,
                    $price, $priceLabel, $totalSeats, $totalAreaSqft, $amenitiesJson, $imagesJson,
                    $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                    $listingType, $listingCode
                );
            }
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to insert listing');
            }
            $newId = mysqli_insert_id($conn);
            log_activity($conn, 'create', $table, $newId, ['title' => $title, 'type' => $listingType]);
            publish_event('listing_created', $listingType, $newId, $title);
            JsonCache::incrementGlobalVersion();
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Listing created successfully', 'id' => $newId]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            // Clean up uploaded images on failure
            foreach ($imagePaths as $img) {
                $filePath = __DIR__ . '/../..' . $img;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create listing: ' . $e->getMessage()]);
        }
    } else {
        mysqli_begin_transaction($conn);
        try {
            if ($isFurnished) {
                $stmt = mysqli_prepare($conn,
                    "UPDATE $table SET title=?, slug=?, description=?, city=?, area=?, address=?, latitude=?, longitude=?, price=?, price_label=?, total_seats=?, total_area_sqft=?, available_sqft=?, min_inventory=?, inventory_type=?, amenities=?, images=?, status=?, featured=?, office_space_type=?, listing_type=? WHERE id=?"
                );
                mysqli_stmt_bind_param($stmt, 'ssssssddsssiiiisssissi',
                    $title, $slug, $description, $city, $area, $address,
                    $latitude, $longitude,
                    $price, $priceLabel, $totalSeats, $totalAreaSqft, $availableSqft, $minInventory, $inventoryType,
                    $amenitiesJson, $imagesJson,
                    $status, $featured, $officeSpaceType,
                    $listingType, $id
                );
            } else {
                $stmt = mysqli_prepare($conn,
                    "UPDATE $table SET title=?, slug=?, description=?, city=?, area=?, address=?, latitude=?, longitude=?, price=?, price_label=?, total_seats=?, total_area_sqft=?, amenities=?, images=?, status=?, featured=?, feature_highlights=?, seo_text=?, office_space_type=?, listing_type=? WHERE id=?"
                );
                mysqli_stmt_bind_param($stmt, 'ssssssddssiisssissssi',
                    $title, $slug, $description, $city, $area, $address,
                    $latitude, $longitude,
                    $price, $priceLabel, $totalSeats, $totalAreaSqft, $amenitiesJson, $imagesJson,
                    $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                    $listingType, $id
                );
            }
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update listing');
            }
            log_activity($conn, 'update', $table, $id, ['title' => $title, 'type' => $listingType]);
            publish_event('listing_updated', $listingType, $id, $title);
            JsonCache::incrementGlobalVersion();
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Listing updated successfully']);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            // Clean up newly uploaded images on failure
            foreach ($imagePaths as $img) {
                $filePath = __DIR__ . '/../..' . $img;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update listing: ' . $e->getMessage()]);
        }
    }
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $listingType = trim($_POST['listing_type'] ?? '');
    $table = get_listing_table($listingType);
    if (!$table) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid listing type']));
    }

    $stmt = mysqli_prepare($conn, "SELECT title, images FROM $table WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if (!$row) {
        http_response_code(404);
        die(json_encode(['error' => 'Listing not found']));
    }

    $images = json_decode($row['images'] ?? '[]', true);
    foreach ($images as $img) {
        $filePath = __DIR__ . '/../..' . $img;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM $table WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'delete', $table, $id, ['title' => $row['title']]);
        publish_event('listing_deleted', $listingType, $id, $row['title']);
        JsonCache::incrementGlobalVersion();
        echo json_encode(['success' => true, 'message' => 'Listing deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete listing']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);

}
