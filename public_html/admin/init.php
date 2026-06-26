<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

function admin_load_db(): void {
    cubespace_load_db_config();
}

function admin_require_lib(string $file): void {
    cubespace_require_project('lib/' . ltrim($file, '/'));
}

function admin_load_api_db(): void {
    global $conn;
    if (isset($conn) && $conn) {
        return;
    }
    cubespace_load_db_config();
}

function admin_uploads_dir(string $sub = 'listings'): string {
    $paths = [
        dirname(__DIR__) . '/uploads/' . $sub,
        cubespace_project_root() . '/uploads/' . $sub,
    ];

    foreach ($paths as $path) {
        $parent = dirname($path);
        if (is_dir($parent) || is_dir(dirname($parent))) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            return rtrim($path, '/') . '/';
        }
    }

    return dirname(__DIR__) . '/uploads/' . $sub . '/';
}

function admin_resolve_upload_path(string $webPath): string {
    $webPath = '/' . ltrim($webPath, '/');
    if (str_starts_with($webPath, '/uploads/')) {
        return dirname(__DIR__) . $webPath;
    }
    return cubespace_project_root() . $webPath;
}
