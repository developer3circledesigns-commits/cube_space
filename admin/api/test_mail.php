<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/init.php';
cubespace_require_project('src/autoload.php');

$to = trim($_POST['to'] ?? '');
if (!$to) {
    $to = ADMIN_EMAIL;
}

try {
    $mail = new \CubeSpace\EmailService();

    if (!$mail->isEnabled()) {
        echo json_encode([
            'success' => false,
            'error' => 'Mail is not configured. Set MAIL_HOST and MAIL_PASSWORD in .env',
        ]);
        exit;
    }

    $result = $mail->sendTest($to);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "Test email sent to $to successfully. Check the inbox (and spam folder).",
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send test email. Check logs/mail_*.log for details.',
        ]);
    }
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
    ]);
}
