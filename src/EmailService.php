<?php
declare(strict_types=1);

namespace CubeSpace;

class EmailService {
    private string $fromEmail;
    private string $fromName;
    private string $adminEmail;
    private bool $enabled;

    public function __construct() {
        $this->fromEmail = getenv('MAIL_FROM') ?: 'noreply@cubespace.in';
        $this->fromName = 'CubeSpace';
        $this->adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@cubespace.in';
        $host = getenv('MAIL_HOST');

        if (empty($host)) {
            $configCandidates = [
                __DIR__ . '/../config/mail.php',
                __DIR__ . '/../../config/mail.php',
            ];
            foreach ($configCandidates as $configFile) {
                if (file_exists($configFile)) {
                    require_once $configFile;
                    $host = defined('MAIL_HOST') ? MAIL_HOST : '';
                    if (defined('MAIL_FROM')) {
                        $this->fromEmail = MAIL_FROM;
                    }
                    if (defined('ADMIN_EMAIL')) {
                        $this->adminEmail = ADMIN_EMAIL;
                    }
                    break;
                }
            }
        }

        $this->enabled = !empty($host);
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function getAdminEmail(): string {
        return $this->adminEmail;
    }

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): bool {
        if (!$this->enabled) {
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'X-Mailer: CubeSpace Mailer',
        ];

        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    public function notifyAdminNewContact(array $contact): bool {
        if (!$this->enabled) {
            $this->logEmail('New contact enquiry from ' . ($contact['name'] ?? 'Unknown'));
            return false;
        }

        $subject = 'New Enquiry: ' . ($contact['name'] ?? 'Unknown') . ' - CubeSpace';
        $body = $this->renderTemplate('admin-new-contact', [
            'name' => $contact['name'] ?? 'N/A',
            'phone' => $contact['phone'] ?? 'N/A',
            'email' => $contact['email'] ?? 'N/A',
            'interest' => $contact['interest'] ?? 'N/A',
            'company' => $contact['company'] ?? 'N/A',
            'seats' => $contact['seats'] ?? 'N/A',
            'message' => $contact['message'] ?? 'N/A',
        ]);

        return $this->send($this->adminEmail, $subject, $body);
    }

    public function notifyAdminNewPartner(array $partner): bool {
        if (!$this->enabled) {
            $this->logEmail('New partner submission from ' . ($partner['first_name'] ?? 'Unknown'));
            return false;
        }

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

        return $this->send($this->adminEmail, $subject, $body);
    }

    public function notifyPartnerApproved(array $partner): bool {
        if (!$this->enabled || empty($partner['email'])) {
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
                    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:600;">Seats</td><td style="padding:8px;border:1px solid #ddd;">{{seats}}</td></tr>
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
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/mail_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, date('c') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
