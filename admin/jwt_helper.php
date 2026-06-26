<?php
if (!defined('JWT_SECRET')) {
    $secret = getenv('JWT_SECRET');
    if ($secret && strlen($secret) >= 16) {
        define('JWT_SECRET', $secret);
    }
}
if (!defined('JWT_SECRET')) {
    $configCandidates = [
        __DIR__ . '/../../config/jwt.php',
        __DIR__ . '/../../../config/jwt.php',
    ];
    foreach ($configCandidates as $configFile) {
        if (file_exists($configFile)) {
            require_once $configFile;
            break;
        }
    }
}
if (!defined('JWT_SECRET')) {
    error_log('CRITICAL: JWT_SECRET not properly configured');
    http_response_code(500);
    die(json_encode(['error' => 'Server configuration error']));
}
define('JWT_ALGO', 'sha256');
define('ACCESS_TOKEN_TTL', 900);      // 15 minutes
define('REFRESH_TOKEN_TTL', 604800);  // 7 days

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function jwt_encode($payload, $expiresIn) {
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + $expiresIn;
    $payloadEncoded = base64url_encode(json_encode($payload));
    $signature = base64url_encode(hash_hmac(JWT_ALGO, "$header.$payloadEncoded", JWT_SECRET, true));
    return "$header.$payloadEncoded.$signature";
}

function jwt_decode($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $signature] = $parts;
    $expectedSig = base64url_encode(hash_hmac(JWT_ALGO, "$header.$payload", JWT_SECRET, true));

    if (!hash_equals($expectedSig, $signature)) return null;

    $data = json_decode(base64url_decode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return null;

    return $data;
}

function generate_access_token($adminId, $username) {
    return jwt_encode([
        'sub' => (int)$adminId,
        'user' => $username,
        'type' => 'access'
    ], ACCESS_TOKEN_TTL);
}

function generate_refresh_token($adminId, $username) {
    return jwt_encode([
        'sub' => (int)$adminId,
        'user' => $username,
        'type' => 'refresh'
    ], REFRESH_TOKEN_TTL);
}

function is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function set_auth_cookies($accessToken, $refreshToken) {
    $secure = is_https();
    setcookie('access_token', $accessToken, [
        'expires' => time() + ACCESS_TOKEN_TTL,
        'path' => '/admin/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    setcookie('refresh_token', $refreshToken, [
        'expires' => time() + REFRESH_TOKEN_TTL,
        'path' => '/admin/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clear_auth_cookies() {
    $secure = is_https();
    setcookie('access_token', '', [
        'expires' => 1, 'path' => '/admin/', 'httponly' => true, 'secure' => $secure, 'samesite' => 'Lax'
    ]);
    setcookie('refresh_token', '', [
        'expires' => 1, 'path' => '/admin/', 'httponly' => true, 'secure' => $secure, 'samesite' => 'Lax'
    ]);
}

function get_jwt_from_header() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return $m[1];
    }
    return '';
}

function get_jwt_from_cookie() {
    return $_COOKIE['access_token'] ?? '';
}

function require_jwt_auth() {
    $token = get_jwt_from_header();
    if (!$token) {
        $token = get_jwt_from_cookie();
    }
    if (!$token && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    if (!$token) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
    $payload = jwt_decode($token);
    if (!$payload || ($payload['type'] ?? '') !== 'access') {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
    return $payload;
}

function require_admin_role($minRole = 'admin') {
    $payload = require_jwt_auth();
    $roleHierarchy = ['admin' => 0, 'super_admin' => 1];
    $userRole = $payload['role'] ?? 'admin';
    $requiredLevel = $roleHierarchy[$minRole] ?? 0;
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    if ($userLevel < $requiredLevel) {
        http_response_code(403);
        die(json_encode(['error' => 'Insufficient permissions']));
    }
    return $payload;
}

function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function log_app_error($message, $context = []) {
    $logDirs = [
        __DIR__ . '/../../logs',
        __DIR__ . '/../../../logs',
    ];
    $logDir = __DIR__ . '/../../logs';
    foreach ($logDirs as $dir) {
        if (is_dir($dir) || is_dir(dirname($dir))) {
            $logDir = $dir;
            break;
        }
    }
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
    $entry = date('c') . ' ' . $message;
    if ($context) {
        $entry .= ' ' . json_encode($context);
    }
    $entry .= PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
