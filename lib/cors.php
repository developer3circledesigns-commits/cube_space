<?php
declare(strict_types=1);

function set_cors_headers(string $allowedMethods = 'GET, OPTIONS'): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'https://cubespaces.in',
        'https://www.cubespaces.in',
        'https://sandybrown-goshawk-839799.hostingersite.com',
        'http://localhost:8080',
        'http://localhost',
    ];
    if ($origin && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: https://cubespaces.in');
    }
    header('Access-Control-Allow-Methods: ' . $allowedMethods);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}