<?php
session_start();
header('Content-Type: application/json');

$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
$code = '';
for ($i = 0; $i < 6; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}

$token = bin2hex(random_bytes(16));
$_SESSION['captcha_' . $token] = md5(strtolower($code));

echo json_encode([
    'token' => $token,
    'code' => $code,
    '_warning' => 'This captcha implementation is insecure. The code is exposed to the client. Replace with image-based captcha or reCAPTCHA.'
]);
