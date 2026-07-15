<?php
/**
 * SMTP Test Script - Run from command line: php test-smtp.php
 * Tests the Hostinger SMTP configuration and sends a test email.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/dotenv.php';
load_env(__DIR__ . '/.env');
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "=== CubeSpace SMTP Test ===\n\n";
echo "Configuration:\n";
echo "  Host:       " . MAIL_HOST . "\n";
echo "  Port:       " . MAIL_PORT . "\n";
echo "  Username:   " . MAIL_USERNAME . "\n";
echo "  Password:   " . (MAIL_PASSWORD ? str_repeat('*', strlen(MAIL_PASSWORD)) : 'NOT SET') . "\n";
echo "  Encryption: " . MAIL_ENCRYPTION . "\n";
echo "  From:       " . MAIL_FROM . " (" . MAIL_FROM_NAME . ")\n";
echo "  Admin To:   " . ADMIN_EMAIL . "\n\n";

if (empty(MAIL_PASSWORD)) {
    echo "ERROR: MAIL_PASSWORD is empty! Set it in .env file.\n";
    exit(1);
}

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->Port = (int)MAIL_PORT;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    if (MAIL_ENCRYPTION === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif (MAIL_ENCRYPTION === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
    $mail->Debugoutput = function ($str, $level) {
        echo "  [DEBUG] $str\n";
    };

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(ADMIN_EMAIL);
    $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

    $mail->Subject = 'Test Email - CubeSpace SMTP Configuration';
    $mail->isHTML(true);
    $mail->Body = '
        <h2>SMTP Test Successful!</h2>
        <p>Your Hostinger SMTP configuration is working correctly.</p>
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Server</td><td style="padding:8px;border:1px solid #ddd;">' . MAIL_HOST . ':' . MAIL_PORT . '</td></tr>
            <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Encryption</td><td style="padding:8px;border:1px solid #ddd;">' . MAIL_ENCRYPTION . '</td></tr>
            <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">From</td><td style="padding:8px;border:1px solid #ddd;">' . MAIL_FROM . '</td></tr>
            <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Sent To</td><td style="padding:8px;border:1px solid #ddd;">' . ADMIN_EMAIL . '</td></tr>
            <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Time</td><td style="padding:8px;border:1px solid #ddd;">' . date('Y-m-d H:i:s') . '</td></tr>
        </table>
        <hr><p style="color:#888;font-size:12px;">CubeSpace - ' . date('Y-m-d H:i:s') . '</p>
    ';
    $mail->AltBody = 'SMTP test successful. Server: ' . MAIL_HOST . ', From: ' . MAIL_FROM . ', To: ' . ADMIN_EMAIL;

    echo "Connecting and sending...\n\n";
    $mail->send();
    echo "\n✓ SUCCESS: Test email sent to " . ADMIN_EMAIL . "\n";
    exit(0);

} catch (Exception $e) {
    echo "\n✗ FAILED: " . $e->getMessage() . "\n\n";
    echo "Troubleshooting tips:\n";
    echo "  1. Verify MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD in .env\n";
    echo "  2. For Hostinger: host=smtp.hostinger.com, port=465, encryption=ssl\n";
    echo "  3. Ensure MAIL_PASSWORD is set in .env file\n";
    echo "  4. Check Hostinger email account credentials\n";
    exit(1);
}
