<?php
/**
 * Image Compression Script for Managed Offices & Furnished Offices
 * Compresses all images in the database:
 *   - Images under 5MB: recompress to reduce file size
 *   - Images over 5MB: resize + recompress to get under 5MB
 * Run on the production server: php compress_images.php
 */
set_error_handler(function ($severity, $message, $file, $line) {
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return false;
    }
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/includes/bootstrap.php';
cubespace_load_db_config();

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    die("Database connection failed.\n");
}

if (!extension_loaded('gd')) {
    die("GD extension is not available.\n");
}

define('MAX_IMAGE_SIZE_BYTES', 5 * 1024 * 1024);
define('JPEG_QUALITY', 82);
define('MIN_SIZE_TO_COMPRESS', 40 * 1024);
define('MAX_DIMENSION', 2400);

$tables = [
    'managed_offices',
    'furnished_offices',
];

$stats = [
    'total_images' => 0,
    'compressed' => 0,
    'resized_over_limit' => 0,
    'skipped_already_small' => 0,
    'skipped_non_base64' => 0,
    'errors' => 0,
];

echo "=== Image Compression Started ===\n";
echo "Max output size: " . (MAX_IMAGE_SIZE_BYTES / 1024 / 1024) . "MB\n";
echo "JPEG compression quality: " . JPEG_QUALITY . "\n";
echo "Max dimension cap: " . MAX_DIMENSION . "px\n\n";

foreach ($tables as $table) {
    echo "Processing table: $table\n";

    $stmt = mysqli_prepare($conn, "SELECT id, images FROM `$table` WHERE images IS NOT NULL AND images != '' AND status='active'");
    if (!$stmt) {
        echo "  Error preparing statement: " . mysqli_error($conn) . "\n";
        continue;
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $id = (int)$row['id'];
        $imagesJson = $row['images'];
        if (!$imagesJson) continue;

        $images = json_decode($imagesJson, true);
        if (!is_array($images)) continue;

        $modified = false;
        foreach ($images as $idx => $imageData) {
            if (!is_string($imageData) || trim($imageData) === '') continue;
            $stats['total_images']++;

            if (strpos($imageData, 'data:') !== 0) {
                $stats['skipped_non_base64']++;
                continue;
            }

            $binaryData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
            if ($binaryData === false || $binaryData === '') {
                $stats['errors']++;
                continue;
            }

            $originalSize = strlen($binaryData);

            // Detect image type
            if (!preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64;#i', $imageData, $m)) {
                $stats['skipped_non_base64']++;
                continue;
            }
            $srcType = strtolower($m[1]);
            if ($srcType === 'jpg') $srcType = 'jpeg';

            $imageResource = @imagecreatefromstring($binaryData);
            if (!$imageResource) {
                $stats['errors']++;
                continue;
            }

            $origWidth = imagesx($imageResource);
            $origHeight = imagesy($imageResource);

            // Determine if resizing is needed
            $needResize = false;
            $targetWidth = $origWidth;
            $targetHeight = $origHeight;

            // Cap dimensions at MAX_DIMENSION
            if ($origWidth > MAX_DIMENSION || $origHeight > MAX_DIMENSION) {
                $needResize = true;
                if ($origWidth / $origHeight >= MAX_DIMENSION / MAX_DIMENSION) {
                    $targetWidth = MAX_DIMENSION;
                    $targetHeight = (int)($origHeight * (MAX_DIMENSION / $origWidth));
                } else {
                    $targetHeight = MAX_DIMENSION;
                    $targetWidth = (int)($origWidth * (MAX_DIMENSION / $origHeight));
                }
            }

            // For images already under 40KB and no resize needed, skip
            if (!$needResize && $originalSize < MIN_SIZE_TO_COMPRESS) {
                $stats['skipped_already_small']++;
                imagedestroy($imageResource);
                continue;
            }

            // Create output image resource
            $outResource = $imageResource;
            if ($needResize) {
                $outResource = imagecreatetruecolor($targetWidth, $targetHeight);
                $white = imagecolorallocate($outResource, 255, 255, 255);
                imagefill($outResource, 0, 0, $white);
                imagecopyresampled($outResource, $imageResource, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);
                imagedestroy($imageResource);
            }

            // Check for alpha transparency
            $hasAlpha = false;
            if ($srcType === 'png' || $srcType === 'gif') {
                if (imagecolortransparent($outResource) >= 0) {
                    $hasAlpha = true;
                }
            }

            // Output compressed image
            ob_start();
            if ($hasAlpha || $srcType === 'png' || $srcType === 'gif') {
                imagealphablending($outResource, false);
                imagesavealpha($outResource, true);
                imagepng($outResource, null, 6);
                $compressedData = ob_get_clean();
                $outType = 'png';
            } else {
                // Convert to JPEG for smaller size
                $tmp = imagecreatetruecolor($targetWidth, $targetHeight);
                $white = imagecolorallocate($tmp, 255, 255, 255);
                imagefill($tmp, 0, 0, $white);
                imagecopy($tmp, $outResource, 0, 0, 0, 0, $targetWidth, $targetHeight);
                imagedestroy($outResource);
                imagejpeg($tmp, null, JPEG_QUALITY);
                $compressedData = ob_get_clean();
                imagedestroy($tmp);
                $outType = 'jpeg';
            }

            if (!$compressedData || strlen($compressedData) === 0) {
                $stats['errors']++;
                continue;
            }

            // Update if: file size reduced, or was over 5MB and now under, or dimensions changed
            $sizeReduced = strlen($compressedData) < $originalSize * 0.95;
            $wasOverLimit = $originalSize > MAX_IMAGE_SIZE_BYTES && strlen($compressedData) <= MAX_IMAGE_SIZE_BYTES;
            $dimensionsChanged = $needResize;

            if ($sizeReduced || $wasOverLimit || $dimensionsChanged) {
                $mimePrefix = $outType === 'jpeg' ? 'jpeg' : $outType;
                $newBase64 = 'data:image/' . $mimePrefix . ';base64,' . base64_encode($compressedData);
                $images[$idx] = $newBase64;
                $modified = true;
                if ($wasOverLimit) {
                    $stats['resized_over_limit']++;
                } else {
                    $stats['compressed']++;
                }
            } else {
                $stats['skipped_already_small']++;
            }
        }

        if ($modified) {
            $newImagesJson = json_encode($images, JSON_UNESCAPED_SLASHES);
            $updateStmt = mysqli_prepare($conn, "UPDATE `$table` SET images = ? WHERE id = ?");
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'si', $newImagesJson, $id);
                if (!mysqli_stmt_execute($updateStmt)) {
                    $stats['errors']++;
                    echo "  Error updating ID $id: " . mysqli_stmt_error($updateStmt) . "\n";
                }
                mysqli_stmt_close($updateStmt);
            } else {
                $stats['errors']++;
            }
        }
    }
    mysqli_stmt_close($stmt);
    echo "  Done.\n\n";
}

echo "=== Compression Complete ===\n";
echo "Total images scanned: {$stats['total_images']}\n";
echo "Images recompressed: {$stats['compressed']}\n";
echo "Images resized (was >5MB): {$stats['resized_over_limit']}\n";
echo "Skipped (already small): {$stats['skipped_already_small']}\n";
echo "Skipped (non-base64): {$stats['skipped_non_base64']}\n";
echo "Errors: {$stats['errors']}\n";

mysqli_close($conn);
echo "Done.\n";