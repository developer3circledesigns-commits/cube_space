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
    'leasing_options' => $leasingItems,
];

$cache->set($cacheKey, $response);

echo json_encode($response);
