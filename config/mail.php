<?php
declare(strict_types=1);

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.hostinger.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: '465');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'hafiz@cubespaces.in');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'ssl');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'hafiz@cubespaces.in');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'CubeSpace');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'hafiz@cubespaces.in');
