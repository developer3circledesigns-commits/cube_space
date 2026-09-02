<?php
set_error_handler(function ($severity, $message, $file, $line) {
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return false;
    }
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
try {
require_once __DIR__ . '/db_config.php';
cubespace_require_project('lib/cors.php');
cubespace_require_project('lib/cache.php');
cubespace_require_project('lib/ratelimit.php');
cubespace_require_project('lib/geohash.php');
cubespace_require_project('lib/image_helper.php');

set_cors_headers('GET, OPTIONS');
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

$rateLimiter = new RateLimiter(30, 60, 'mgo_api_');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!$rateLimiter->check($clientIp)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Try again in a minute.']);
    exit;
}

function decode_existing_listing_images($imagesJson) {
    if ($imagesJson === null || $imagesJson === '') return [];
    $images = json_decode($imagesJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($images)) {
        error_log('[decode_images] json_error '.json_last_error_msg().' raw='.substr((string)$imagesJson,0,200));
        return [];
    }
    return array_values(array_filter($images, function($image) {
        return is_string($image) && trim($image) !== '';
    }));
}

function resolve_images_for_row($imagesJson, $listingType, $listingId, $conn) {
    // Prioritize uploads folder, fallback to DB (serve_image)
    if (function_exists('cubespace_resolve_display_images')) {
        $resolved = cubespace_resolve_display_images($conn, $listingType, (int)$listingId, $imagesJson ?? '[]');
        if (!empty($resolved)) return $resolved;
    }
    return decode_existing_listing_images($imagesJson);
}

// ============================================================
// DSA: Trie for Autocomplete
// ============================================================
class TrieNode {
    public $children = [];
    public $isEnd = false;
    public $data = [];
}

class Trie {
    private $root;

    public function __construct() {
        $this->root = new TrieNode();
    }

    public function insert($word, $payload = []) {
        $node = $this->root;
        $word = mb_strtolower(trim($word));
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($word, $i, 1);
            if (!isset($node->children[$ch])) {
                $node->children[$ch] = new TrieNode();
            }
            $node = $node->children[$ch];
        }
        $node->isEnd = true;
        if (!in_array($payload, $node->data)) {
            $node->data[] = $payload;
        }
    }

    public function search($prefix) {
        $node = $this->root;
        $prefix = mb_strtolower(trim($prefix));
        $len = mb_strlen($prefix);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($prefix, $i, 1);
            if (!isset($node->children[$ch])) return [];
            $node = $node->children[$ch];
        }
        $results = [];
        $this->collect($node, $results);
        return $results;
    }

    private function collect($node, &$results, $limit = 10) {
        if (count($results) >= $limit) return;
        if ($node->isEnd) {
            foreach ($node->data as $d) {
                if (count($results) >= $limit) break;
                $results[] = $d;
            }
        }
        foreach ($node->children as $child) {
            $this->collect($child, $results, $limit);
            if (count($results) >= $limit) break;
        }
    }
}

$cache = new JsonCache(100, 300);

// ============================================================
// Filter Engine
// ============================================================
class FilterEngine {
    private $filters = [];

    public function addFilter($name, $callback) {
        $this->filters[$name] = $callback;
    }

    public function apply($offices, $params) {
        $result = $offices;
        foreach ($this->filters as $name => $callback) {
            if (isset($params[$name]) && $params[$name] !== '' && $params[$name] !== null) {
                $result = $callback($result, $params[$name]);
            }
        }
        return $result;
    }
}

// ============================================================
// Main API Handler
// ============================================================
$action = $_GET['action'] ?? 'list';

if ($action === 'autocomplete') {
    $query = $_GET['q'] ?? '';
    if (strlen($query) < 1) { echo json_encode([]); exit; }

    $cacheKey = 'ac_managed_v3_' . JsonCache::getGlobalVersion() . '_' . md5($query);
    $cached = $cache->get($cacheKey);
    if ($cached) { echo json_encode($cached); exit; }

    $like = $query . '%';
    $stmt = mysqli_prepare($conn,
        "SELECT DISTINCT title, area, city FROM managed_offices
         WHERE status='active' AND (title LIKE ? OR area LIKE ? OR city LIKE ?)
         LIMIT 10"
    );
    mysqli_stmt_bind_param($stmt, 'sss', $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $results = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['title'] && stripos($row['title'], $query) === 0) {
            $results[] = ['type' => 'title', 'value' => $row['title']];
        }
        if ($row['area'] && stripos($row['area'], $query) === 0) {
            $results[] = ['type' => 'area', 'value' => $row['area']];
        }
        if ($row['city'] && stripos($row['city'], $query) === 0) {
            $results[] = ['type' => 'city', 'value' => $row['city']];
        }
    }
    mysqli_stmt_close($stmt);
    $results = array_slice(array_unique($results, SORT_REGULAR), 0, 10);

    $cache->set($cacheKey, $results);
    echo json_encode($results);
    exit;
}

if ($action === 'list') {
    try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');
    $listing_type = trim($_GET['listing_type'] ?? '');
    $city = trim($_GET['city'] ?? '');
    $area = trim($_GET['area'] ?? '');
    $minPrice = $_GET['min_price'] ?? '';
    $maxPrice = $_GET['max_price'] ?? '';
    $minSeats = $_GET['min_seats'] ?? '';
    $maxSeats = $_GET['max_seats'] ?? '';
    $seatRanges = trim($_GET['seat_ranges'] ?? '');
    $sort = trim($_GET['sort'] ?? 'newest');
    $featured = $_GET['featured'] ?? '';

    $cacheKey = 'list_managed_v5_' . JsonCache::getGlobalVersion() . '_' . md5(implode('|', [$page, $limit, $search, $listing_type, $city, $area, $minPrice, $maxPrice, $minSeats, $maxSeats, $seatRanges, $sort, $featured]));
    $cached = $cache->get($cacheKey);
    if ($cached) { echo json_encode($cached); exit; }

    $where = ["status='active'"];
    $params = [];
    $types = '';

    if ($search) {
        $where[] = "(title LIKE ? OR area LIKE ? OR city LIKE ? OR address LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s]);
        $types .= 'ssss';
    }
    if ($listing_type && in_array($listing_type, ['managed', 'commercial'])) {
        if ($listing_type === 'managed') {
            $where[] = "(listing_type = ? OR listing_type IS NULL)";
        } else {
            $where[] = "listing_type = ?";
        }
        $params[] = $listing_type;
        $types .= 's';
    }
    if ($city) {
        $where[] = "city = ?";
        $params[] = $city;
        $types .= 's';
    }
    $areaList = [];
    if ($area !== '') {
        foreach (explode(',', $area) as $a) {
            $a = trim($a);
            if ($a !== '') $areaList[] = $a;
        }
    }
    if (!empty($areaList)) {
        $areaIn = implode(',', array_fill(0, count($areaList), '?'));
        $where[] = "area IN ($areaIn)";
        foreach ($areaList as $a) { $params[] = $a; $types .= 's'; }
    }
    if ($featured === '1') {
        $where[] = "featured = 1";
    }
    if ($minSeats !== '') {
        $where[] = "total_seats >= ?";
        $params[] = (int)$minSeats;
        $types .= 'i';
    }
    if ($maxSeats !== '') {
        $where[] = "total_seats <= ?";
        $params[] = (int)$maxSeats;
        $types .= 'i';
    }
    if ($seatRanges !== '') {
        $rangeClauses = [];
        foreach (explode(',', $seatRanges) as $r) {
            $r = trim($r);
            if ($r === '') continue;
            $parts = explode('-', $r);
            if (count($parts) === 2) {
                $min = (int)$parts[0];
                $max = (int)$parts[1];
                if ($max >= 9999) {
                    $rangeClauses[] = "total_seats >= ?";
                    $params[] = $min; $types .= 'i';
                } else {
                    $rangeClauses[] = "(total_seats BETWEEN ? AND ?)";
                    $params[] = $min; $types .= 'i';
                    $params[] = $max; $types .= 'i';
                }
            }
        }
        if (!empty($rangeClauses)) {
            $where[] = '(' . implode(' OR ', $rangeClauses) . ')';
        }
    }

    $needsPriceFilter = ($minPrice !== '' || $maxPrice !== '');
    if ($needsPriceFilter) {
        if ($minPrice !== '') {
            $where[] = "price >= ?";
            $params[] = (float)$minPrice;
            $types .= 'd';
        }
        if ($maxPrice !== '') {
            $where[] = "price <= ?";
            $params[] = (float)$maxPrice;
            $types .= 'd';
        }
    }

    $whereClause = implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) as total FROM managed_offices WHERE $whereClause";
    $stmt = mysqli_prepare($conn, $countSql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    if ($total === 0) {
        $response = ['total' => 0, 'offices' => [], 'facets' => [], 'nearest' => []];
        $cache->set($cacheKey, $response);
        echo json_encode($response);
        exit;
    }

    switch ($sort) {
        case 'price_asc': $orderBy = 'price ASC'; break;
        case 'price_desc': $orderBy = 'price DESC'; break;
        case 'seats': $orderBy = 'total_seats DESC'; break;
        case 'area': $orderBy = 'total_area_sqft DESC'; break;
        default: $orderBy = 'featured DESC, created_at DESC';
    }

    $sql = "SELECT id, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, billable_seats, remarks, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, created_at, listing_code
            FROM managed_offices
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";

    $allParams = array_merge($params, [$limit, $offset]);
    $allTypes = $types . 'ii';

    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($allParams)) {
        mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $offices = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['amenities_arr'] = json_decode($row['amenities'] ?? '[]', true);
        $row['images_arr'] = resolve_images_for_row($row['images'] ?? '[]', 'managed', $row['id'] ?? 0, $conn);
        $row['first_image'] = $row['images_arr'][0] ?? null;
        unset($row['amenities']);
        unset($row['images']);
        $offices[] = $row;
    }

    // Facets — apply same filters as main query for accurate counts
    $facets = [];

    $facetWhere = ["status='active'"];
    $facetParams = [];
    $facetTypes = '';

    if ($search) {
        $facetWhere[] = "(title LIKE ? OR area LIKE ? OR city LIKE ? OR address LIKE ?)";
        $s = "%$search%";
        $facetParams = array_merge($facetParams, [$s, $s, $s, $s]);
        $facetTypes .= 'ssss';
    }
    if ($listing_type && in_array($listing_type, ['managed', 'commercial'])) {
        if ($listing_type === 'managed') {
            $facetWhere[] = "(listing_type = ? OR listing_type IS NULL)";
        } else {
            $facetWhere[] = "listing_type = ?";
        }
        $facetParams[] = $listing_type;
        $facetTypes .= 's';
    }
    if (!empty($areaList)) {
        $areaIn = implode(',', array_fill(0, count($areaList), '?'));
        $facetWhere[] = "area IN ($areaIn)";
        foreach ($areaList as $a) { $facetParams[] = $a; $facetTypes .= 's'; }
    }
    if ($minSeats !== '') {
        $facetWhere[] = "total_seats >= ?";
        $facetParams[] = (int)$minSeats;
        $facetTypes .= 'i';
    }
    if ($maxSeats !== '') {
        $facetWhere[] = "total_seats <= ?";
        $facetParams[] = (int)$maxSeats;
        $facetTypes .= 'i';
    }
    if ($seatRanges !== '') {
        $rangeClauses = [];
        foreach (explode(',', $seatRanges) as $r) {
            $r = trim($r);
            if ($r === '') continue;
            $parts = explode('-', $r);
            if (count($parts) === 2) {
                $min = (int)$parts[0];
                $max = (int)$parts[1];
                if ($max >= 9999) {
                    $rangeClauses[] = "total_seats >= ?";
                    $facetParams[] = $min; $facetTypes .= 'i';
                } else {
                    $rangeClauses[] = "(total_seats BETWEEN ? AND ?)";
                    $facetParams[] = $min; $facetTypes .= 'i';
                    $facetParams[] = $max; $facetTypes .= 'i';
                }
            }
        }
        if (!empty($rangeClauses)) {
            $facetWhere[] = '(' . implode(' OR ', $rangeClauses) . ')';
        }
    }
    if ($needsPriceFilter) {
        if ($minPrice !== '') {
            $facetWhere[] = "price >= ?";
            $facetParams[] = (float)$minPrice;
            $facetTypes .= 'd';
        }
        if ($maxPrice !== '') {
            $facetWhere[] = "price <= ?";
            $facetParams[] = (float)$maxPrice;
            $facetTypes .= 'd';
        }
    }

    $facetWhereClause = implode(' AND ', $facetWhere);

    $facetStmt = mysqli_prepare($conn,
        "SELECT
            COUNT(*) as total_published,
            SUM(featured = 1) as featured_count,
            MIN(price) as min_price,
            MAX(price) as max_price
         FROM managed_offices
         WHERE $facetWhereClause"
    );
    if (!empty($facetParams)) {
        mysqli_stmt_bind_param($facetStmt, $facetTypes, ...$facetParams);
    }
    mysqli_stmt_execute($facetStmt);
    $facetRow = mysqli_fetch_assoc(mysqli_stmt_get_result($facetStmt));
    mysqli_stmt_close($facetStmt);
    $facets['price_range'] = ['min_price' => $facetRow['min_price'], 'max_price' => $facetRow['max_price']];
    $featuredCount = (int)($facetRow['featured_count'] ?? 0);

    // City list facet (no city filter — shows all cities matching other criteria)
    $cityStmt = mysqli_prepare($conn,
        "SELECT city, COUNT(*) as cnt FROM managed_offices WHERE $facetWhereClause GROUP BY city ORDER BY cnt DESC"
    );
    if (!empty($facetParams)) {
        mysqli_stmt_bind_param($cityStmt, $facetTypes, ...$facetParams);
    }
    mysqli_stmt_execute($cityStmt);
    $cityResult = mysqli_stmt_get_result($cityStmt);
    while ($r = mysqli_fetch_assoc($cityResult)) {
        $facets['cities'][] = $r;
    }
    mysqli_stmt_close($cityStmt);

    // Area facet
    $areaStmt = mysqli_prepare($conn,
        "SELECT area, COUNT(*) as cnt FROM managed_offices WHERE $facetWhereClause AND area IS NOT NULL GROUP BY area ORDER BY cnt DESC"
    );
    if (!empty($facetParams)) {
        mysqli_stmt_bind_param($areaStmt, $facetTypes, ...$facetParams);
    }
    mysqli_stmt_execute($areaStmt);
    $areaResult = mysqli_stmt_get_result($areaStmt);
    while ($r = mysqli_fetch_assoc($areaResult)) {
        $facets['areas'][] = $r;
    }
    mysqli_stmt_close($areaStmt);

    // Nearest workspaces (GeoHash-optimized)
    $nearest = [];
    if (!empty($offices) && $total > 0) {
        $latSum = 0; $lngSum = 0; $coordCount = 0;
        $excludeIds = [];
        foreach ($offices as $o) {
            if ($o['latitude'] && $o['longitude']) {
                $latSum += (float)$o['latitude'];
                $lngSum += (float)$o['longitude'];
                $coordCount++;
            }
            $excludeIds[] = (int)$o['id'];
        }
        if ($coordCount > 0 && !empty($excludeIds)) {
            $centerLat = $latSum / $coordCount;
            $centerLng = $lngSum / $coordCount;

            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $nearSql = "SELECT id, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, billable_seats, remarks, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, created_at, listing_code
                        FROM managed_offices
                        WHERE status='active' AND latitude IS NOT NULL AND id NOT IN ($placeholders)
                        ORDER BY (POW(latitude - ?, 2) + POW(longitude - ?, 2))
                        LIMIT 6";
            $nearParams = array_merge($excludeIds, [$centerLat, $centerLng]);
            $nearTypes = str_repeat('i', count($excludeIds)) . 'dd';
            $nearStmt = mysqli_prepare($conn, $nearSql);
            mysqli_stmt_bind_param($nearStmt, $nearTypes, ...$nearParams);
            mysqli_stmt_execute($nearStmt);
            $nearRes = mysqli_stmt_get_result($nearStmt);
            while ($r = mysqli_fetch_assoc($nearRes)) {
                $dlat = deg2rad((float)$r['latitude'] - $centerLat);
                $dlng = deg2rad((float)$r['longitude'] - $centerLng);
                $a = sin($dlat/2) * sin($dlat/2) + cos(deg2rad($centerLat)) * cos(deg2rad((float)$r['latitude'])) * sin($dlng/2) * sin($dlng/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $r['distance_km'] = round(6371 * $c, 1);
                $r['images_arr'] = resolve_images_for_row($r['images'] ?? '[]', 'managed', $r['id'] ?? 0, $conn);
                $r['first_image'] = $r['images_arr'][0] ?? null;
                unset($r['amenities']); unset($r['images']);
                $nearest[] = $r;
            }
            usort($nearest, function($a, $b) { return $a['distance_km'] <=> $b['distance_km']; });
        }
    }

    $response = [
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'offices' => $offices,
        'facets' => $facets,
        'featured_count' => $featuredCount,
        'nearest' => $nearest,
    ];

    $cache->set($cacheKey, $response);
    echo json_encode($response);
    exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        exit;
    }
}

if ($action === 'map') {
    $city = trim($_GET['city'] ?? '');
    $area = trim($_GET['area'] ?? '');
    $search = trim($_GET['search'] ?? '');
    $minSeats = $_GET['min_seats'] ?? '';
    $maxSeats = $_GET['max_seats'] ?? '';
    $limit = min(200, max(1, (int)($_GET['limit'] ?? 100)));

    $where = ["status='active'", "latitude IS NOT NULL", "longitude IS NOT NULL"];
    $params = [];
    $types = '';

    if ($city) {
        $where[] = "city = ?";
        $params[] = $city;
        $types .= 's';
    }
    if ($area) {
        $where[] = "area = ?";
        $params[] = $area;
        $types .= 's';
    }
    if ($search) {
        $where[] = "(title LIKE ? OR area LIKE ? OR city LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s]);
        $types .= 'sss';
    }
    if ($minSeats !== '' && $maxSeats !== '') {
        $where[] = "total_seats BETWEEN ? AND ?";
        $params[] = (int)$minSeats;
        $params[] = (int)$maxSeats;
        $types .= 'ii';
    } elseif ($minSeats !== '') {
        $where[] = "total_seats >= ?";
        $params[] = (int)$minSeats;
        $types .= 'i';
    } elseif ($maxSeats !== '') {
        $where[] = "total_seats <= ?";
        $params[] = (int)$maxSeats;
        $types .= 'i';
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT id, title, slug, city, area, address, latitude, longitude, price, price_label, total_seats, billable_seats, remarks, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured
            FROM managed_offices WHERE $whereClause ORDER BY featured DESC, created_at DESC LIMIT ?";

    $allParams = array_merge($params, [$limit]);
    $allTypes = $types . 'i';

    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($allParams)) mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $features = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $imagesArr = resolve_images_for_row($row['images'] ?? '[]', 'managed', $row['id'] ?? 0, $conn);
        $amenitiesArr = json_decode($row['amenities'] ?? '[]', true);
        $features[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'city' => $row['city'],
            'area' => $row['area'],
            'address' => $row['address'],
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'price' => $row['price'],
            'price_label' => $row['price_label'],
            'total_seats' => $row['total_seats'],
            'billable_seats' => $row['billable_seats'],
            'total_area_sqft' => $row['total_area_sqft'],
            'office_space_type' => $row['office_space_type'] ?? 'rent',
            'featured' => $row['featured'],
            'first_image' => $imagesArr[0] ?? null,
            'amenities' => array_slice($amenitiesArr, 0, 4),
        ];
    }

    $center = [13.0827, 80.2707];
    if (!empty($features)) {
        $lats = array_column($features, 'latitude');
        $lngs = array_column($features, 'longitude');
        $center = [array_sum($lats) / count($lats), array_sum($lngs) / count($lngs)];
    }

    echo json_encode([
        'total' => count($features),
        'center' => $center,
        'features' => $features,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    exit;
}
