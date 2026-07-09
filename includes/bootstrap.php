<?php
declare(strict_types=1);

/**
 * Application base path for URLs (empty string when site is at domain root).
 */
function app_base_path(): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($dir === '/' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

function app_url(string $path = ''): string {
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = app_base_path();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

if (!function_exists('cubespace_project_root')) {
function cubespace_project_root(): string {
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    $root = dirname(__DIR__);
    return $root;
}
}

if (!function_exists('cubespace_require_project')) {
function cubespace_require_project(string $relative): void {
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $paths = [
        cubespace_project_root() . '/' . $relative,
        dirname(__DIR__) . '/' . $relative,
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }

    error_log('CubeSpace: missing project file: ' . $relative);
    http_response_code(500);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Server configuration error']));
    }
    die('Server configuration error');
}
}

if (!function_exists('cubespace_project_path')) {
function cubespace_project_path(string $relative): string {
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $paths = [
        cubespace_project_root() . '/' . $relative,
        dirname(__DIR__) . '/' . $relative,
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    return cubespace_project_root() . '/' . $relative;
}
}

if (!function_exists('cubespace_load_db_config')) {
function cubespace_load_db_config(): void {
    global $conn;
    if (isset($conn) && $conn) {
        return;
    }

    $candidates = [
        __DIR__ . '/../api/db_config.php',
        __DIR__ . '/api/db_config.php',
        dirname(__DIR__) . '/api/db_config.php',
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }

    error_log('CubeSpace: db_config.php not found');
    http_response_code(500);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Server configuration error']));
    }
    die('Server configuration error');
}
}
