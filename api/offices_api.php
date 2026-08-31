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

set_cors_headers('GET, OPTIONS');
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

$rateLimiter = new RateLimiter(30, 60, 'mo_api_');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!$rateLimiter->check($clientIp)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Try again in a minute.']);
    exit;
}

function decode_existing_listing_images($imagesJson) {
    $images = json_decode($imagesJson ?? '[]', true);
    if (!is_array($images)) {
        return [];
    }
    return array_values(array_filter($images, function($image) {
        return is_string($image) && trim($image) !== '';
    }));
}

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

$action = $_GET['action'] ?? 'list';

if ($action === 'autocomplete') {
    $query = $_GET['q'] ?? '';
    if (strlen($query) < 1) { echo json_encode([]); exit; }

    $cacheKey = 'ac_all_v1_' . JsonCache::getGlobalVersion() . '_' . md5($query);
    $cached = $cache->get($cacheKey);
    if ($cached) { echo json_encode($cached); exit; }

    $like = $query . '%';
    $stmt = mysqli_prepare($conn,
        "(SELECT DISTINCT title, area, city FROM furnished_offices
          WHERE status='active' AND (title LIKE ? OR area LIKE ? OR city LIKE ?)
          LIMIT 10)
         UNION ALL
         (SELECT DISTINCT title, area, city FROM unfurnished_offices
          WHERE status='active' AND (title LIKE ? OR area LIKE ? OR city LIKE ?)
          LIMIT 10)
         LIMIT 10"
    );
    mysqli_stmt_bind_param($stmt, 'ssssss', $like, $like, $like, $like, $like, $like);
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
    $city = trim($_GET['city'] ?? '');
    $area = trim($_GET['area'] ?? '');
    $minPrice = $_GET['min_price'] ?? '';
    $maxPrice = $_GET['max_price'] ?? '';
    $minSeats = $_GET['min_seats'] ?? '';
    $maxSeats = $_GET['max_seats'] ?? '';
    $minSqft = $_GET['min_sqft'] ?? '';
    $maxSqft = $_GET['max_sqft'] ?? '';
    $sqftRanges = trim($_GET['sqft_ranges'] ?? '');
    $sort = trim($_GET['sort'] ?? 'newest');
    $featured = $_GET['featured'] ?? '';

    $cacheKey = 'list_all_v2_' . JsonCache::getGlobalVersion() . '_' . md5(implode('|', [$page, $limit, $search, $city, $area, $minPrice, $maxPrice, $minSeats, $maxSeats, $minSqft, $maxSqft, $sqftRanges, $sort, $featured]));
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
    if ($minSqft !== '') {
        $where[] = "total_area_sqft >= ?";
        $params[] = (int)$minSqft;
        $types .= 'i';
    }
    if ($maxSqft !== '') {
        $where[] = "total_area_sqft <= ?";
        $params[] = (int)$maxSqft;
        $types .= 'i';
    }
    if ($sqftRanges !== '') {
        $rangeClauses = [];
        foreach (explode(',', $sqftRanges) as $r) {
            $r = trim($r);
            if ($r === '') continue;
            $parts = explode('-', $r);
            if (count($parts) === 2) {
                $min = (int)$parts[0];
                $max = ($parts[1] === '') ? null : (int)$parts[1];
                if ($max !== null && $max > 0) {
                    $rangeClauses[] = "(total_area_sqft BETWEEN ? AND ?)";
                    $params[] = $min; $types .= 'i';
                    $params[] = $max; $types .= 'i';
                } else {
                    $rangeClauses[] = "total_area_sqft >= ?";
                    $params[] = $min; $types .= 'i';
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

    $countSql = "SELECT SUM(cnt) as total FROM (
        (SELECT COUNT(*) as cnt FROM furnished_offices WHERE $whereClause)
        UNION ALL
        (SELECT COUNT(*) as cnt FROM unfurnished_offices WHERE $whereClause)
    ) combined";
    $stmt = mysqli_prepare($conn, $countSql);
    $allParams = array_merge($params, $params);
    $allTypes = $types . $types;
    if (!empty($allParams)) {
        mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
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

    $baseSql = "SELECT id, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, created_at, listing_code, listing_type_db
                 FROM (
                     SELECT id, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, created_at, listing_code, 'furnished' as listing_type_db
                     FROM furnished_offices
                     WHERE $whereClause
                     UNION ALL
                     SELECT id, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, created_at, listing_code, 'unfurnished' as listing_type_db
                     FROM unfurnished_offices
                     WHERE $whereClause
                 ) combined
                 ORDER BY $orderBy
                 LIMIT ? OFFSET ?";

    $allParams = array_merge($params, $params, [$limit, $offset]);
    $allTypes = $types . $types . 'ii';

    $stmt = mysqli_prepare($conn, $baseSql);
    if (!empty($allParams)) {
        mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $offices = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['amenities_arr'] = json_decode($row['amenities'] ?? '[]', true);
        $row['images_arr'] = decode_existing_listing_images($row['images'] ?? '[]');
        $row['first_image'] = $row['images_arr'][0] ?? null;
        unset($row['amenities']);
        unset($row['images']);
        $offices[] = $row;
    }

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
    if ($minSqft !== '') {
        $facetWhere[] = "total_area_sqft >= ?";
        $facetParams[] = (int)$minSqft;
        $facetTypes .= 'i';
    }
    if ($maxSqft !== '') {
        $facetWhere[] = "total_area_sqft <= ?";
        $facetParams[] = (int)$maxSqft;
        $facetTypes .= 'i';
    }
    if ($sqftRanges !== '') {
        $rangeClauses = [];
        foreach (explode(',', $sqftRanges) as $r) {
            $r = trim($r);
            if ($r === '') continue;
            $parts = explode('-', $r);
            if (count($parts) === 2) {
                $min = (int)$parts[0];
                $max = ($parts[1] === '') ? null : (int)$parts[1];
                if ($max !== null && $max > 0) {
                    $rangeClauses[] = "(total_area_sqft BETWEEN ? AND ?)";
                    $facetParams[] = $min; $facetTypes .= 'i';
                    $facetParams[] = $max; $facetTypes .= 'i';
                } else {
                    $rangeClauses[] = "total_area_sqft >= ?";
                    $facetParams[] = $min; $facetTypes .= 'i';
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

    $cityRes = mysqli_query($conn, "(SELECT city, COUNT(*) as cnt FROM furnished_offices WHERE status='active' GROUP BY city) UNION ALL (SELECT city, COUNT(*) as cnt FROM unfurnished_offices WHERE status='active' GROUP BY city)");
    $cityCounts = [];
    while ($r = mysqli_fetch_assoc($cityRes)) {
        $cityCounts[$r['city']] = ($cityCounts[$r['city']] ?? 0) + (int)$r['cnt'];
    }
    arsort($cityCounts);
    foreach ($cityCounts as $cityName => $cnt) {
        $facets['cities'][] = ['city' => $cityName, 'cnt' => $cnt];
    }

    $facetStmt = mysqli_prepare($conn,
        "SELECT
            SUM(cnt) as total_published,
            SUM(featured_cnt) as featured_count,
            MIN(min_price) as min_price,
            MAX(max_price) as max_price
         FROM (
             (SELECT
                 COUNT(*) as cnt,
                 SUM(featured = 1) as featured_cnt,
                 MIN(price) as min_price,
                 MAX(price) as max_price
              FROM furnished_offices
              WHERE $facetWhereClause)
             UNION ALL
             (SELECT
                 COUNT(*) as cnt,
                 SUM(featured = 1) as featured_cnt,
                 MIN(price) as min_price,
                 MAX(price) as max_price
              FROM unfurnished_offices
              WHERE $facetWhereClause)
         ) combined_facets"
    );
    $allParams = array_merge($facetParams, $facetParams);
    $allTypes = $facetTypes . $facetTypes;
    if (!empty($allParams)) {
        mysqli_stmt_bind_param($facetStmt, $allTypes, ...$allParams);
    }
    mysqli_stmt_execute($facetStmt);
    $facetRow = mysqli_fetch_assoc(mysqli_stmt_get_result($facetStmt));
    mysqli_stmt_close($facetStmt);
    $facets['price_range'] = ['min_price' => $facetRow['min_price'], 'max_price' => $facetRow['max_price']];
    $featuredCount = (int)($facetRow['featured_count'] ?? 0);

    $areaStmt = mysqli_prepare($conn,
        "(SELECT area, COUNT(*) as cnt FROM furnished_offices WHERE $facetWhereClause AND area IS NOT NULL GROUP BY area)
         UNION ALL
         (SELECT area, COUNT(*) as cnt FROM unfurnished_offices WHERE $facetWhereClause AND area IS NOT NULL GROUP BY area)
         ORDER BY cnt DESC"
    );
    $allParams = array_merge($facetParams, $facetParams);
    $allTypes = $facetTypes . $facetTypes;
    if (!empty($allParams)) {
        mysqli_stmt_bind_param($areaStmt, $allTypes, ...$allParams);
    }
    mysqli_stmt_execute($areaStmt);
    $areaResult = mysqli_stmt_get_result($areaStmt);
    $areaCounts = [];
    while ($r = mysqli_fetch_assoc($areaResult)) {
        $areaCounts[$r['area']] = ($areaCounts[$r['area']] ?? 0) + (int)$r['cnt'];
    }
    arsort($areaCounts);
    foreach ($areaCounts as $areaName => $cnt) {
        $facets['areas'][] = ['area' => $areaName, 'cnt' => $cnt];
    }
    mysqli_stmt_close($areaStmt);

    $nearest = [];
    if (!empty($offices) && $total > 0) {
        $latSum = 0; $lngSum = 0; $coordCount = 0;
        $excludePairs = [];
        foreach ($offices as $o) {
            if ($o['latitude'] && $o['longitude']) {
                $latSum += (float)$o['latitude'];
                $lngSum += (float)$o['longitude'];
                $coordCount++;
            }
            $excludePairs[] = $o;
        }
        if ($coordCount > 0 && !empty($excludePairs)) {
            $centerLat = $latSum / $coordCount;
            $centerLng = $lngSum / $coordCount;

            $nearSql = "SELECT id, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, created_at, listing_code, listing_type_db
                         FROM (
                             SELECT id, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, created_at, listing_code, 'furnished' as listing_type_db
                             FROM furnished_offices
                             WHERE status='active' AND latitude IS NOT NULL
                             UNION ALL
                             SELECT id, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, created_at, listing_code, 'unfurnished' as listing_type_db
                             FROM unfurnished_offices
                             WHERE status='active' AND latitude IS NOT NULL
                         ) combined
                         ORDER BY (POW(latitude - ?, 2) + POW(longitude - ?, 2))
                         LIMIT 10";
            $nearParams = [$centerLat, $centerLng];
            $nearTypes = 'dd';
            $nearStmt = mysqli_prepare($conn, $nearSql);
            mysqli_stmt_bind_param($nearStmt, $nearTypes, ...$nearParams);
            mysqli_stmt_execute($nearStmt);
            $nearRes = mysqli_stmt_get_result($nearStmt);
            while ($r = mysqli_fetch_assoc($nearRes)) {
                $isExcluded = false;
                foreach ($excludePairs as $ep) {
                    if ($ep['id'] == $r['id'] && $ep['listing_type_db'] === $r['listing_type_db']) {
                        $isExcluded = true;
                        break;
                    }
                }
                if ($isExcluded) continue;
                $dlat = deg2rad((float)$r['latitude'] - $centerLat);
                $dlng = deg2rad((float)$r['longitude'] - $centerLng);
                $a = sin($dlat/2) * sin($dlat/2) + cos(deg2rad($centerLat)) * cos(deg2rad((float)$r['latitude'])) * sin($dlng/2) * sin($dlng/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $r['distance_km'] = round(6371 * $c, 1);
                $r['images_arr'] = decode_existing_listing_images($r['images'] ?? '[]');
                $r['first_image'] = $r['images_arr'][0] ?? null;
                unset($r['amenities']); unset($r['images']);
                $nearest[] = $r;
            }
            usort($nearest, function($a, $b) { return $a['distance_km'] <=> $b['distance_km']; });
            $nearest = array_slice($nearest, 0, 6);
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

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    exit;
}
