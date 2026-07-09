<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/jwt_helper.php';
admin_require_lib('session.php');
clear_auth_cookies();
secure_session_start();
$_SESSION = [];
session_destroy();
header('Location: ' . '/admin/');
exit;
