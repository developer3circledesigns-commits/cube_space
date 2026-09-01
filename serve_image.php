<?php
require_once __DIR__ . '/includes/bootstrap.php';
cubespace_load_db_config();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-store, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect fill="#eee" width="400" height="300"/><text fill="#aaa" font-size="16" x="50%" y="50%" text-anchor="middle" dy=".3em">No Image</text></svg>';
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT image_data, image_mime FROM listing_images WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-store, must-revalidate');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect fill="#eee" width="400" height="300"/><text fill="#aaa" x="50%" y="50%" text-anchor="middle">DB Error</text></svg>';
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    http_response_code(404);
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-store, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    @file_put_contents(__DIR__ . '/logs/image_404.log', date('c')." 404 id=$id IP=".($_SERVER['REMOTE_ADDR']??'')."\n", FILE_APPEND);
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect fill="#eee" width="400" height="300"/><text fill="#aaa" font-size="16" x="50%" y="50%" text-anchor="middle" dy=".3em">Image Not Found</text></svg>';
    exit;
}

$maxAge = 365 * 86400;
$data = $row['image_data'];
$mime = $row['image_mime'] ?: 'image/jpeg';
$etag = sprintf('"%x-%x"', crc32($mime), strlen($data));
$lastMod = gmdate('D, d M Y H:i:s', time()) . ' GMT';

// Handle If-None-Match / If-Modified-Since
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=' . $maxAge . ', immutable');
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($data));
header('Cache-Control: public, max-age=' . $maxAge . ', immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastMod);
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}
echo $data;
