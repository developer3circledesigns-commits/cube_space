<?php
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');

try {
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../jwt_helper.php';
admin_require_lib('csrf.php');
admin_require_lib('config.php');
admin_require_lib('validator.php');
admin_require_lib('cache.php');
admin_require_lib('events.php');

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
    CSRFManager::require();
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
            $featureHighlights = array_map('trim', explode("\n", str_replace("\r\n", "\n", $_POST['feature_highlights'])));
        }
        $featureHighlights = array_filter($featureHighlights, fn($v) => $v !== '');
        $featureHighlights = array_values($featureHighlights);
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

    $isFurnished = in_array($table, ['furnished_offices', 'unfurnished_offices']);

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

    $imagePaths = [];
    $existingImages = json_decode($_POST['existing_images'] ?? '[]', true);

    if (!empty($_FILES['images'])) {
        $uploadDir = admin_uploads_dir();
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

    // Handle image deletion: compare submitted existing_images with stored images on update
    if ($action === 'update' && $id) {
        $oldStmt = mysqli_prepare($conn, "SELECT images FROM $table WHERE id = ?");
        mysqli_stmt_bind_param($oldStmt, 'i', $id);
        mysqli_stmt_execute($oldStmt);
        $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));
        $oldImages = json_decode($oldRow['images'] ?? '[]', true);
        $removedImages = array_diff($oldImages, $existingImages);
        foreach ($removedImages as $rimg) {
            $filePath = __DIR__ . '/../..' . $rimg;
            if (file_exists($filePath)) unlink($filePath);
        }
    }

    $allImages = array_merge($existingImages, $imagePaths);
    $imagesJson = !empty($allImages) ? json_encode($allImages) : null;

    // Auto-generate listing code
    if ($action === 'create') {
        $prefix = match($listingType) {
            'managed'     => 'MFO',
            'commercial'  => 'OSP',
            'furnished'   => 'FUO',
            'unfurnished' => 'UFU',
            default       => 'LST',
        };
        $maxRes = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(listing_code, 4) AS UNSIGNED)) as max_num FROM $table WHERE listing_code LIKE '{$prefix}%'");
        $maxRow = mysqli_fetch_assoc($maxRes);
        $nextNum = (int)($maxRow['max_num'] ?? 0) + 1;
        $listingCode = $prefix . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
    } else {
        $listingCode = $_POST['listing_code'] ?? null;
    }

    if ($action === 'create') {
        mysqli_begin_transaction($conn);
        try {
            if ($isFurnished) {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO $table (title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, available_sqft, min_inventory, inventory_type, amenities, images, status, featured, feature_highlights, seo_text, office_space_type, listing_type, listing_code)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $availableSqft = trim($_POST['available_sqft'] ?? '');
                $minInventory = trim($_POST['min_inventory'] ?? '');
                $inventoryType = trim($_POST['inventory_type'] ?? '');
                mysqli_stmt_bind_param($stmt, 'ssssssddsssisssssssissss',
                    $title, $slug, $description, $city, $area, $address,
                    $latitude, $longitude,
                    $price, $priceLabel, $totalSeats, $totalAreaSqft,
                    $availableSqft, $minInventory, $inventoryType,
                    $amenitiesJson, $imagesJson,
                    $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                    $listingType, $listingCode
                );
            } else {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO $table (title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, amenities, images, status, featured, feature_highlights, seo_text, office_space_type, listing_type, listing_code)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, 'ssssssddsssisssisssss',
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
                $filePath = admin_resolve_upload_path($img);
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
                $availableSqft = trim($_POST['available_sqft'] ?? '');
                $minInventory = trim($_POST['min_inventory'] ?? '');
                $inventoryType = trim($_POST['inventory_type'] ?? '');
                $stmt = mysqli_prepare($conn,
                    "UPDATE $table SET title=?, slug=?, description=?, city=?, area=?, address=?, latitude=?, longitude=?, price=?, price_label=?, total_seats=?, total_area_sqft=?, available_sqft=?, min_inventory=?, inventory_type=?, amenities=?, images=?, status=?, featured=?, feature_highlights=?, seo_text=?, office_space_type=?, listing_type=?, updated_at=NOW() WHERE id=?"
                );
                mysqli_stmt_bind_param($stmt, 'ssssssddsssisssssssisssi',
                    $title, $slug, $description, $city, $area, $address,
                    $latitude, $longitude,
                    $price, $priceLabel, $totalSeats, $totalAreaSqft,
                    $availableSqft, $minInventory, $inventoryType,
                    $amenitiesJson, $imagesJson,
                    $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                    $listingType, $id
                );
            } else {
                $stmt = mysqli_prepare($conn,
                    "UPDATE $table SET title=?, slug=?, description=?, city=?, area=?, address=?, latitude=?, longitude=?, price=?, price_label=?, total_seats=?, total_area_sqft=?, amenities=?, images=?, status=?, featured=?, feature_highlights=?, seo_text=?, office_space_type=?, listing_type=?, updated_at=NOW() WHERE id=?"
                );
                mysqli_stmt_bind_param($stmt, 'ssssssddsssisssissssi',
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
                $filePath = admin_resolve_upload_path($img);
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

if ($action === 'export') {
    $listingType = trim($_GET['listing_type'] ?? '');
    $table = get_listing_table($listingType);

    $statusFilter = trim($_GET['status'] ?? '');
    $cityFilter = trim($_GET['city'] ?? '');
    $featuredFilter = trim($_GET['featured'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $conditions = [];
    $params = [];
    $types = '';

    if ($statusFilter && in_array($statusFilter, ['draft','published','archived'])) {
        $conditions[] = "status = ?"; $params[] = $statusFilter; $types .= 's';
    }
    if ($cityFilter) {
        $conditions[] = "city = ?"; $params[] = $cityFilter; $types .= 's';
    }
    if ($featuredFilter === 'yes') {
        $conditions[] = "featured = 1";
    } elseif ($featuredFilter === 'no') {
        $conditions[] = "featured = 0";
    }
    if ($search) {
        $conditions[] = "(title LIKE ? OR city LIKE ? OR area LIKE ? OR address LIKE ?)";
        $sp = "%$search%"; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        $types .= 'ssss';
    }
    $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

    if ($listingType === 'office-space') {
        $uniSql = "(SELECT *, 'furnished' as listing_type_db FROM furnished_offices $whereClause) UNION ALL (SELECT *, 'unfurnished' as listing_type_db FROM unfurnished_offices $whereClause) ORDER BY created_at DESC";
        $stmt = mysqli_prepare($conn, $uniSql);
        $allParams = array_merge($params, $params);
        $allTypes = $types . $types;
        if (!empty($allParams)) mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
    } else {
        if (!$table) { http_response_code(400); die(json_encode(['error' => 'Invalid listing type'])); }
        $stmt = mysqli_prepare($conn, "SELECT *, listing_type as listing_type_db FROM $table $whereClause ORDER BY created_at DESC");
        if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $listingType . '_offices_export_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Code','Title','Slug','City','Area','Address','Price','Price Label','Seats','Sq.ft','Space Type','Status','Featured','Latitude','Longitude','Furnishing','Listing Type','Created','Updated']);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($out, [
            $row['id'], $row['listing_code'], $row['title'], $row['slug'],
            $row['city'], $row['area'], $row['address'],
            $row['price'], $row['price_label'], $row['total_seats'],
            $row['total_area_sqft'], $row['office_space_type'], $row['status'],
            $row['featured'], $row['latitude'], $row['longitude'],
            $row['listing_type_db'] ?? $row['listing_type'],
            $row['listing_type'], $row['created_at'], $row['updated_at']
        ]);
    }
    fclose($out);
    exit;
}

if ($action === 'delete') {
    CSRFManager::require();
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
    log_app_error('listing_crud.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);

}
