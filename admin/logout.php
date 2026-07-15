<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/jwt_helper.php';
admin_require_lib('session.php');
cubespace_require_project('src/autoload.php');
$adminUser = $_SESSION['admin_user'] ?? 'unknown';
clear_auth_cookies();
secure_session_start();
$_SESSION = [];
session_destroy();
try { (new \CubeSpace\EmailService())->notifyAdminAction('logout', 'admin panel', "Admin '$adminUser' logged out"); } catch (\Throwable $e) { error_log('Email notify: ' . $e->getMessage()); }
header('Location: ' . '/admin/');
exit;
