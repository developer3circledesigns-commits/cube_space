<?php
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/../lib/session.php';
clear_auth_cookies();
secure_session_start();
$_SESSION = [];
session_destroy();
header('Location: /admin/');
exit;
