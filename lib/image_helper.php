<?php
declare(strict_types=1);

/**
 * Shared image helper for uploads-first + DB fallback strategy.
 * Used by admin listing_crud and public APIs.
 */

function cubespace_mime_to_ext(string $mime): string {
    $map = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $mime = strtolower(trim($mime));
    return $map[$mime] ?? 'jpg';
}

function cubespace_uploads_listings_dir(): string {
    // Prefer admin_uploads_dir if available
    if (function_exists('admin_uploads_dir')) {
        $dir = admin_uploads_dir('listings');
    } elseif (function_exists('cubespace_project_root')) {
        $dir = cubespace_project_root() . '/uploads/listings/';
    } else {
        $dir = dirname(__DIR__) . '/uploads/listings/';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return rtrim($dir, '/') . '/';
}

function cubespace_uploads_web_path(string $filename): string {
    return '/uploads/listings/' . ltrim($filename, '/');
}

function cubespace_filesystem_path_from_web(string $webPath): string {
    $webPath = '/' . ltrim($webPath, '/');
    if (str_starts_with($webPath, '/uploads/')) {
        if (function_exists('cubespace_project_root')) {
            return cubespace_project_root() . $webPath;
        }
        return dirname(__DIR__) . $webPath;
    }
    // fallback
    if (function_exists('cubespace_project_root')) {
        return cubespace_project_root() . $webPath;
    }
    return dirname(__DIR__) . $webPath;
}

function cubespace_parse_db_image_id(string $url): int {
    if (str_contains($url, 'serve_image.php')) {
        $q = parse_url($url, PHP_URL_QUERY);
        if ($q) {
            parse_str($q, $params);
            return (int)($params['id'] ?? 0);
        }
    }
    return 0;
}

function cubespace_extract_db_id_from_upload_path(string $webPath): int {
    // expects pattern *_<id>.<ext>
    if (preg_match('/_(\d+)\.(jpg|jpeg|png|webp|gif)(?:\?.*)?$/i', $webPath, $m)) {
        return (int)$m[1];
    }
    return 0;
}

function cubespace_find_file_for_image_id(string $listingType, int $listingId, int $imageId): ?string {
    $dir = cubespace_uploads_listings_dir();
    $pattern = $dir . $listingType . '_' . $listingId . '_' . $imageId . '.*';
    $matches = glob($pattern);
    if ($matches && is_array($matches)) {
        foreach ($matches as $match) {
            if (is_file($match)) {
                return $match;
            }
        }
    }
    return null;
}

function cubespace_write_image_file(string $data, string $mime, string $listingType, int $listingId, int $imageId): ?string {
    $dir = cubespace_uploads_listings_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ext = cubespace_mime_to_ext($mime);
    $filename = $listingType . '_' . $listingId . '_' . $imageId . '.' . $ext;
    $fsPath = $dir . $filename;
    $written = @file_put_contents($fsPath, $data);
    if ($written === false) {
        error_log("[image_helper] failed to write file $fsPath");
        return null;
    }
    @chmod($fsPath, 0644);
    return cubespace_uploads_web_path($filename);
}

function cubespace_materialize_db_image_to_file(mysqli $conn, string $listingType, int $listingId, int $imageId): ?string {
    // Return web path if materialized or already exists, null on failure
    $existing = cubespace_find_file_for_image_id($listingType, $listingId, $imageId);
    if ($existing) {
        return cubespace_uploads_web_path(basename($existing));
    }
    // Fetch from DB
    $stmt = mysqli_prepare($conn, "SELECT image_data, image_mime FROM listing_images WHERE id = ?");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $imageId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row || empty($row['image_data'])) return null;
    $data = $row['image_data'];
    $mime = $row['image_mime'] ?? 'image/jpeg';
    return cubespace_write_image_file($data, $mime, $listingType, $listingId, $imageId);
}

function cubespace_materialize_images_array(mysqli $conn, string $listingType, int $listingId, array $images): array {
    $result = [];
    foreach ($images as $url) {
        if (!is_string($url) || trim($url) === '') continue;
        $dbId = cubespace_parse_db_image_id($url);
        if ($dbId > 0) {
            $webPath = cubespace_materialize_db_image_to_file($conn, $listingType, $listingId, $dbId);
            if ($webPath && file_exists(cubespace_filesystem_path_from_web($webPath))) {
                $result[] = $webPath;
            } else {
                // fallback to original DB url if materialization failed
                $result[] = $url;
            }
        } elseif (str_starts_with($url, '/uploads/listings/')) {
            // Already file path — keep as is, ensure file exists; if missing try fallback via extracted id
            $fs = cubespace_filesystem_path_from_web($url);
            if (file_exists($fs)) {
                $result[] = $url;
            } else {
                $fid = cubespace_extract_db_id_from_upload_path($url);
                if ($fid > 0) {
                    // Try to re-materialize
                    $wp = cubespace_materialize_db_image_to_file($conn, $listingType, $listingId, $fid);
                    if ($wp && file_exists(cubespace_filesystem_path_from_web($wp))) {
                        $result[] = $wp;
                    } else {
                        $result[] = '/serve_image.php?id=' . $fid;
                    }
                } else {
                    // Keep original; client-side will show placeholder if missing
                    $result[] = $url;
                }
            }
        } else {
            // Legacy /uploads/... or external URL — check existence for uploads, otherwise keep
            $isUploads = str_starts_with($url, '/uploads/');
            if ($isUploads) {
                $fs = cubespace_filesystem_path_from_web($url);
                if (file_exists($fs)) {
                    $result[] = $url;
                } else {
                    // No DB fallback available, keep original (will become placeholder on frontend)
                    $result[] = $url;
                }
            } else {
                // External URL or relative — keep
                $result[] = $url;
            }
        }
    }
    return $result;
}

function cubespace_resolve_display_images(mysqli $conn, string $listingType, int $listingId, string $imagesJson): array {
    if ($imagesJson === null || $imagesJson === '') return [];
    $images = json_decode($imagesJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($images)) return [];
    $images = array_values(array_filter($images, fn($v) => is_string($v) && trim($v) !== ''));
    if (empty($images)) return [];

    $resolved = [];
    foreach ($images as $url) {
        $dbId = cubespace_parse_db_image_id($url);
        if ($dbId > 0) {
            // Serve_image URL -> check if file version exists
            $file = cubespace_find_file_for_image_id($listingType, $listingId, $dbId);
            if ($file && file_exists($file)) {
                $resolved[] = cubespace_uploads_web_path(basename($file));
            } else {
                // Lazy materialize attempt (non-blocking best effort)
                $wp = cubespace_materialize_db_image_to_file($conn, $listingType, $listingId, $dbId);
                if ($wp && file_exists(cubespace_filesystem_path_from_web($wp))) {
                    $resolved[] = $wp;
                } else {
                    // Fallback to DB URL
                    $resolved[] = $url;
                }
            }
        } elseif (str_starts_with($url, '/uploads/listings/')) {
            $fs = cubespace_filesystem_path_from_web($url);
            if (file_exists($fs)) {
                $resolved[] = $url;
            } else {
                // File missing -> try DB fallback if id extractable
                $fid = cubespace_extract_db_id_from_upload_path($url);
                if ($fid > 0) {
                    $stmt = mysqli_prepare($conn, "SELECT id FROM listing_images WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'i', $fid);
                        mysqli_stmt_execute($stmt);
                        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                        mysqli_stmt_close($stmt);
                        if ($r) {
                            $resolved[] = '/serve_image.php?id=' . $fid;
                            continue;
                        }
                    }
                }
                // Keep original (will fallback client-side to placeholder or DB if possible)
                $resolved[] = $url;
            }
        } else {
            // Check uploads existence for other uploads paths
            if (str_starts_with($url, '/uploads/')) {
                $fs = cubespace_filesystem_path_from_web($url);
                if (file_exists($fs)) {
                    $resolved[] = $url;
                } else {
                    // Try to keep original; frontend will handle fallback
                    $resolved[] = $url;
                }
            } else {
                $resolved[] = $url;
            }
        }
    }
    return array_values(array_filter($resolved, fn($v) => is_string($v) && trim($v) !== ''));
}

function cubespace_delete_image_file_by_webpath(string $webPath): void {
    $fs = cubespace_filesystem_path_from_web($webPath);
    if (is_file($fs)) {
        @unlink($fs);
    }
}

function cubespace_delete_files_for_image_id(string $listingType, int $listingId, int $imageId): void {
    $file = cubespace_find_file_for_image_id($listingType, $listingId, $imageId);
    if ($file && is_file($file)) {
        @unlink($file);
    }
}
