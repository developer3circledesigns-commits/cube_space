<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/db_config.php';

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $raw = file_get_contents('php://input');
    $pageUrl = '';
    $activity = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
        $pageUrl = $_POST['url'] ?? '';
        $activity = $_POST['activity'] ?? '';
    } elseif ($raw !== '') {
        parse_str($raw, $parsed);
        $pageUrl = $parsed['url'] ?? '';
        $activity = $parsed['activity'] ?? '';
    }
    if ($pageUrl === '') {
        $pageUrl = $_SERVER['HTTP_REFERER'] ?? '';
    }

    $isVpn = 0;
    $vpnMethod = null;

    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $via = $_SERVER['HTTP_VIA'] ?? '';
    $clientIp = $_SERVER['HTTP_CLIENT_IP'] ?? '';
    $xRealIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    $proxyConnection = $_SERVER['HTTP_PROXY_CONNECTION'] ?? '';

    if ($forwardedFor !== '' || $via !== '' || $clientIp !== '' || $proxyConnection !== '') {
        $isVpn = 1;
        if ($forwardedFor !== '') $vpnMethod = 'X-Forwarded-For';
        elseif ($via !== '') $vpnMethod = 'Via';
        elseif ($clientIp !== '') $vpnMethod = 'Client-IP';
        elseif ($proxyConnection !== '') $vpnMethod = 'Proxy-Connection';
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO visitors_log (ip_address, user_agent, page_url, activity, is_vpn, vpn_detected_method, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ssssis', $ip, $userAgent, $pageUrl, $activity, $isVpn, $vpnMethod);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_close($conn);

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    error_log('CubeSpace track_visit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
