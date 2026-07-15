<?php
declare(strict_types=1);

define('MAIL_HOST', getenv('MAIL_HOST') ?: ($_ENV['MAIL_HOST'] ?? 'smtp.hostinger.com'));
define('MAIL_PORT', getenv('MAIL_PORT') ?: ($_ENV['MAIL_PORT'] ?? '465'));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: ($_ENV['MAIL_USERNAME'] ?? 'hafiz@cubespaces.in'));
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: ($_ENV['MAIL_PASSWORD'] ?? ''));
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: ($_ENV['MAIL_ENCRYPTION'] ?? 'ssl'));
define('MAIL_FROM', getenv('MAIL_FROM') ?: ($_ENV['MAIL_FROM'] ?? 'hafiz@cubespaces.in'));
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: ($_ENV['MAIL_FROM_NAME'] ?? 'CubeSpace'));
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: ($_ENV['ADMIN_EMAIL'] ?? 'hafiz@cubespaces.in'));
