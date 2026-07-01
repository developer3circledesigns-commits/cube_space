<?php
require_once __DIR__ . '/includes/bootstrap.php';
mysqli_report(MYSQLI_REPORT_OFF);

if (!function_exists('fmt_seats')) {
    function fmt_seats($s) {
        $s = (int)$s;
        if ($s <= 50) return '10-50';
        if ($s <= 100) return '51-100';
        if ($s <= 200) return '101-200';
        return '200+';
    }
}
if (!function_exists('clean_min_inventory')) {
    function clean_min_inventory($val) {
        if (empty($val)) return '';
        return trim(preg_replace('/\b(cabin|office|floor|seats?|people|persons?|none)\b\s*\+?\s*/i', '', $val));
    }
}

$action = $_GET['action'] ?? '';
if ($action === 'forgot_password' || $action === 'reset_password') {
    header('Content-Type: application/json');
    if ($action === 'forgot_password') {
        $username = trim($_POST['username'] ?? '');
        if (!$username) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Username required'])); }
        cubespace_load_db_config();
        if (!$conn) { http_response_code(500); die(json_encode(['success'=>false,'error'=>'DB unavailable'])); }
        $r = @mysqli_query($conn, "SELECT id FROM admins WHERE username='" . mysqli_real_escape_string($conn,$username) . "'");
        if (!$r) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        $a = @mysqli_fetch_assoc($r);
        if (!$a) { http_response_code(404); die(json_encode(['success'=>false,'error'=>'Admin not found'])); }
        $token = bin2hex(random_bytes(16));
        if (!@mysqli_query($conn, "INSERT INTO password_resets (admin_id,token,expires_at) VALUES (" . (int)$a['id'] . ",'" . mysqli_real_escape_string($conn,$token) . "',DATE_ADD(NOW(),INTERVAL 1 HOUR))")) {
            http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)]));
        }
        die(json_encode(['success'=>true,'message'=>'Use this token. Expires in 1 hour.','reset_token'=>$token]));
    }
    if ($action === 'reset_password') {
        $token = trim($_POST['token'] ?? ''); $password = trim($_POST['password'] ?? '');
        if (!$token||!$password) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Token and password required'])); }
        if (strlen($password)<6) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Password min 6 chars'])); }
        cubespace_load_db_config();
        if (!$conn) { http_response_code(500); die(json_encode(['success'=>false,'error'=>'DB unavailable'])); }
        $r = @mysqli_query($conn, "SELECT id,admin_id FROM password_resets WHERE token='" . mysqli_real_escape_string($conn,$token) . "' AND used=0 AND expires_at>NOW()");
        if (!$r) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        $row = @mysqli_fetch_assoc($r);
        if (!$row) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Invalid or expired token'])); }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $aid = (int)$row['admin_id'];
        @mysqli_begin_transaction($conn);
        $ok1 = @mysqli_query($conn, "UPDATE admins SET password='" . mysqli_real_escape_string($conn,$hash) . "' WHERE id=" . $aid);
        $ok2 = @mysqli_query($conn, "UPDATE password_resets SET used=1 WHERE id=" . (int)$row['id']);
        if ($ok1 && $ok2) { @mysqli_commit($conn); die(json_encode(['success'=>true,'message'=>'Password reset successfully.'])); }
        else { @mysqli_rollback($conn); http_response_code(500); die(json_encode(['success'=>false,'error'=>'Reset failed'])); }
    }
}
?>
<?php
set_error_handler(function ($severity, $message, $file, $line) {
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
try {
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') {
    header('Location: index.php');
    exit;
}

cubespace_load_db_config();
cubespace_require_project('lib/config.php');
cubespace_require_project('lib/geohash.php');

$slugValid = preg_match('/^[a-zA-Z0-9\-_]+$/', $slug);
if (!$slugValid) {
    http_response_code(400);
    ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Invalid URL - CubeSpace</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Roboto,sans-serif;"><div style="text-align:center;"><h1 style="font-size:32px;color:#0d4ab4;">Invalid URL</h1><p>The workspace URL is not valid.</p><a href="managed_offices.php" style="display:inline-block;padding:12px 28px;background:#0d4ab4;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Browse Workspaces</a></div></body></html>';
    exit;
}

if (!isset($conn) || !$conn) {
    ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Service Unavailable - CubeSpace</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Roboto,sans-serif;margin:0;background:#fafbff;"><div style="text-align:center;padding:40px;"><h1 style="font-size:28px;color:#0d4ab4;margin-bottom:12px;">Service Unavailable</h1><p style="color:#64748b;font-size:16px;">We are experiencing a temporary issue. Please try again later.</p></div></body></html>';
    exit;
}

$listingTypeDb = '';
$typeParam = isset($_GET['type']) ? trim($_GET['type']) : '';

if ($typeParam && in_array($typeParam, ['managed', 'furnished', 'unfurnished'])) {
    $tableMap = ['managed' => 'managed_offices', 'furnished' => 'furnished_offices', 'unfurnished' => 'unfurnished_offices'];
    $table = $tableMap[$typeParam];
    $stmt = mysqli_prepare($conn, "SELECT * FROM $table WHERE slug = ? AND status = 'published'");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $office = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($office) { $listingTypeDb = $typeParam; }
}

if (!$office) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM managed_offices WHERE slug = ? AND status = 'published'");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $officeResult = mysqli_stmt_get_result($stmt);
    $office = mysqli_fetch_assoc($officeResult);
    mysqli_stmt_close($stmt);
    if ($office) { $listingTypeDb = 'managed'; }
}

if (!$office) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM furnished_offices WHERE slug = ? AND status = 'published'");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $officeResult = mysqli_stmt_get_result($stmt);
    $office = mysqli_fetch_assoc($officeResult);
    mysqli_stmt_close($stmt);
    if ($office) { $listingTypeDb = 'furnished'; }
}

if (!$office) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM unfurnished_offices WHERE slug = ? AND status = 'published'");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $officeResult = mysqli_stmt_get_result($stmt);
    $office = mysqli_fetch_assoc($officeResult);
    mysqli_stmt_close($stmt);
    if ($office) { $listingTypeDb = 'unfurnished'; }
}

if (!$office) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Workspace Not Found - CubeSpace</title>';
    echo '<link rel="icon" href="favicon.ico" sizes="any">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">';
    echo '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:"Roboto",sans-serif;background:#fff;color:#212121;display:flex;align-items:center;justify-content:center;min-height:100vh}.not-found{text-align:center;padding:40px}.not-found i{font-size:64px;color:#0d4ab4;margin-bottom:20px}.not-found h1{font-size:32px;font-weight:700;color:#111827;margin-bottom:12px}.not-found p{font-size:16px;color:#64748b;margin-bottom:24px}.not-found a{display:inline-block;padding:12px 28px;background:#0d4ab4;color:#fff;text-decoration:none;border-radius:8px;font-weight:600}.not-found a:hover{background:#083891}</style></head><body>';
    echo '<div class="not-found"><i class="fa-solid fa-building-circle-xmark"></i><h1>Workspace Not Found</h1><p>The workspace you are looking for does not exist or has been removed.</p><a href="managed_offices.php">Browse Workspaces</a></div>';
    echo '</body></html>';
    exit;
}

$office['listing_type_db'] = $listingTypeDb;
$officeId = $office['id'] ?? 0;
$officeName = htmlspecialchars($office['title'] ?? '');
$officeAddress = htmlspecialchars($office['address'] ?? '');
$officeCityRaw = $office['city'] ?? '';
$officeCity = htmlspecialchars($officeCityRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$officeArea = htmlspecialchars($office['area'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$officeDescription = $office['description'] ?? '';
$officeSeoText = $office['seo_text'] ?? '';
$officeSeats = $office['total_seats'] ?? 0;
$officeCabins = $office['private_cabins'] ?? max(0, (int)($office['total_seats'] ?? 0) > 20 ? (int)(($office['total_seats'] ?? 0) * 0.3) : 0);
$officeMoveIn = 'Immediate';
$officeVerified = 1;
$officeFeatured = $office['featured'] ?? 0;
$officePrice = $office['price'] ?? 0;
$officeSpaceType = $office['office_space_type'] ?? 'rent';
$pricePeriod = $officeSpaceType === 'lease' ? '/yr' : '/mo';
$typeLabels = ['managed' => 'Managed Office Space', 'furnished' => 'Furnished Office Space', 'unfurnished' => 'Unfurnished Office Space'];
$officeType = $typeLabels[$listingTypeDb] ?? 'Office Space';
$officeSlug = htmlspecialchars($office['slug'] ?? $slug);


function filter_listing_images($imagesJson) {
    $images = json_decode($imagesJson ?? '[]', true);
    if (!is_array($images)) {
        return [];
    }

    return array_values(array_filter($images, function($image) {
        if (!is_string($image) || trim($image) === '') {
            return false;
        }

        $host = parse_url($image, PHP_URL_HOST);
        $scheme = parse_url($image, PHP_URL_SCHEME);
        if ($host || $scheme) {
            return true;
        }

        $path = parse_url($image, PHP_URL_PATH);
        if (!$path || $path[0] !== '/') {
            $localPath = __DIR__ . '/' . ltrim($path, '/');
            return file_exists($localPath);
        }

        return file_exists(__DIR__ . $path);
    }));
}

$images = filter_listing_images($office['images'] ?? '[]');
if (empty($images)) {
    $images = ['https://images.unsplash.com/photo-1497366756111-5c12c1785e86?w=1200'];
}

$featureHighlights = json_decode($office['feature_highlights'] ?? '[]', true) ?: [];
$amenities = json_decode($office['amenities'] ?? '[]', true) ?: [];

// Fetch leasing options
$leasingStmt = mysqli_prepare($conn, "SELECT option_title, option_desc, option_price, option_image FROM office_leasing_options WHERE office_id = ? AND is_active = 1 ORDER BY sort_order ASC");
mysqli_stmt_bind_param($leasingStmt, 'i', $officeId);
mysqli_stmt_execute($leasingStmt);
$leasingResult = mysqli_stmt_get_result($leasingStmt);
$leasingOptions = [];
while ($lr = mysqli_fetch_assoc($leasingResult)) { $leasingOptions[] = $lr; }
mysqli_stmt_close($leasingStmt);



// Fetch nearest workspaces
$nearestOffices = [];
$officeLat = $office['latitude'] ?? null;
$officeLng = $office['longitude'] ?? null;
if ($officeLat && $officeLng) {
    $nearStmt = mysqli_prepare($conn,
        "(SELECT id, title, slug, city, area, address, price, total_seats, images, featured, latitude, longitude, 'managed' as listing_type_db FROM managed_offices WHERE status='published' AND slug != ? AND latitude IS NOT NULL)
        UNION ALL
        (SELECT id, title, slug, city, area, address, price, total_seats, images, featured, latitude, longitude, 'furnished' as listing_type_db FROM furnished_offices WHERE status='published' AND slug != ? AND latitude IS NOT NULL)
        UNION ALL
        (SELECT id, title, slug, city, area, address, price, total_seats, images, featured, latitude, longitude, 'unfurnished' as listing_type_db FROM unfurnished_offices WHERE status='published' AND slug != ? AND latitude IS NOT NULL)
        ORDER BY (POW(latitude - ?, 2) + POW(longitude - ?, 2))
        LIMIT 6"
    );
    mysqli_stmt_bind_param($nearStmt, 'sssdd', $slug, $slug, $slug, $officeLat, $officeLng);
    mysqli_stmt_execute($nearStmt);
    $nearRes = mysqli_stmt_get_result($nearStmt);
    while ($nr = mysqli_fetch_assoc($nearRes)) {
        $nr['images_arr'] = filter_listing_images($nr['images'] ?? '[]');
        $nr['first_image'] = $nr['images_arr'][0] ?? null;
        unset($nr['images']);
        $nearestOffices[] = $nr;
    }
    mysqli_stmt_close($nearStmt);
}

$pageTitle = $officeName ? $officeName . ' | CubeSpace' : 'Workspace Details | CubeSpace';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <script>function imgErrorToPlaceholder(img){if(!img||!img.parentElement)return;img.parentElement.innerHTML='<div class=\"placeholder-img\"><i class=\"fa-solid fa-building\"></i></div>';}</script>
    <title><?php echo e($pageTitle); ?></title>
    <meta name="description" content="View details for <?php echo e($officeName); ?> - <?php echo e($officeType); ?> in <?php echo e($officeCity); ?>. Check amenities, pricing, and availability.">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="assets/images/favicon-32x32.png">
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=6">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="access-token" content="">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Roboto', sans-serif; background: #fff; color: #212121; -webkit-font-smoothing: antialiased; }

        /* ===== STICKY NAV (tabs) ===== */
        .sticky-nav {
            position: sticky; top: 70px; z-index: 98; background: #fff;
            border-bottom: 1px solid #e0e0e0; transition: box-shadow 0.2s;
        }
        .sticky-nav.scrolled { box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .sticky-nav-inner {
            max-width: 1280px; margin: 0 auto; padding: 0 24px;
            display: flex; align-items: center; gap: 0; overflow-x: auto;
        }
        .nav-tabs { display: flex; gap: 0; }
        .nav-tab {
            padding: 14px 24px; font-size: 14px; font-weight: 500; color: #64748b;
            text-decoration: none; border-bottom: 2px solid transparent; white-space: nowrap;
            transition: all 0.15s; cursor: pointer;
        }
        .nav-tab:hover { color: #0d4ab4; }
        .nav-tab.active { color: #0d4ab4; border-bottom-color: #0d4ab4; font-weight: 600; }
        .nav-workspace-info {
            margin-left: auto; display: flex; align-items: center; gap: 12px;
            padding: 8px 16px; background: #f8fafc; border-radius: 8px; flex-shrink: 0;
        }
        .nav-ws-price { font-size: 16px; font-weight: 700; color: #111827; }
        .nav-ws-seats { font-size: 12px; color: #64748b; }
        .nav-ws-cta {
            padding: 8px 20px; background: #0d4ab4; color: #fff; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; white-space: nowrap;
        }

        /* ===== PAGE LAYOUT ===== */
        .page-container { max-width: 1280px; margin: 0 auto; padding: 24px 24px 80px; }
        .page-layout { display: grid; grid-template-columns: 1fr 380px; gap: 32px; align-items: start; }
        .main-content { min-width: 0; }

        /* ===== BREADCRUMB ===== */
        .breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #757575; margin-bottom: 16px; flex-wrap: wrap; }
        .breadcrumb a { color: #0d4ab4; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb .sep { color: #bdbdbd; }

        /* ===== IMAGE GALLERY (first left big, rest right grid) ===== */
        .image-gallery {
            display: flex;
            gap: 0;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
            position: relative;
            background: #e2e8f0;
            min-height: 460px;
        }
        .gallery-featured {
            flex: 0 0 50%;
            max-width: 50%;
            position: relative;
            overflow: hidden;
            background: #e2e8f0;
            cursor: pointer;
            border-right: 2px solid #fff;
        }
        .gallery-featured img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s;
        }
        .gallery-featured:hover img { transform: scale(1.04); }
        .gallery-side {
            flex: 1 1 50%;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-auto-rows: 1fr;
            gap: 0;
            min-height: 460px;
        }
        .gallery-side .gallery-item {
            border-bottom: 2px solid #fff;
            border-right: 2px solid #fff;
        }
        .gallery-side .gallery-item:nth-child(2n) { border-right: none; }
        .gallery-side .gallery-item:nth-last-child(-n+2) { border-bottom: none; }
        .gallery-item {
            position: relative;
            overflow: hidden;
            background: #e2e8f0;
            cursor: pointer;
            min-height: 0;
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s;
            display: block;
        }
        .gallery-item:hover img { transform: scale(1.05); }
        .gallery-item .gallery-more {
            position: absolute; inset: 0;
            background: rgba(0, 0, 0, 0.55);
            color: #fff;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 4px;
            font-size: 14px;
            font-weight: 600;
            backdrop-filter: blur(2px);
            transition: background 0.2s;
        }
        .gallery-item .gallery-more:hover { background: rgba(0, 0, 0, 0.65); }
        .gallery-item .gallery-more i { font-size: 22px; margin-bottom: 2px; }
        .gallery-item .gallery-more small { font-size: 12px; font-weight: 500; opacity: 0.9; }
        .photo-hint {
            position: absolute; bottom: 12px; left: 12px;
            background: rgba(0, 0, 0, 0.55); color: #fff;
            padding: 6px 12px; border-radius: 6px;
            font-size: 12px; font-weight: 500;
            backdrop-filter: blur(4px); z-index: 2;
            display: flex; align-items: center; gap: 6px;
        }
        @media (max-width: 900px) {
            .image-gallery { flex-direction: column; min-height: auto; }
            .gallery-featured {
                flex: 0 0 auto; max-width: 100%; height: 320px;
                border-right: none; border-bottom: 2px solid #fff;
            }
            .gallery-side { min-height: auto; }
            .gallery-side .gallery-item { border-bottom: 2px solid #fff; }
            .gallery-side .gallery-item:nth-last-child(-n+2) { border-bottom: none; }
        }
        @media (max-width: 500px) {
            .gallery-side .gallery-item { height: 140px; }
        }

        /* ===== LIGHTBOX ===== */
        .lightbox { position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.93); display: none; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .lightbox.active { display: flex; }
        .lb-top { position: absolute; top: 0; left: 0; right: 0; display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; z-index: 10; background: linear-gradient(rgba(0,0,0,0.4), transparent); }
        .lb-counter { font-size: 13px; color: rgba(255,255,255,0.7); font-weight: 500; }
        .lb-close { width: 40px; height: 40px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; font-family: inherit; }
        .lb-close:hover { background: rgba(255,255,255,0.2); }
        .lb-progress { position: absolute; top: 0; left: 0; right: 0; height: 3px; background: rgba(255,255,255,0.15); z-index: 11; }
        .lb-progress-fill { height: 100%; background: #0d4ab4; transition: width 0.3s ease; }
        .lb-slide-area { flex: 1; display: flex; align-items: center; justify-content: center; width: 100%; position: relative; overflow: hidden; touch-action: pan-y; }
        .lb-slide-track { display: flex; height: 100%; transition: transform 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94); will-change: transform; }
        .lb-slide { flex: 0 0 100%; height: 100%; display: flex; align-items: center; justify-content: center; padding: 60px 80px; }
        .lb-slide img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 4px; user-select: none; -webkit-user-drag: none; transition: opacity 0.2s; opacity: 1; }
        .lb-slide.loading img { opacity: 0; }
        .lb-slide img.zoomed { transform: scale(2); cursor: grab; }
        .lb-nav { position: absolute; top: 50%; transform: translateY(-50%); z-index: 10; width: 48px; height: 48px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; backdrop-filter: blur(4px); font-family: inherit; }
        .lb-nav:hover { background: rgba(255,255,255,0.25); }
        .lb-nav:active { transform: translateY(-50%) scale(0.95); }
        .lb-prev { left: 16px; }
        .lb-next { right: 16px; }
        .lb-nav:disabled { opacity: 0.2; cursor: default; }
        .lb-thumbs { width: 100%; background: rgba(0,0,0,0.5); padding: 12px 16px; display: flex; gap: 8px; overflow-x: auto; justify-content: center; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.3) transparent; }
        .lb-thumbs::-webkit-scrollbar { height: 4px; }
        .lb-thumbs::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 2px; }
        .lb-thumb { width: 56px; height: 42px; border-radius: 4px; overflow: hidden; cursor: pointer; border: 2px solid transparent; flex-shrink: 0; opacity: 0.5; transition: all 0.2s; background: #1a1a1a; }
        .lb-thumb:hover { opacity: 0.8; }
        .lb-thumb.active { border-color: #0d4ab4; opacity: 1; }
        .lb-thumb img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
        .lb-spinner { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 36px; height: 36px; border: 3px solid rgba(255,255,255,0.15); border-top-color: #fff; border-radius: 50%; animation: lbSpin 0.7s linear infinite; display: none; }
        .lb-slide.loading .lb-spinner { display: block; }
        @keyframes lbSpin { to { transform: translate(-50%, -50%) rotate(360deg); } }

        /* ===== WORKSPACE NAME ===== */
        .ws-header { margin-bottom: 20px; }
        .ws-type { font-size: 12px; font-weight: 600; color: #90a4ae; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
        .ws-name { font-size: 28px; font-weight: 700; color: #111827; line-height: 1.2; margin-bottom: 8px; }
        .ws-location { display: flex; align-items: center; gap: 6px; font-size: 14px; color: #64748b; margin-bottom: 12px; }
        .ws-location i { color: #0d4ab4; font-size: 13px; }
        .ws-tags { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .ws-tag { padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .tag-verified { background: #def2e1; color: #08753f; }
        .tag-featured { background: #fef3c7; color: #92400e; }
        /* ===== INFO STRIP ===== */
        .info-strip {
            display: flex; align-items: center; gap: 16px; padding: 14px 20px;
            background: #f4f4fb; border-radius: 10px; margin-bottom: 28px; flex-wrap: wrap;
        }
        .info-strip-item { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .info-strip-item i { color: #0d4ab4; font-size: 15px; }
        .info-strip-item strong { font-weight: 600; color: #111827; }
        .info-strip-divider { width: 1px; height: 20px; background: #d2d3ee; }

        /* ===== SECTION ===== */
        .section { margin-bottom: 36px; }
        .section-title {
            font-size: 20px; font-weight: 700; color: #111827; margin-bottom: 18px;
            display: flex; align-items: center; gap: 10px;
        }
        .section-title i { color: #0d4ab4; font-size: 18px; }

        /* ===== OVERVIEW ===== */
        .overview-text { font-size: 15px; line-height: 1.7; color: #475569; margin-bottom: 16px; }
        .overview-text.collapsed { max-height: 80px; overflow: hidden; position: relative; }
        .overview-text.collapsed::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 40px;
            background: linear-gradient(transparent, #fff);
        }
        .read-more-btn { font-size: 14px; font-weight: 600; color: #0d4ab4; cursor: pointer; border: none; background: none; padding: 0; }
        .read-more-btn:hover { text-decoration: underline; }
        .feature-highlights { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .feature-chip {
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
            background: #def2e1; border-radius: 6px; font-size: 13px; font-weight: 500; color: #08753f;
        }
        .feature-chip i { font-size: 12px; }

        /* ===== CENTER DETAILS ===== */
        .center-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .cd-item {
            display: flex; align-items: center; gap: 14px; padding: 16px 18px;
            background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9;
            transition: all 0.15s;
        }
        .cd-item:hover { border-color: #0d4ab4; background: #eef4ff; }
        .cd-icon {
            width: 42px; height: 42px; border-radius: 8px; background: #eef4ff;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .cd-icon i { font-size: 18px; color: #0d4ab4; }
        .cd-label { font-size: 12px; color: #757575; margin-bottom: 2px; }
        .cd-value { font-size: 16px; font-weight: 700; color: #111827; }

        /* ===== AMENITIES ===== */
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            border-top: 1px solid var(--cs-border);
            border-left: 1px solid var(--cs-border);
        }
        .amenity-item {
            display: flex; align-items: center; gap: 8px; padding: 12px 16px;
            background: #f1f5fb;
            border-right: 1px solid var(--cs-border);
            border-bottom: 1px solid var(--cs-border);
            color: #1e3a8a;
            font-size: 14px;
            font-weight: 500;
        }
        .amenity-item:nth-child(3n) { border-right: none; }
        .amenity-item:nth-last-child(-n+3) { border-bottom: none; }
        .amenity-item .icon {
            display: none;
        }
        .amenity-item .icon i { font-size: 16px; color: #0d4ab4; }
        .amenity-item span { font-size: 14px; font-weight: 500; color: #374151; }
        .amenities-tnc {
            margin-top: 16px; padding: 12px 16px; background: #212121; color: #fff;
            font-size: 12px; display: flex; align-items: center; gap: 8px;
            border: 1px solid #212121;
        }
        .amenities-tnc i { color: #f59e0b; }

        /* ===== LEASING OPTIONS / PRICING ===== */
        .pricing-cards { display: flex; flex-direction: column; gap: 16px; }
        .pricing-card {
            display: flex; border: 1px solid #d2d3ee; border-radius: 10px; overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .pricing-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .pricing-card-img { width: 180px; min-height: 130px; background: #e2e8f0; flex-shrink: 0; overflow: hidden; }
        .pricing-card-img img { width: 100%; height: 100%; object-fit: cover; }
        .pricing-card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .pricing-card-title { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 6px; }
        .pricing-card-desc { font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 12px; flex: 1; }
        .pricing-card-price {
            display: flex; align-items: baseline; gap: 4px; padding-left: 12px;
            border-left: 3px solid #0d4ab4;
        }
        .pricing-card-price .amount { font-size: 22px; font-weight: 700; color: #111827; }
        .pricing-card-price .period { font-size: 13px; color: #64748b; }
        .pricing-card-price .label { font-size: 11px; color: #90a4ae; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px; }

        .best-price-banner {
            display: flex; align-items: center; gap: 10px; padding: 12px 20px;
            background: linear-gradient(90deg, #def2e1, #def2e1cc 70%, rgba(222,242,225,0));
            border-radius: 100px 0 0 100px; margin-bottom: 20px;
        }
        .best-price-banner i { color: #08753f; font-size: 18px; }
        .best-price-banner .bp-text { font-size: 14px; font-weight: 600; color: #08753f; }

        /* ===== NEARBY SPACES ===== */
        .nearby-section { margin-bottom: 36px; }
        .nearby-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .nearby-card {
            background: #fff; border-radius: 10px; border: 1px solid #f1f5f9; overflow: hidden;
            transition: box-shadow 0.2s; cursor: pointer;
        }
        .nearby-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); border-color: #0d4ab4; }
        .nearby-card-img { height: 130px; background: #e2e8f0; overflow: hidden; }
        .nearby-card-img img { width: 100%; height: 100%; object-fit: cover; }
        .nearby-card-body { padding: 12px 14px; }
        .nearby-card-title { font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .nearby-card-address { font-size: 12px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 6px; }
        .nearby-card-price { font-size: 13px; font-weight: 700; color: #0d4ab4; }
        .nearby-card-seats { font-size: 11px; color: #90a4ae; margin-left: 6px; }

        @media (max-width: 768px) { .nearby-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .nearby-grid { grid-template-columns: 1fr; } }

        /* ===== SEO TEXT ===== */
        .seo-text { font-size: 14px; color: #64748b; line-height: 1.7; }
        .seo-text h3 { font-size: 18px; font-weight: 600; color: #111827; margin: 20px 0 10px; }

        /* ===== SIDEBAR ===== */
        .sidebar { position: sticky; top: 120px; display: flex; flex-direction: column; gap: 20px; }
        .sidebar-card { background: #fff; border-radius: 12px; border: 1px solid #e0e0e0; overflow: hidden; }
        .sidebar-card-header {
            padding: 16px 20px; background: rgba(207,216,220,0.25); border-bottom: 1px solid #e0e0e0;
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-card-header .expert-avatar { width: 44px; height: 44px; border-radius: 50%; background: #0d4ab4; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .sidebar-card-header .expert-info h4 { font-size: 15px; font-weight: 600; color: #111827; }
        .sidebar-card-header .expert-info p { font-size: 12px; color: #757575; }
        .sidebar-card-header .expert-badge { font-size: 11px; color: #08753f; font-weight: 600; background: #def2e1; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; margin-top: 2px; }

        .sidebar-form { padding: 20px; }
        .sidebar-form label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .sidebar-form input, .sidebar-form select, .sidebar-form textarea {
            width: 100%; height: 44px; padding: 0 14px; border: 1px solid #e0e0e0;
            border-radius: 6px; font-size: 14px; font-family: inherit; color: #212121;
            background: #eceff1; outline: none; transition: border-color 0.15s;
        }
        .sidebar-form textarea { height: 80px; padding: 12px 14px; resize: vertical; }
        .sidebar-form input:focus, .sidebar-form textarea:focus { border-color: #0d4ab4; background: #fff; }
        .sidebar-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn-submit { width: 100%; height: 48px; background: #0d4ab4; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.2s; }
        .btn-submit:hover { background: #083891; }
        .btn-whatsapp {
            width: 100%; height: 44px; background: #25D366; color: #fff; border: none; border-radius: 8px;
            font-size: 14px; font-weight: 600; cursor: pointer; font-family: inherit; margin-top: 10px;
            display: flex; align-items: center; justify-content: center; gap: 8px; transition: opacity 0.2s;
        }
        .btn-whatsapp:hover { opacity: 0.9; }
        .btn-call-sidebar {
            width: 100%; height: 44px; background: #fff; color: #0d4ab4; border: 2px solid #0d4ab4;
            border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: inherit;
            margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;
        }

        .sidebar-benefits { padding: 20px; }
        .sidebar-benefits h4 { font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 14px; }
        .sidebar-benefits li {
            list-style: none; display: flex; align-items: center; gap: 10px;
            font-size: 13px; color: #374151; margin-bottom: 10px;
        }
        .sidebar-benefits li i { color: #0d4ab4; font-size: 14px; width: 20px; text-align: center; }

        /* ===== BOTTOM STICKY CTA ===== */
        .bottom-sticky {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 97;
            background: #fff; border-top: 1px solid #e0e0e0; padding: 16px 24px;
            display: none; align-items: center; justify-content: space-between;
            box-shadow: 0 -2px 12px rgba(0,0,0,0.06);
        }
        .bottom-sticky.visible { display: flex; }
        .bs-left .bs-price { font-size: 20px; font-weight: 700; color: #111827; }
        .bs-left .bs-seats { font-size: 12px; color: #757575; }
        .bs-right { display: flex; gap: 10px; }
        .bs-right .btn-submit { width: auto; padding: 0 28px; }
        .bs-right .btn-call-sidebar { width: auto; padding: 0 20px; margin-top: 0; height: 48px; }

        /* ===== MOBILE BOTTOM BAR ===== */
        .mobile-bottom-bar {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 97;
            background: #fff; border-top: 1px solid #e0e0e0; padding: 12px 16px;
            display: none; box-shadow: 0 -2px 12px rgba(0,0,0,0.06);
            padding-bottom: calc(12px + env(safe-area-inset-bottom));
        }
        .mob-bar-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
        .mob-bar-price { font-size: 18px; font-weight: 700; color: #111827; }
        .mob-bar-seats { font-size: 11px; color: #757575; }
        .mob-bar-actions { display: flex; gap: 8px; }
        .mob-bar-actions button { flex: 1; height: 44px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: inherit; border: none; }
        .mob-bar-actions .mob-enquire { background: #0d4ab4; color: #fff; }
        .mob-bar-actions .mob-call { background: #fff; color: #0d4ab4; border: 2px solid #0d4ab4 !important; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .page-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .nav-workspace-info { display: none; }
            .nav-tab { padding: 12px 16px; font-size: 13px; }
        }
        @media (max-width: 768px) {
            .ws-name { font-size: 22px; }
            .amenities-grid { grid-template-columns: repeat(2, 1fr); }
            .amenity-item:nth-child(3n) { border-right: 1px solid var(--cs-border); }
            .amenity-item:nth-child(2n) { border-right: none; }
            .amenity-item:nth-last-child(-n+3) { border-bottom: 1px solid var(--cs-border); }
            .amenity-item:nth-last-child(-n+2) { border-bottom: none; }

            .pricing-card { flex-direction: column; }
            .pricing-card-img { width: 100%; height: 160px; }
            .bottom-sticky { display: none !important; }
            .mobile-bottom-bar { display: block; }
            .page-container { padding-bottom: 120px; }
            .sticky-nav { top: 70px; }
            .lb-slide { padding: 60px 16px; }
            .lb-nav { width: 36px; height: 36px; font-size: 14px; }
            .lb-prev { left: 8px; }
            .lb-next { right: 8px; }
            .lb-thumbs { justify-content: flex-start; padding: 10px 12px; }
            .lb-thumb { width: 48px; height: 36px; }
        }
        @media (max-width: 576px) {
            .page-container { padding: 16px 16px 120px; }
            .amenities-grid { grid-template-columns: 1fr; }
            .amenity-item { border-right: none; }
            .amenity-item:nth-child(2n),
            .amenity-item:nth-child(3n) { border-right: none; }
            .amenity-item:nth-last-child(-n+2),
            .amenity-item:nth-last-child(-n+3) { border-bottom: 1px solid var(--cs-border); }
            .amenity-item:last-child { border-bottom: none; }
            .center-details-grid { grid-template-columns: 1fr; }
            .info-strip { flex-direction: column; gap: 10px; align-items: flex-start; }
            .info-strip-divider { display: none; }
            .sidebar-form .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- STICKY NAV TABS
<nav class="sticky-nav" id="stickyNav">
    <div class="sticky-nav-inner">
        <div class="nav-tabs">
            <a class="nav-tab active" href="#overview">Overview</a>
            <a class="nav-tab" href="#amenities">Amenities</a>
            <a class="nav-tab" href="#pricing">Pricing</a>
            <a class="nav-tab" href="#location">Location</a>
            <a class="nav-tab" href="#faq">FAQ</a>
        </div>
        <div class="nav-workspace-info">
            <div>
                <div class="nav-ws-price">₹<?php echo format_number($officePrice); ?><?php echo $pricePeriod; ?></div>
                    <div class="nav-ws-seats"><?php echo fmt_seats((int)$officeSeats); ?> Seats</div>
            </div>
            <button class="nav-ws-cta" onclick="scrollToForm()">Get Price</button>
        </div>
    </div>
</nav> -->

<!-- PAGE -->
<div class="container py-4">
    <div class="row g-4">
        <!-- ===== MAIN CONTENT ===== -->
        <div class="col-lg-8 main-content">

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php">Home</a><span class="sep">/</span>
                <a href="<?php echo $listingTypeDb === 'managed' ? 'managed_offices.php' : 'furnished_offices.php'; ?>"><?php echo $listingTypeDb === 'managed' ? 'Managed Offices' : 'Office Spaces'; ?></a><span class="sep">/</span>
                <span><?php echo $officeArea ?: $officeCity; ?></span>
            </div>

            <!-- Image Gallery (first image left big, rest right grid) -->
            <?php
            $totalImages = count($images);
            $firstImage = $images[0] ?? 'https://images.unsplash.com/photo-1497366756111-5c12c1785e86?w=1200';
            $restImages = array_slice($images, 1);
            $restCount = count($restImages);
            // For the overlay on the last visible right-side image when there are many
            $hiddenCount = max(0, $restCount - 4); // up to 4 thumbnails shown in right grid; the 4th gets overlay if more
            ?>
            <div class="image-gallery">
                <div class="gallery-featured" onclick="openLightbox(0)">
                    <img src="<?php echo htmlspecialchars($firstImage); ?>" alt="<?php echo $officeName; ?>" loading="lazy" onerror="imgErrorToPlaceholder(this)">
                    <span class="photo-hint"><i class="fa-solid fa-expand"></i> Click to view all <?php echo (int)$totalImages; ?> photos</span>
                </div>
                <div class="gallery-side">
                    <?php foreach ($restImages as $i => $img):
                        $src = htmlspecialchars($img);
                        $lightboxIdx = $i + 1;
                        $showOverlay = ($i === 3 && $restCount > 4);
                    ?>
                    <div class="gallery-item" onclick="openLightbox(<?php echo (int)$lightboxIdx; ?>)">
                        <img src="<?php echo $src; ?>" alt="<?php echo $officeName; ?>" loading="lazy" onerror="imgErrorToPlaceholder(this)">
                        <?php if ($showOverlay): ?>
                        <div class="gallery-more">
                            <i class="fa-solid fa-images"></i>
                            +<?php echo (int)$hiddenCount; ?> more
                            <small>View all</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Workspace Header -->
            <div class="ws-header">
                <div class="ws-type"><?php echo e($officeType); ?></div>
                <h1 class="ws-name"><?php echo $officeName; ?> <?php if (!empty($office['listing_code'])): ?><code class="small text-muted fw-normal" style="font-size:0.6em;"><?php echo $office['listing_code']; ?></code><?php endif; ?></h1>
                <div class="ws-location">
                    <i class="fa-solid fa-location-dot"></i>
                    <?php echo $officeAddress; ?>
                </div>
                <!--<div class="ws-tags">-->
                <!--    <?php if ($officeVerified): ?>-->
                <!--    <span class="ws-tag tag-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>-->
                <!--    <?php endif; ?>-->
                <!--    <?php if ($officeFeatured): ?>-->
                <!--    <span class="ws-tag tag-featured"><i class="fa-solid fa-star"></i> Featured</span>-->
                <!--    <?php endif; ?>-->

                <!--</div>-->
            </div>

            <!-- Info Strip -->
            <!--<div class="info-strip">-->
            <!--    <div class="info-strip-item"><i class="fa-solid fa-couch"></i> <strong><?php echo (int)$officeSeats; ?></strong> Seats</div>-->
            <!--    <div class="info-strip-divider"></div>-->

            <!--    <div class="info-strip-item"><i class="fa-solid fa-calendar-check"></i> <strong><?php echo htmlspecialchars($officeMoveIn); ?></strong> Move-in</div>-->
            <!--</div>-->

            <!-- Overview -->
            <?php if (!empty($officeDescription)): ?>
            <div class="section" id="overview">
                <div class="section-title"><i class="fa-solid fa-building"></i> Workspace Overview</div>
                <div class="overview-text collapsed" id="overviewText">
                    <?php echo nl2br(htmlspecialchars($officeDescription)); ?>
                </div>
                <button class="read-more-btn" onclick="toggleOverview()">Read more <i class="fa-solid fa-chevron-down"></i></button>
                <!-- <?php if (!empty($featureHighlights)): ?>
                <div class="feature-highlights">
                    <?php foreach ($featureHighlights as $highlight): ?>
                    <span class="feature-chip"><i class="fa-solid fa-check"></i> <?php echo htmlspecialchars($highlight); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?> -->
            </div>
            <?php endif; ?>

            <!-- Center Details -->
            <?php
            $totalSeats = (int)($office['total_seats'] ?? 0);
            $totalSqft = (int)($office['total_area_sqft'] ?? 0);
            $availableSqft = $office['available_sqft'] ?? '';
            $inventoryType = !empty($office['inventory_type']) ? $office['inventory_type'] : '';
            $minInventory = !empty($office['min_inventory']) ? $office['min_inventory'] : '';
            $isManaged = $listingTypeDb === 'managed';
            ?>
            <div class="section" id="center-details">
                <div class="section-title"><i class="fa-solid fa-circle-info"></i> Center Details</div>
                <div class="center-details-grid">
                    <?php if ($isManaged): ?>
                    <div class="cd-item">
                        <div class="cd-icon"><i class="fa-solid fa-people-group"></i></div>
                        <div>
                            <div class="cd-label">Current Available Seats</div>
                            <div class="cd-value"><?php echo fmt_seats($totalSeats); ?> Seats</div>
                        </div>
                    </div>
                    <div class="cd-item">
                        <div class="cd-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <div>
                            <div class="cd-label">Min Inventory</div>
                            <div class="cd-value"><?php echo htmlspecialchars(clean_min_inventory($minInventory) ?: '-'); ?></div>
                        </div>
                    </div>
                    <div class="cd-item">
                        <div class="cd-icon"><i class="fa-solid fa-circle-check" style="color:#166534;"></i></div>
                        <div>
                            <div class="cd-label">Status</div>
                            <div class="cd-value" style="color:#166534;">Ready to move in</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="cd-item">
                        <div class="cd-icon"><i class="fa-solid fa-building"></i></div>
                        <div>
                            <div class="cd-label">Total Building Leasable Area</div>
                            <div class="cd-value"><?php echo $totalSqft ? number_format($totalSqft) . ' Sq Ft.' : '-'; ?></div>
                        </div>
                    </div>
                    <?php if ($availableSqft): ?>
                    <div class="cd-item">
                        <div class="cd-icon"><i class="fa-solid fa-ruler-combined"></i></div>
                        <div>
                            <div class="cd-label">Current Available Area On Rent</div>
                            <div class="cd-value"><?php echo htmlspecialchars($availableSqft); ?> Sq Ft.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($inventoryType): ?>
                    <div class="cd-item">
                        <div class="cd-icon"><i class="fa-solid <?php echo $inventoryType === 'Ready to move in' ? 'fa-circle-check' : 'fa-clock'; ?>" style="color:<?php echo $inventoryType === 'Ready to move in' ? '#166534' : '#92400e'; ?>;"></i></div>
                        <div>
                            <div class="cd-label">Status</div>
                            <div class="cd-value" style="color:<?php echo $inventoryType === 'Ready to move in' ? '#166534' : '#92400e'; ?>;"><?php echo $inventoryType === 'Ready to move in' ? 'Ready to move in' : 'Processing'; ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Amenities -->
            <?php if (!empty($amenities)): ?>
            <div class="section" id="amenities">
                <div class="section-title"><i class="fa-solid fa-sparkles"></i> Amenities</div>
                <div class="amenities-grid">
                    <?php foreach ($amenities as $amenity):
                        $name = is_array($amenity) ? ($amenity['name'] ?? $amenity['title'] ?? '') : $amenity;
                        $icon = is_array($amenity) ? ($amenity['icon'] ?? '') : '';
                    ?>
                    <div class="amenity-item">
                        <div class="icon"><i class="fa-solid <?php echo $icon ? e($icon) : 'fa-check'; ?>"></i></div>
                        <span><?php echo e($name); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="amenities-tnc">
                    <i class="fa-solid fa-circle-info"></i>
                    Amenities may vary. Contact us for detailed information about this workspace.
                </div>
            </div>
            <?php endif; ?>

            <!-- Pricing / Leasing Options
            <?php if (!empty($leasingOptions)): ?>
            <div class="section" id="pricing">
                <div class="section-title"><i class="fa-solid fa-indian-rupee-sign"></i> Pricing & Leasing Options</div>
                <div class="best-price-banner">
                    <i class="fa-solid fa-badge-check"></i>
                    <span class="bp-text">Best price guaranteed | No hidden charges | Flexible terms</span>
                </div>
                <div class="pricing-cards">
                    <?php foreach ($leasingOptions as $option):
                        $title = is_array($option) ? ($option['title'] ?? $option['name'] ?? 'Office Space') : $option;
                        $desc = is_array($option) ? ($option['description'] ?? $option['desc'] ?? '') : '';
                        $price = is_array($option) ? ($option['option_price'] ?? $option['price'] ?? $option['amount'] ?? 0) : 0;
                        $period = is_array($option) ? ($option['period'] ?? 'month') : 'month';
                        $perUnit = is_array($option) ? ($option['per_unit'] ?? $option['unit'] ?? '') : '';
                        $img = is_array($option) ? ($option['image'] ?? $option['img'] ?? ($images[0] ?? '')) : ($images[0] ?? '');
                    ?>
                    <div class="pricing-card">
                        <div class="pricing-card-img">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($title); ?>" loading="lazy" onerror="imgErrorToPlaceholder(this)">
                        </div>
                        <div class="pricing-card-body">
                            <div class="pricing-card-title"><?php echo htmlspecialchars($title); ?></div>
                            <?php if ($desc): ?>
                            <div class="pricing-card-desc"><?php echo htmlspecialchars($desc); ?></div>
                            <?php endif; ?>
                            <div class="pricing-card-price">
                                <div>
                                    <span class="label">Starting from</span>
                                    <span class="amount">₹<?php echo format_number($price); ?></span>
                                    <span class="period">/<?php echo htmlspecialchars($perUnit ? $perUnit . '/' . $period : $period); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?> -->

            <!-- SEO Content -->
            <?php if (!empty($officeSeoText)): ?>
            <div class="section">
                <div class="seo-text">
                    <?php
                    $seoContent = preg_replace('/### (.+)/', '<h3>$1</h3>', $officeSeoText);
                    $seoContent = preg_replace('/## (.+)/', '<h3>$1</h3>', $seoContent);
                    $seoContent = nl2br($seoContent);
                    echo $seoContent;
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($nearestOffices)): ?>
            <div class="nearby-section">
                <div class="section-title"><i class="fa-solid fa-location-dot"></i> Nearby Workspaces</div>
                <p style="font-size:13px;color:#64748b;margin-bottom:16px;"><i class="fa-regular fa-compass"></i> Spaces closest to this location</p>
                <div class="nearby-grid">
                    <?php foreach ($nearestOffices as $n): ?>
                    <a href="office_detail.php?slug=<?php echo e($n['slug']); ?>&type=<?php echo e($n['listing_type_db']); ?>" class="nearby-card" style="text-decoration:none;">
                        <div class="nearby-card-img">
                            <img src="<?php echo e($n['first_image'] ?: 'https://images.unsplash.com/photo-1497366756111-5c12c1785e86?w=600'); ?>" alt="<?php echo e($n['title']); ?>" loading="lazy" onerror="imgErrorToPlaceholder(this)">
                        </div>
                        <div class="nearby-card-body">
                            <div class="nearby-card-title"><?php echo e($n['title']); ?></div>
                            <div class="nearby-card-address"><?php echo e($n['area'] ?: $n['city']); ?></div>
                            <span class="nearby-card-price">₹<?php echo format_number($n['price'] ?? 0); ?></span>
                            <span class="nearby-card-seats"><?php echo fmt_seats((int)$n['total_seats']); ?> seats</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ===== SIDEBAR ===== -->
        <div class="col-lg-4">
        <div class="sidebar position-sticky" id="contactSidebar" style="top:120px">
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <div class="expert-avatar"><i class="fa-solid fa-user-tie"></i></div>
                    <div class="expert-info">
                        <h4>Connect with our workspace expert</h4>
                    </div>
                </div>
                <form class="sidebar-form" id="contactForm" onsubmit="handleSidebarForm(event)">
                    <input type="hidden" name="office_id" value="<?php echo (int)$officeId; ?>">
                    <input type="hidden" name="source" value="detail_page">
                    <div class="mb-3">
                        <label>Full Name *</label>
                        <input type="text" name="name" placeholder="Enter your name" required data-rules="required|max:120">
                    </div>
                    <div class="form-row">
                        <div class="mb-3">
                            <label>Phone *</label>
                            <input type="tel" name="phone" placeholder="10-digit mobile" required data-rules="required|phone" maxlength="10">
                        </div>
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="email@example.com" data-rules="email|max:180">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Team Size</label>
                        <select name="seats">
                            <option value="">Select team size</option>
                            <option value="10-50">10-50 seats</option>
                            <option value="51-100">51-100 seats</option>
                            <option value="101-200">101-200 seats</option>
                            <option value="200+">200+ seats</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Message</label>
                        <textarea name="message" placeholder="Tell us about your requirements..." data-rules="max:1000"></textarea>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Get Best Price</button>
                    <button type="button" class="btn-call-sidebar" onclick="window.open('tel:+919962200015')"><i class="fa-solid fa-phone"></i> Call +91 99622 00015</button>
                </form>
            </div>

            <!-- <div class="sidebar-card">
                <div class="sidebar-benefits">
                    <h4>Why CubeSpace?</h4>
                    <ul>
                        <li><i class="fa-solid fa-check-circle"></i> Move in within 7 days</li>
                        <li><i class="fa-solid fa-check-circle"></i> No brokerage charges</li>
                        <li><i class="fa-solid fa-check-circle"></i> All-inclusive pricing</li>
                        <li><i class="fa-solid fa-check-circle"></i> Flexible lease terms</li>
                        <li><i class="fa-solid fa-check-circle"></i> Premium locations</li>

                    </ul>
                </div>
            </div> -->
        </div>
    </div>
    </div>
</div>

<!-- BOTTOM STICKY CTA (Desktop) -->
<div class="bottom-sticky" id="bottomSticky">
    <div class="bs-left">
        <div class="bs-price">₹<?php echo format_number($officePrice); ?><?php echo $pricePeriod; ?></div>
        <div class="bs-seats"><?php echo fmt_seats((int)$officeSeats); ?> Seats</div>
    </div>
    <div class="bs-right">
        <button class="btn-call-sidebar" onclick="window.open('tel:+919962200015')"><i class="fa-solid fa-phone"></i> Call</button>
        <button class="btn-submit" onclick="scrollToForm()">Get Price</button>
    </div>
</div>

<!-- MOBILE BOTTOM BAR -->
<div class="mobile-bottom-bar">
    <div class="mob-bar-top">
        <div>
            <div class="mob-bar-price">₹<?php echo format_number($officePrice); ?><?php echo $pricePeriod; ?></div>
            <div class="mob-bar-seats"><?php echo fmt_seats((int)$officeSeats); ?> Seats | <?php echo htmlspecialchars($officeMoveIn); ?></div>
        </div>
    </div>
    <div class="mob-bar-actions">
        <button class="mob-call" onclick="window.open('tel:+919962200015')"><i class="fa-solid fa-phone"></i> Call</button>
        <button class="mob-enquire" onclick="scrollToForm()">Enquire Now</button>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox">
    <div class="lb-progress"><div class="lb-progress-fill" id="progressFill"></div></div>
    <div class="lb-top">
        <span class="lb-counter" id="lbCounter">1 / <?php echo (int)$totalImages; ?></span>
        <button class="lb-close" onclick="closeLightbox()" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="lb-slide-area" id="slideArea">
        <button class="lb-nav lb-prev" id="prevBtn" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="lb-nav lb-next" id="nextBtn" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="lb-slide-track" id="slideTrack"></div>
    </div>
    <div class="lb-thumbs" id="thumbStrip"></div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/site-nav.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/api-client.js"></script>
<script src="assets/js/toast.js"></script>
<script src="assets/js/realtime.js"></script>
<script src="assets/js/forms.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/lightbox.js"></script>
<script>
    function imgErrorToPlaceholder(img) {
        if (!img || !img.parentElement) return;
        img.parentElement.innerHTML = '<div class="placeholder-img"><i class="fa-solid fa-building"></i></div>';
    }

    function toggleMenu() {
        const nav = document.getElementById("mobileNav");
        nav.classList.toggle("active");
        const icon = document.querySelector(".mobile-menu i");
        if (icon) {
            icon.classList.toggle("fa-bars");
            icon.classList.toggle("fa-times");
        }
    }

    // Close mobile menu on link click
    document.querySelectorAll('.mobile-nav a').forEach(function(link) {
        link.addEventListener('click', function() {
            document.getElementById("mobileNav").classList.remove("active");
            const icon = document.querySelector(".mobile-menu i");
            if (icon) {
                icon.classList.remove("fa-times");
                icon.classList.add("fa-bars");
            }
    

});
    });
</script>
<script>
const officeImages = <?php echo json_encode(array_map('htmlspecialchars', $images)); ?>;
const officeId = <?php echo json_encode($officeId); ?>;

// Lightbox
if (typeof CubeSpaceLightbox !== 'undefined') {
    CubeSpaceLightbox.init(officeImages);
}

function openLightbox(i) {
    if (typeof CubeSpaceLightbox !== 'undefined') CubeSpaceLightbox.open(i);
}
function closeLightbox() {
    if (typeof CubeSpaceLightbox !== 'undefined') CubeSpaceLightbox.close();
}
</script>

<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fa-solid fa-circle-check text-success mb-3 d-none" id="alertIconSuccess" style="font-size: 2rem;"></i>
                <i class="fa-solid fa-circle-exclamation text-danger mb-3 d-none" id="alertIconError" style="font-size: 2rem;"></i>
                <i class="fa-solid fa-circle-info text-primary mb-3 d-none" id="alertIconInfo" style="font-size: 2rem;"></i>
                <p class="mb-0 fw-medium" id="alertMessage">Message</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button type="button" class="btn btn-primary btn-sm px-3" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
function showAlertModal(message, type) {
    type = type || 'info';
    document.getElementById('alertMessage').textContent = message;
    document.getElementById('alertIconSuccess').classList.add('d-none');
    document.getElementById('alertIconError').classList.add('d-none');
    document.getElementById('alertIconInfo').classList.add('d-none');
    document.getElementById('alertIcon' + type.charAt(0).toUpperCase() + type.slice(1)).classList.remove('d-none');
    var modalEl = document.getElementById('alertModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}
</script>
</body>
</html>
<?php
} catch (\Throwable $e) {
    ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error - CubeSpace</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Roboto,sans-serif;margin:0;background:#fafbff;"><div style="text-align:center;padding:40px;"><h1 style="font-size:28px;color:#c0392b;margin-bottom:12px;">Server Error</h1><p style="color:#64748b;font-size:16px;">' . htmlspecialchars($e->getMessage()) . '</p><pre style="color:#888;font-size:13px;margin-top:20px;">' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</pre></div></body></html>';
    exit;
}
?>
