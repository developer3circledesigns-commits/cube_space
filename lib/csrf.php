<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

class CSRFManager {
    private const TOKEN_LENGTH = 32;
    
    public static function initialize(): void {
        secure_session_start();
    }
    
    public static function generateToken(): string {
        self::initialize();
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public static function validateToken(string $token): bool {
        self::initialize();
        
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function getTokenHeader(): string {
        return 'X-CSRF-Token';
    }
    
    public static function require(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || 
            $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return;
        }
        
        $token = self::getTokenFromRequest();
        
        if (!$token || !self::validateToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'error' => 'CSRF token invalid or missing'
            ]));
        }
    }
    
    private static function getTokenFromRequest(): string {
        // Check header first
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($headerToken) {
            return $headerToken;
        }
        
        // Check POST data
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $_POST['csrf_token'] ?? '';
        }
        
        // Check JSON body
        $json = json_decode(file_get_contents('php://input'), true);
        return $json['csrf_token'] ?? '';
    }
}
?>
