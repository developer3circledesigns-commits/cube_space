<?php
declare(strict_types=1);

namespace CubeSpace;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class EmailService {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromEmail;
    private string $fromName;
    private string $adminEmail;
    private bool $enabled;

    public function __construct() {
        $this->loadConfig();

        $this->enabled = $this->host !== '' && $this->host !== 'localhost';
    }

    private function loadConfig(): void {
        $candidates = [
            __DIR__ . '/../config/mail.php',
        ];
        foreach ($candidates as $file) {
            if (file_exists($file)) {
                require_once $file;
                break;
            }
        }

        $this->host = defined('MAIL_HOST') ? MAIL_HOST : (getenv('MAIL_HOST') ?: ($_ENV['MAIL_HOST'] ?? ''));
        $this->port = (int)(defined('MAIL_PORT') ? MAIL_PORT : (getenv('MAIL_PORT') ?: ($_ENV['MAIL_PORT'] ?? '465')));
        $this->username = defined('MAIL_USERNAME') ? MAIL_USERNAME : (getenv('MAIL_USERNAME') ?: ($_ENV['MAIL_USERNAME'] ?? ''));
        $this->password = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : (getenv('MAIL_PASSWORD') ?: ($_ENV['MAIL_PASSWORD'] ?? ''));
        $this->encryption = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : (getenv('MAIL_ENCRYPTION') ?: ($_ENV['MAIL_ENCRYPTION'] ?? 'ssl'));
        $this->fromEmail = defined('MAIL_FROM') ? MAIL_FROM : (getenv('MAIL_FROM') ?: ($_ENV['MAIL_FROM'] ?? 'noreply@cubespaces.in'));
        $this->fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (getenv('MAIL_FROM_NAME') ?: ($_ENV['MAIL_FROM_NAME'] ?? 'CubeSpace'));
        $this->adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : (getenv('ADMIN_EMAIL') ?: ($_ENV['ADMIN_EMAIL'] ?? 'hafiz@cubespaces.in'));
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function getAdminEmail(): string {
        return $this->adminEmail;
    }

    public function getFromEmail(): string {
        return $this->fromEmail;
    }

    private function createPHPMailer(): PHPMailer {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->Port = $this->port;
        $mail->SMTPAuth = $this->username !== '';
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        if ($this->username !== '') {
            $mail->Username = $this->username;
            $mail->Password = $this->password;
        }

        if ($this->encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($this->encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
        }

        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        $mail->setFrom($this->fromEmail, $this->fromName);

        return $mail;
    }

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): bool {
        if (!$this->enabled) {
            $this->logEmail("SMTP not configured. Would send to $to: $subject");
            return false;
        }

        try {
            $mail = $this->createPHPMailer();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $body;

            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }

            $mail->send();
            $this->logEmail("Sent to $to: $subject");
            return true;
        } catch (PHPMailerException $e) {
            $this->logEmail("Failed to send to $to: $subject - " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->logEmail("Failed to send to $to: $subject - " . $e->getMessage());
            return false;
        }
    }

    private function getInterestLabel(string $interest): string
    {
        $map = [
            'managed' => 'Managed Office',
            'furnished' => 'Furnished Office',
            'unfurnished' => 'Unfurnished Office',
            'commercial' => 'Furnished / Unfurnished Office',
        ];
        return $map[$interest] ?? ucfirst($interest);
    }

    public function notifyAdminNewContact(array $contact): bool
    {
        // Sanitize name for subject to avoid header injection
        $safeName = preg_replace('/[\r\n]+/', ' ', (string)($contact['name'] ?? 'Unknown'));
        $safeName = mb_substr(trim($safeName), 0, 80);
        if ($safeName === '') $safeName = 'Unknown';
        $subject = 'New Enquiry: ' . $safeName . ' - CubeSpace';
        // Build workspaces summary safely from selected_offices or fallback
        $workspacesSummary = $contact['workspaces_summary'] ?? '';
        if ($workspacesSummary === '' && !empty($contact['selected_offices']) && is_array($contact['selected_offices'])) {
            $lines = [];
            foreach ($contact['selected_offices'] as $idx => $off) {
                $t = preg_replace('/[\r\n]+/', ' ', (string)($off['title'] ?? 'Workspace'));
                $t = mb_substr(trim(strip_tags($t)), 0, 100);
                $code = preg_replace('/[\r\n]+/', '', (string)($off['listing_code'] ?? ''));
                $code = mb_substr(trim(strip_tags($code)), 0, 30);
                $type = preg_replace('/[^a-z_]/', '', strtolower((string)($off['listing_type'] ?? '')));
                $id = (int)($off['id'] ?? 0);
                $line = ($idx + 1) . '. ' . $t;
                if ($code !== '') $line .= ' [' . $code . ']';
                if ($id > 0) $line .= ' (ID: ' . $id . ', ' . $type . ')';
                $lines[] = $line;
            }
            $workspacesSummary = implode("\n", $lines);
        } else {
            // Sanitize existing summary
            $workspacesSummary = preg_replace('/[\r\n]+/', "\n", (string)$workspacesSummary);
            $workspacesSummary = mb_substr(trim($workspacesSummary), 0, 5000);
        }

        // For multi, office_id/listing_code are placeholders; show N/A or MULTI summary
        $officeIdDisplay = $contact['office_id'] ?? 'N/A';
        if (empty($officeIdDisplay) || $officeIdDisplay === 0) $officeIdDisplay = 'N/A';
        // If multi, show count hint
        $listingCodeDisplay = $contact['listing_code'] ?? 'N/A';
        if (($contact['source'] ?? '') === 'multi_select_enquiry' && !empty($contact['selected_offices'])) {
            $listingCodeDisplay = $listingCodeDisplay . ' (' . count($contact['selected_offices']) . ' workspaces)';
        }

        $body = $this->renderTemplate('admin-new-contact', [
            'name' => $contact['name'] ?? 'N/A',
            'phone' => $contact['phone'] ?? 'N/A',
            'email' => $contact['email'] ?? 'N/A',
            'interest' => $this->getInterestLabel($contact['interest'] ?? 'N/A'),
            'company' => $contact['company'] ?? 'N/A',
            'seats' => $contact['seats'] ?? 'N/A',
            'message' => $contact['message'] ?? 'N/A',
            'office_id' => $officeIdDisplay,
            'listing_code' => $listingCodeDisplay,
            'source' => $contact['source'] ?? 'N/A',
            'ip' => $contact['ip'] ?? 'N/A',
            'user_agent' => $contact['user_agent'] ?? 'N/A',
            'workspaces_summary' => $workspacesSummary !== '' ? $workspacesSummary : 'N/A',
        ]);

        $result = $this->send($this->adminEmail, $subject, $body);

        if (!$result) {
            $safeLogName = preg_replace('/[\r\n\t]+/', ' ', (string)($contact['name'] ?? 'Unknown'));
            $safeLogName = mb_substr(trim($safeLogName), 0, 60);
            $this->logEmail('New contact enquiry from ' . $safeLogName . ' (email failed)');
        }

        return $result;
    }

    public function notifyAdminNewPartner(array $partner): bool {
        $subject = 'New Partner Submission: ' . ($partner['establishment_name'] ?? 'Unknown') . ' - CubeSpace';
        $body = $this->renderTemplate('admin-new-partner', [
            'establishment_type' => $partner['establishment_type'] ?? 'N/A',
            'establishment_name' => $partner['establishment_name'] ?? 'N/A',
            'ownership_type' => $partner['ownership_type'] ?? 'N/A',
            'city' => $partner['city'] ?? 'N/A',
            'address' => $partner['address'] ?? 'N/A',
            'first_name' => $partner['first_name'] ?? 'N/A',
            'last_name' => $partner['last_name'] ?? 'N/A',
            'phone' => $partner['phone'] ?? 'N/A',
            'email' => $partner['email'] ?? 'N/A',
        ]);

        $result = $this->send($this->adminEmail, $subject, $body);

        if (!$result) {
            $this->logEmail('New partner submission from ' . ($partner['first_name'] ?? 'Unknown') . ' (email failed)');
        }

        return $result;
    }

    public function sendTest(string $to): bool {
        $subject = 'Test Email - CubeSpace SMTP Configuration';
        $body = '
            <h2>SMTP Test Successful!</h2>
            <p>Your Outlook SMTP configuration is working correctly.</p>
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Server</td><td style="padding:8px;border:1px solid #ddd;">' . $this->host . ':' . $this->port . '</td></tr>
                <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Encryption</td><td style="padding:8px;border:1px solid #ddd;">' . $this->encryption . '</td></tr>
                <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">From</td><td style="padding:8px;border:1px solid #ddd;">' . $this->fromEmail . '</td></tr>
                <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Time</td><td style="padding:8px;border:1px solid #ddd;">' . date('Y-m-d H:i:s') . '</td></tr>
            </table>
            <hr><p style="color:#888;font-size:12px;">CubeSpace - ' . date('Y-m-d H:i:s') . '</p>
        ';
        return $this->send($to, $subject, $body);
    }

    public function notifyAdminAction(string $action, string $entity, string $details, ?string $actor = null): bool {
        $actorStr = $actor ? " by $actor" : '';
        $subject = "Admin Action: $action $entity - CubeSpace";
        $detailsHtml = nl2br(htmlspecialchars($details));
        $body = "
            <h2>Admin Action Notification</h2>
            <table style=\"width:100%;border-collapse:collapse;\">
                <tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:600;\">Action</td><td style=\"padding:8px;border:1px solid #ddd;\">$action</td></tr>
                <tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:600;\">Entity</td><td style=\"padding:8px;border:1px solid #ddd;\">$entity</td></tr>
                <tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:600;\">Details</td><td style=\"padding:8px;border:1px solid #ddd;\">$detailsHtml</td></tr>
                <tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:600;\">Actor</td><td style=\"padding:8px;border:1px solid #ddd;\">$actorStr</td></tr>
                <tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:600;\">Time</td><td style=\"padding:8px;border:1px solid #ddd;\">" . date('Y-m-d H:i:s') . "</td></tr>
            </table>
            <hr><p style=\"color:#888;font-size:12px;\">CubeSpace - Admin Notification</p>
        ";
        return $this->send($this->adminEmail, $subject, $body);
    }

    public function notifyPartnerApproved(array $partner): bool {
        if (empty($partner['email'])) {
            return false;
        }

        $subject = 'Your Space Listing is Approved - CubeSpace';
        $body = $this->renderTemplate('partner-approved', [
            'name' => ($partner['first_name'] ?? '') . ' ' . ($partner['last_name'] ?? ''),
            'establishment_name' => $partner['establishment_name'] ?? '',
        ]);

        return $this->send($partner['email'], $subject, $body, $this->adminEmail);
    }

    private function renderTemplate(string $template, array $vars): string {
        $body = $this->getTemplateHtml($template);
        foreach ($vars as $key => $value) {
            $body = str_replace('{{' . $key . '}}', htmlspecialchars((string)$value), $body);
        }
        return $body;
    }

    private function getTemplateHtml(string $template): string {
        $templates = [
            'admin-new-contact' => '
                <h2>New Contact Enquiry</h2>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Name</td><td style="padding:8px;border:1px solid #ddd;">{{name}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Phone</td><td style="padding:8px;border:1px solid #ddd;">{{phone}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Email</td><td style="padding:8px;border:1px solid #ddd;">{{email}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Interest</td><td style="padding:8px;border:1px solid #ddd;">{{interest}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Company</td><td style="padding:8px;border:1px solid #ddd;">{{company}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Seats Required</td><td style="padding:8px;border:1px solid #ddd;">{{seats}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Office ID</td><td style="padding:8px;border:1px solid #ddd;">{{office_id}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Listing Code</td><td style="padding:8px;border:1px solid #ddd;">{{listing_code}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;vertical-align:top;">Selected Workspaces</td><td style="padding:8px;border:1px solid #ddd;white-space:pre-line;">{{workspaces_summary}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Source</td><td style="padding:8px;border:1px solid #ddd;">{{source}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">IP Address</td><td style="padding:8px;border:1px solid #ddd;">{{ip}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">User Agent</td><td style="padding:8px;border:1px solid #ddd;word-break:break-all;">{{user_agent}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Message</td><td style="padding:8px;border:1px solid #ddd;">{{message}}</td></tr>
                </table>
                <p style="margin-top:20px;color:#888;font-size:12px;">View in admin: <a href="https://cubespaces.in/admin/?page=contacts">cubespaces.in/admin</a></p>
            ',
            'admin-new-partner' => '
                <h2>New Partner Submission</h2>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Establishment Type</td><td style="padding:8px;border:1px solid #ddd;">{{establishment_type}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Establishment Name</td><td style="padding:8px;border:1px solid #ddd;">{{establishment_name}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Ownership</td><td style="padding:8px;border:1px solid #ddd;">{{ownership_type}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">City</td><td style="padding:8px;border:1px solid #ddd;">{{city}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Address</td><td style="padding:8px;border:1px solid #ddd;">{{address}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Contact</td><td style="padding:8px;border:1px solid #ddd;">{{first_name}} {{last_name}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Phone</td><td style="padding:8px;border:1px solid #ddd;">{{phone}}</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Email</td><td style="padding:8px;border:1px solid #ddd;">{{email}}</td></tr>
                </table>
                <p style="margin-top:20px;color:#888;font-size:12px;">View in admin: <a href="https://cubespaces.in/admin/?page=list-space">cubespaces.in/admin</a></p>
            ',
            'partner-approved' => '
                <h2>Your Space Listing is Approved!</h2>
                <p>Dear {{name}},</p>
                <p>Your space listing <strong>{{establishment_name}}</strong> has been approved on CubeSpace.</p>
                <p>Your space is now visible to potential tenants looking for commercial real estate.</p>
                <p>If you have any questions, feel free to reach out to us.</p>
                <p>Best regards,<br>CubeSpace Team</p>
            ',
        ];

        return $templates[$template] ?? '<p>No template found.</p>';
    }

    private function logEmail(string $message): void {
        // Sanitize to prevent log injection: strip newlines, control chars, limit length
        $sanitized = preg_replace('/[\r\n\t]+/', ' ', $message);
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $sanitized);
        $sanitized = mb_substr(trim($sanitized), 0, 500);
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/mail_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, date('c') . ' ' . $sanitized . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
