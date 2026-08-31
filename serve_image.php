<?php
require_once __DIR__ . '/includes/bootstrap.php';
cubespace_load_db_config();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect fill="#eee" width="400" height="300"/><text fill="#aaa" font-size="16" x="50%" y="50%" text-anchor="middle" dy=".3em">No Image</text></svg>';
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT image_data, image_mime FROM listing_images WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    http_response_code(404);
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect fill="#eee" width="400" height="300"/><text fill="#aaa" font-size="16" x="50%" y="50%" text-anchor="middle" dy=".3em">Image Not Found</text></svg>';
    exit;
}

$maxAge = 365 * 86400;
header('Content-Type: ' . $row['image_mime']);
header('Content-Length: ' . strlen($row['image_data']));
header('Cache-Control: public, max-age=' . $maxAge);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
echo $row['image_data'];
