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
admin_require_lib('validator.php');
admin_require_lib('cache.php');
admin_require_lib('events.php');
cubespace_require_project('src/autoload.php');
cubespace_require_project('lib/image_helper.php');

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

function ensure_listing_images_table($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `listing_images` (
        `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `listing_type` varchar(50) NOT NULL,
        `listing_id` int NOT NULL,
        `image_data` longblob NOT NULL,
        `image_mime` varchar(50) NOT NULL DEFAULT 'image/jpeg',
        `sort_order` int NOT NULL DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_listing` (`listing_type`, `listing_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function store_uploaded_images($conn, $listingType, $listingId, $files, $allowedExts) {
    ensure_listing_images_table($conn);
    $urls = [];
    if (empty($files['name'])) return $urls;
    $count = count($files['name']);
    $order = 0;
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp = $files['tmp_name'][$i];
        if (!validate_image_upload($tmp, $files['name'][$i], $allowedExts)) continue;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        $data = file_get_contents($tmp);
        $s = mysqli_prepare($conn, "INSERT INTO listing_images (listing_type, listing_id, image_data, image_mime, sort_order) VALUES (?, ?, ?, ?, ?)");
        if (!$s) throw new Exception('DB prepare failed for listing_images: ' . mysqli_error($conn));
        mysqli_stmt_bind_param($s, 'sissi', $listingType, $listingId, $data, $mime, $order);
        mysqli_stmt_execute($s);
        $imageId = (int)mysqli_insert_id($conn);
        // Write to uploads folder (primary) and keep DB as fallback
        $webPath = cubespace_write_image_file($data, $mime, $listingType, $listingId, $imageId);
        if ($webPath && file_exists(cubespace_filesystem_path_from_web($webPath))) {
            $urls[] = $webPath;
        } else {
            $urls[] = '/serve_image.php?id=' . $imageId;
        }
        $order++;
    }
    return $urls;
}

function delete_listing_images($conn, $listingType, $listingId) {
    ensure_listing_images_table($conn);
    // Delete associated files first
    $stmtFiles = mysqli_prepare($conn, "SELECT id FROM listing_images WHERE listing_type = ? AND listing_id = ?");
    if ($stmtFiles) {
        mysqli_stmt_bind_param($stmtFiles, 'si', $listingType, $listingId);
        mysqli_stmt_execute($stmtFiles);
        $res = mysqli_stmt_get_result($stmtFiles);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                cubespace_delete_files_for_image_id($listingType, $listingId, (int)$r['id']);
            }
        }
        mysqli_stmt_close($stmtFiles);
    }
    $s = mysqli_prepare($conn, "DELETE FROM listing_images WHERE listing_type = ? AND listing_id = ?");
    if (!$s) return;
    mysqli_stmt_bind_param($s, 'si', $listingType, $listingId);
    mysqli_stmt_execute($s);
}

function parse_db_image_id($url) {
    if (str_contains($url, 'serve_image.php')) {
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        return (int)($params['id'] ?? 0);
    }
    return 0;
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
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? trim($_POST['price']) : null;
    
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
    $billableSeats = isset($_POST['billable_seats']) && $_POST['billable_seats'] !== '' ? (int)$_POST['billable_seats'] : 0;
    $remarks = trim($_POST['remarks'] ?? '');
    $totalAreaSqft = isset($_POST['total_area_sqft']) && $_POST['total_area_sqft'] !== '' ? (int)$_POST['total_area_sqft'] : 0;
    $status = trim($_POST['status'] ?? 'inactive');
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
    $hasBillableSeats = $table === 'managed_offices';

    $inventoryType = trim($_POST['inventory_type'] ?? '');
    if ($inventoryType !== '' && is_numeric($inventoryType)) {
        http_response_code(400);
        die(json_encode(['error' => 'Current Status must be a text value, cannot be a number']));
    }

    $validator = new Validator($_POST);
    if (!$validator->validate([
        'title'            => 'required|max:255',
        'listing_type'     => 'required|in:managed,commercial,furnished,unfurnished',
        'city'             => 'required|max:100',
        'area'             => 'max:100',
        'address'          => 'max:1000',
        'price_label'      => 'max:120',
        'total_seats'      => 'integer|min:0',
        'billable_seats'   => 'integer|min:0',
        'remarks'          => 'max:5000',
        'total_area_sqft'  => 'integer|min:0',
        'status'           => 'required|in:inactive,active,archived',
        'inventory_type'   => 'max:255',
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
            $raw = trim($_POST['amenities']);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $amenities = $decoded;
            } else {
                $amenities = array_map('trim', explode(',', $raw));
            }
        }
    }
    $amenitiesJson = json_encode($amenities);

    $existingImages = json_decode($_POST['existing_images'] ?? '[]', true);
    $existingImages = array_values(array_filter($existingImages, function($image) {
        if (!is_string($image) || trim($image) === '') return false;
        $host = parse_url($image, PHP_URL_HOST);
        $scheme = parse_url($image, PHP_URL_SCHEME);
        if ($host || $scheme) return true;
        $path = parse_url($image, PHP_URL_PATH);
        if (!$path) return false;
        if (str_contains($path, 'serve_image.php')) return true;
        if (str_starts_with($path, '/uploads/listings/') && preg_match('/_\d+\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $path)) return true;
        $filePath = __DIR__ . '/../..' . $path;
        return file_exists($filePath);
    }));

    $newImagePaths = [];

    // Auto-generate listing code
    if ($action === 'create') {
        $prefix = match($listingType) {
            'managed'     => 'MO',
            'commercial'  => 'OSP',
            'furnished'   => 'FO',
            'unfurnished' => 'UFU',
            default       => 'LST',
        };
        $prefixLen = strlen($prefix);
        $maxRes = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(listing_code, $prefixLen + 1) AS UNSIGNED)) as max_num FROM $table WHERE listing_code LIKE '{$prefix}%'");
        if (!$maxRes) {
            throw new Exception('Failed to generate listing code: ' . mysqli_error($conn));
        }
        $maxRow = mysqli_fetch_assoc($maxRes);
        $nextNum = (int)($maxRow['max_num'] ?? 0) + 1;
        $listingCode = $prefix . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
    } else {
        $listingCode = $_POST['listing_code'] ?? null;
    }

    if ($action === 'create') {
        mysqli_begin_transaction($conn);
        try {
            $initImagesJson = !empty($existingImages) ? json_encode($existingImages) : null;

            if ($isFurnished) {
                $availableSqft = trim($_POST['available_sqft'] ?? '');
                $minInventory = trim($_POST['min_inventory'] ?? '');
                $inventoryType = trim($_POST['inventory_type'] ?? '');
                $remarks = trim($_POST['remarks'] ?? '');
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO $table (title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, available_sqft, min_inventory, inventory_type, remarks, amenities, images, status, featured, feature_highlights, seo_text, office_space_type, listing_type, listing_code)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if (!$stmt) {
                    throw new Exception('Column mismatch in furnished_offices table: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, 'ssssssddsssssssssssssssss',
                    $title, $slug, $description, $city, $area, $address,
                    $latitude, $longitude,
                    $price, $priceLabel, $totalSeats, $totalAreaSqft,
                    $availableSqft, $minInventory, $inventoryType,
                    $remarks,
                    $amenitiesJson, $initImagesJson,
                    $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                    $listingType, $listingCode
                );
            } else {
                $minInventory = trim($_POST['min_inventory'] ?? '');
                $inventoryType = trim($_POST['inventory_type'] ?? '');
                if ($hasBillableSeats) {
                    $stmt = mysqli_prepare($conn,
                        "INSERT INTO $table (title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, billable_seats, remarks, min_inventory, inventory_type, amenities, images, status, featured, feature_highlights, seo_text, office_space_type, listing_type, listing_code)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    if (!$stmt) {
                        throw new Exception('Column mismatch in ' . $table . ' table: ' . mysqli_error($conn));
                    }
                    mysqli_stmt_bind_param($stmt, 'ssssssddssssissssssssssss',
                        $title, $slug, $description, $city, $area, $address,
                        $latitude, $longitude,
                        $price, $priceLabel, $totalSeats, $totalAreaSqft,
                        $billableSeats, $remarks, $minInventory, $inventoryType,
                        $amenitiesJson, $initImagesJson,
                        $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                        $listingType, $listingCode
                    );
                } else {
                    $stmt = mysqli_prepare($conn,
                        "INSERT INTO $table (title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, min_inventory, inventory_type, amenities, images, status, featured, feature_highlights, seo_text, office_space_type, listing_type, listing_code)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    if (!$stmt) {
                        throw new Exception('Column mismatch in ' . $table . ' table: ' . mysqli_error($conn));
                    }
                    mysqli_stmt_bind_param($stmt, 'ssssssddsssssssssisssss',
                        $title, $slug, $description, $city, $area, $address,
                        $latitude, $longitude,
                        $price, $priceLabel, $totalSeats, $totalAreaSqft,
                        $minInventory, $inventoryType,
                        $amenitiesJson, $initImagesJson,
                        $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                        $listingType, $listingCode
                    );
                }
            }
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to insert listing: ' . mysqli_stmt_error($stmt));
            }
            $newId = mysqli_insert_id($conn);

            $uploaded = [];
            if (!empty($_FILES['images'])) {
                $uploaded = store_uploaded_images($conn, $listingType, $newId, $_FILES['images'], $ALLOWED_IMAGE_EXTS);
            }

            $allImages = array_merge($existingImages, $uploaded);
            // Materialize any DB-backed images to uploads folder (uploads first, DB fallback)
            $allImages = cubespace_materialize_images_array($conn, $listingType, $newId, $allImages);
            if (!empty($allImages)) {
                $upd = mysqli_prepare($conn, "UPDATE $table SET images = ? WHERE id = ?");
                $allJson = json_encode($allImages);
                mysqli_stmt_bind_param($upd, 'si', $allJson, $newId);
                mysqli_stmt_execute($upd);
            }

            log_activity($conn, 'create', $table, $newId, ['title' => $title, 'type' => $listingType]);
            publish_event('listing_created', $listingType, $newId, $title);
            try {
                $listingDetail = "Title: $title\nCode: $listingCode\nType: $listingType\nCity: $city\nArea: $area\nAddress: $address\nPrice: $price ($priceLabel)\nSeats: $totalSeats\nSqft: $totalAreaSqft\nStatus: $status\nFeatured: " . ($featured ? 'Yes' : 'No') . "\nSpace Type: $officeSpaceType\nRemarks: $remarks";
                (new \CubeSpace\EmailService())->notifyAdminAction('create', "$listingType listing #$newId", $listingDetail);
            } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
            JsonCache::incrementGlobalVersion();
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Listing created successfully', 'id' => $newId]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create listing: ' . $e->getMessage()]);
        }
    } else {
        mysqli_begin_transaction($conn);
        try {
            $uploaded = [];
            if (!empty($_FILES['images'])) {
                $uploaded = store_uploaded_images($conn, $listingType, $id, $_FILES['images'], $ALLOWED_IMAGE_EXTS);
            }

            $oldStmt = mysqli_prepare($conn, "SELECT images FROM $table WHERE id = ?");
            if (!$oldStmt) throw new Exception('DB prepare failed: ' . mysqli_error($conn));
            mysqli_stmt_bind_param($oldStmt, 'i', $id);
            mysqli_stmt_execute($oldStmt);
            $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));
            $oldImages = json_decode($oldRow['images'] ?? '[]', true);
            if (!is_array($oldImages)) $oldImages = [];
            $removedImages = array_diff($oldImages, $existingImages);
            foreach ($removedImages as $rimg) {
                $dbId = cubespace_parse_db_image_id($rimg);
                if ($dbId) {
                    $dStmt = mysqli_prepare($conn, "DELETE FROM listing_images WHERE id = ?");
                    if ($dStmt) {
                        mysqli_stmt_bind_param($dStmt, 'i', $dbId);
                        mysqli_stmt_execute($dStmt);
                    }
                    cubespace_delete_files_for_image_id($listingType, $id, $dbId);
                } else {
                    // File-path image removed — delete file if it matches our pattern
                    if (is_string($rimg) && str_starts_with($rimg, '/uploads/listings/')) {
                        cubespace_delete_image_file_by_webpath($rimg);
                        $fid = cubespace_extract_db_id_from_upload_path($rimg);
                        if ($fid > 0) {
                            $dStmt = mysqli_prepare($conn, "DELETE FROM listing_images WHERE id = ?");
                            if ($dStmt) {
                                mysqli_stmt_bind_param($dStmt, 'i', $fid);
                                mysqli_stmt_execute($dStmt);
                            }
                        }
                    }
                }
            }

            $allImages = array_merge($existingImages, $uploaded);
            // Ensure all existing DB images are also materialized to uploads folder (idempotent)
            $allImages = cubespace_materialize_images_array($conn, $listingType, $id, $allImages);
            $imagesJson = !empty($allImages) ? json_encode($allImages) : null;

            if ($isFurnished) {
                $availableSqft = trim($_POST['available_sqft'] ?? '');
                $minInventory = trim($_POST['min_inventory'] ?? '');
                $inventoryType = trim($_POST['inventory_type'] ?? '');
                $remarks = trim($_POST['remarks'] ?? '');
                $stmt = mysqli_prepare($conn,
                    "UPDATE $table SET title=?, slug=?, description=?, city=?, area=?, address=?, latitude=?, longitude=?, price=?, price_label=?, total_seats=?, total_area_sqft=?, available_sqft=?, min_inventory=?, inventory_type=?, remarks=?, amenities=?, images=?, status=?, featured=?, feature_highlights=?, seo_text=?, office_space_type=?, listing_type=?, updated_at=NOW() WHERE id=?"
                );
                if (!$stmt) {
                    throw new Exception('Update prepare failed for ' . $table . ': ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, 'ssssssddsssisssssssssssii',
                    $title, $slug, $description, $city, $area, $address,
                    $latitude, $longitude,
                    $price, $priceLabel, $totalSeats, $totalAreaSqft,
                    $availableSqft, $minInventory, $inventoryType,
                    $remarks,
                    $amenitiesJson, $imagesJson,
                    $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                    $listingType, $id
                );
            } else {
                $minInventory = trim($_POST['min_inventory'] ?? '');
                $inventoryType = trim($_POST['inventory_type'] ?? '');
                if ($hasBillableSeats) {
                    $stmt = mysqli_prepare($conn,
                        "UPDATE $table SET title=?, slug=?, description=?, city=?, area=?, address=?, latitude=?, longitude=?, price=?, price_label=?, total_seats=?, total_area_sqft=?, billable_seats=?, remarks=?, min_inventory=?, inventory_type=?, amenities=?, images=?, status=?, featured=?, feature_highlights=?, seo_text=?, office_space_type=?, listing_type=?, updated_at=NOW() WHERE id=?"
                    );
                    if (!$stmt) {
                        throw new Exception('Update prepare failed for managed_offices: ' . mysqli_error($conn));
                    }
                    mysqli_stmt_bind_param($stmt, 'ssssssddssssisssssssssssi',
                        $title, $slug, $description, $city, $area, $address,
                        $latitude, $longitude,
                        $price, $priceLabel, $totalSeats, $totalAreaSqft,
                        $billableSeats, $remarks, $minInventory, $inventoryType,
                        $amenitiesJson, $imagesJson,
                        $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                        $listingType, $id
                    );
                } else {
                    $stmt = mysqli_prepare($conn,
                        "UPDATE $table SET title=?, slug=?, description=?, city=?, area=?, address=?, latitude=?, longitude=?, price=?, price_label=?, total_seats=?, total_area_sqft=?, min_inventory=?, inventory_type=?, amenities=?, images=?, status=?, featured=?, feature_highlights=?, seo_text=?, office_space_type=?, listing_type=?, updated_at=NOW() WHERE id=?"
                    );
                    if (!$stmt) {
                        throw new Exception('Update prepare failed for ' . $table . ': ' . mysqli_error($conn));
                    }
                    mysqli_stmt_bind_param($stmt, 'ssssssddsssssssssissssi',
                        $title, $slug, $description, $city, $area, $address,
                        $latitude, $longitude,
                        $price, $priceLabel, $totalSeats, $totalAreaSqft,
                        $minInventory, $inventoryType,
                        $amenitiesJson, $imagesJson,
                        $status, $featured, $featureHighlightsJson, $seoText, $officeSpaceType,
                        $listingType, $id
                    );
                }
            }
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update listing: ' . mysqli_stmt_error($stmt));
            }
            log_activity($conn, 'update', $table, $id, ['title' => $title, 'type' => $listingType]);
            publish_event('listing_updated', $listingType, $id, $title);
            try {
                $listingDetail = "Title: $title\nCode: $listingCode\nType: $listingType\nCity: $city\nArea: $area\nAddress: $address\nPrice: $price ($priceLabel)\nSeats: $totalSeats\nSqft: $totalAreaSqft\nStatus: $status\nFeatured: " . ($featured ? 'Yes' : 'No') . "\nSpace Type: $officeSpaceType\nRemarks: $remarks";
                (new \CubeSpace\EmailService())->notifyAdminAction('update', "$listingType listing #$id", $listingDetail);
            } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
            JsonCache::incrementGlobalVersion();
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Listing updated successfully']);
        } catch (Exception $e) {
            mysqli_rollback($conn);
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
    $areaFilter = trim($_GET['area'] ?? '');
    $sqftFilter = trim($_GET['sqft'] ?? '');
    $seatsFilter = trim($_GET['seats'] ?? '');
    $featuredFilter = trim($_GET['featured'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $conditions = [];
    $params = [];
    $types = '';

    if ($statusFilter && in_array($statusFilter, ['inactive','active','archived'])) {
        $conditions[] = "status = ?"; $params[] = $statusFilter; $types .= 's';
    }
    if ($cityFilter) {
        $conditions[] = "city = ?"; $params[] = $cityFilter; $types .= 's';
    }
    if ($areaFilter) {
        $conditions[] = "area = ?"; $params[] = $areaFilter; $types .= 's';
    }
    if ($sqftFilter && in_array($sqftFilter, ['1000-5000','5000-10000','10000-20000','20000-'])) {
        $conditions[] = "available_sqft = ?"; $params[] = $sqftFilter; $types .= 's';
    }
    if ($seatsFilter && in_array($seatsFilter, ['50','100','200','500'])) {
        $conditions[] = "total_seats = ?"; $params[] = (int)$seatsFilter; $types .= 'i';
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

    // Delete any file-path images stored in JSON (cover legacy /uploads files)
    $imgs = json_decode($row['images'] ?? '[]', true);
    if (is_array($imgs)) {
        foreach ($imgs as $img) {
            if (is_string($img) && str_starts_with($img, '/uploads/listings/')) {
                cubespace_delete_image_file_by_webpath($img);
            }
        }
    }
    delete_listing_images($conn, $listingType, $id);

    $stmt = mysqli_prepare($conn, "DELETE FROM $table WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        log_activity($conn, 'delete', $table, $id, ['title' => $row['title']]);
        publish_event('listing_deleted', $listingType, $id, $row['title']);
        try { (new \CubeSpace\EmailService())->notifyAdminAction('delete', "$listingType listing #$id", "Title: {$row['title']}"); } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
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
