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
// Fetch Office (single UNION query instead of 4 sequential queries)
// ============================================================
$listingTypeDb = '';
$typeParam = isset($_GET['type']) ? trim($_GET['type']) : '';

$office = null;
if ($typeParam && in_array($typeParam, ['managed', 'furnished', 'unfurnished'])) {
    $tableMap = ['managed' => 'managed_offices', 'furnished' => 'furnished_offices', 'unfurnished' => 'unfurnished_offices'];
    $table = $tableMap[$typeParam];
    $stmt = mysqli_prepare($conn, "SELECT *, ? as listing_type_db FROM $table WHERE slug = ? AND status = 'active'");
    if (!$stmt) { http_response_code(500); echo json_encode(['error' => 'Database error']); exit; }
    mysqli_stmt_bind_param($stmt, 'ss', $typeParam, $slug);
    mysqli_stmt_execute($stmt);
    $office = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

if (!$office) {
    $unionSql = "(SELECT *, 'managed' as listing_type_db FROM managed_offices WHERE slug = ? AND status = 'active')
                 UNION ALL
                 (SELECT *, 'furnished' as listing_type_db FROM furnished_offices WHERE slug = ? AND status = 'active')
                 UNION ALL
                 (SELECT *, 'unfurnished' as listing_type_db FROM unfurnished_offices WHERE slug = ? AND status = 'active')
                 LIMIT 1";
    $stmt = mysqli_prepare($conn, $unionSql);
    if (!$stmt) { http_response_code(500); echo json_encode(['error' => 'Database error']); exit; }
    mysqli_stmt_bind_param($stmt, 'sss', $slug, $slug, $slug);
    mysqli_stmt_execute($stmt);
    $office = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

if ($office) {
    $listingTypeDb = $office['listing_type_db'];
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

// Filter out empty and non-existent images
if (is_array($office['images'] ?? null)) {
    $projectRoot = realpath(__DIR__ . '/..');
    $office['images'] = array_values(array_filter($office['images'], function($image) use ($projectRoot) {
        if (!is_string($image) || trim($image) === '') {
            return false;
        }
        $host = parse_url($image, PHP_URL_HOST);
        $scheme = parse_url($image, PHP_URL_SCHEME);
        if ($host || $scheme) {
            return true;
        }
        $path = parse_url($image, PHP_URL_PATH);
        if (!$path) {
            return false;
        }
        if ($path[0] !== '/') {
            return file_exists($projectRoot . '/' . $path);
        }
        return file_exists($projectRoot . $path);
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
