<?php
require_once __DIR__ . '/db_config.php';
cubespace_require_project('lib/cors.php');
cubespace_require_project('lib/cache.php');
cubespace_require_project('lib/ratelimit.php');

set_cors_headers('GET, OPTIONS');
header('Content-Type: application/json');
header('Cache-Control: public, max-age=60');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60) . ' GMT');

$rateLimiter = new RateLimiter(30, 60, 'od_api_');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!$rateLimiter->check($clientIp)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Try again in a minute.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$cache = new JsonCache(100, 600);

// ============================================================
// Input Validation
// ============================================================
$slug = trim($_GET['slug'] ?? '');

if ($slug === '' || strlen($slug) > 255 || !preg_match('/^[a-zA-Z0-9\-_]+$/', $slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing slug parameter']);
    exit;
}

// ============================================================
// Check Cache
// ============================================================
$cacheKey = 'office_detail_v2_' . JsonCache::getGlobalVersion() . '_' . md5($slug);
$cached = $cache->get($cacheKey);
if ($cached) {
    echo json_encode($cached);
    exit;
}

// ============================================================
// Fetch Office
// ============================================================
$listingTypeDb = '';

$stmt = mysqli_prepare($conn, "SELECT * FROM managed_offices WHERE slug = ? AND status = 'published'");
if (!$stmt) { http_response_code(500); echo json_encode(['error' => 'Database error']); exit; }
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$office = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if ($office) { $listingTypeDb = 'managed'; }

if (!$office) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM furnished_offices WHERE slug = ? AND status = 'published'");
    if (!$stmt) { http_response_code(500); echo json_encode(['error' => 'Database error']); exit; }
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $office = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($office) { $listingTypeDb = 'furnished'; }
}

if (!$office) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM unfurnished_offices WHERE slug = ? AND status = 'published'");
    if (!$stmt) { http_response_code(500); echo json_encode(['error' => 'Database error']); exit; }
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $office = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($office) { $listingTypeDb = 'unfurnished'; }
}

if (!$office) {
    http_response_code(404);
    echo json_encode(['error' => 'Office not found']);
    exit;
}

$office['listing_type_db'] = $listingTypeDb;

$officeId = (int)$office['id'];

// Decode JSON fields
foreach (['amenities', 'images', 'feature_highlights'] as $field) {
    if (isset($office[$field])) {
        $office[$field] = json_decode($office[$field], true);
    }
}

// Filter out non-existent local images
if (is_array($office['images'] ?? null)) {
    $office['images'] = array_values(array_filter($office['images'], function($image) {
        if (!is_string($image) || trim($image) === '') return false;
        if (parse_url($image, PHP_URL_HOST) || parse_url($image, PHP_URL_SCHEME)) return true;
        $path = parse_url($image, PHP_URL_PATH);
        if (!$path) return true;
        if ($path[0] !== '/') $path = '/' . $path;
        return file_exists(__DIR__ . '/..' . $path);
    }));
}

// ============================================================
// Fetch Approved Reviews
// ============================================================
$reviewsStmt = mysqli_prepare(
    $conn,
    "SELECT id, reviewer_name, rating, review_text, created_at
     FROM office_reviews
     WHERE office_id = ? AND status = 'approved'
     ORDER BY created_at DESC"
);
mysqli_stmt_bind_param($reviewsStmt, 'i', $officeId);
mysqli_stmt_execute($reviewsStmt);
$reviewsResult = mysqli_stmt_get_result($reviewsStmt);

$reviewItems = [];
$totalRating = 0;
$reviewCount = 0;
$distribution = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];

while ($row = mysqli_fetch_assoc($reviewsResult)) {
    $rating = max(1, min(5, (int)$row['rating']));
    $totalRating += $rating;
    $reviewCount++;
    $distribution[(string)$rating]++;
    $reviewItems[] = $row;
}
mysqli_stmt_close($reviewsStmt);

$reviewsData = [
    'average_rating' => $reviewCount > 0 ? round($totalRating / $reviewCount, 1) : 0,
    'total_reviews' => $reviewCount,
    'distribution' => $distribution,
    'items' => $reviewItems,
];

// ============================================================
// Fetch Active FAQ
// ============================================================
$faqStmt = mysqli_prepare(
    $conn,
    "SELECT id, question, answer, sort_order
     FROM office_faq
     WHERE office_id = ? AND is_active = 1
     ORDER BY sort_order ASC, id ASC"
);
mysqli_stmt_bind_param($faqStmt, 'i', $officeId);
mysqli_stmt_execute($faqStmt);
$faqResult = mysqli_stmt_get_result($faqStmt);

$faqItems = [];
while ($row = mysqli_fetch_assoc($faqResult)) {
    $faqItems[] = $row;
}
mysqli_stmt_close($faqStmt);

// ============================================================
// Fetch Building Details
// ============================================================
$buildingStmt = mysqli_prepare(
    $conn,
    "SELECT building_name, year_built, total_floors, floor_plate_area,
            elevators, parking, nearest_metro, nearest_railway, airport, bus_stop
     FROM office_building_details
     WHERE office_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($buildingStmt, 'i', $officeId);
mysqli_stmt_execute($buildingStmt);
$buildingResult = mysqli_stmt_get_result($buildingStmt);
$building = mysqli_fetch_assoc($buildingResult);
mysqli_stmt_close($buildingStmt);

// ============================================================
// Fetch Active Leasing Options
// ============================================================
$leasingStmt = mysqli_prepare(
    $conn,
    "SELECT id, option_title, option_desc, option_price, option_image, sort_order
     FROM office_leasing_options
     WHERE office_id = ? AND is_active = 1
     ORDER BY sort_order ASC, id ASC"
);
mysqli_stmt_bind_param($leasingStmt, 'i', $officeId);
mysqli_stmt_execute($leasingStmt);
$leasingResult = mysqli_stmt_get_result($leasingStmt);

$leasingItems = [];
while ($row = mysqli_fetch_assoc($leasingResult)) {
    $leasingItems[] = $row;
}
mysqli_stmt_close($leasingStmt);

// ============================================================
// Build & Cache Response
// ============================================================
$response = [
    'office' => $office,
    'reviews' => $reviewsData,
    'faq' => $faqItems,
    'building' => $building,
    'leasing_options' => $leasingItems,
];

$cache->set($cacheKey, $response);

echo json_encode($response);
