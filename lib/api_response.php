<?php
declare(strict_types=1);

function json_success($data = null, string $message = 'OK', int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
        'errors'  => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, $errors = null, int $status = 400): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data'    => null,
        'errors'  => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_response(bool $success, string $message = '', $data = null, $errors = null, int $status = 200): void {
    $success ? json_success($data, $message, $status) : json_error($message, $errors, $status);
}
