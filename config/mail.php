<?php
declare(strict_types=1);

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: '587');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'developer3.circledesigns@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'developer3.circledesigns@gmail.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'CubeSpace');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'developer3.circledesigns@gmail.com');
