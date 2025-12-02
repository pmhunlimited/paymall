<?php
// includes/Email.php

require_once __DIR__ . '/../config/settings.php';

class Email {
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $from_email;
    private $from_name;

    public function __construct() {
        // Load SMTP settings from admin config
        $this->smtp_host = setting('smtp_host', 'smtp.gmail.com');
        $this->smtp_port = (int)setting('smtp_port', 587);
        $this->smtp_user = setting('smtp_user', '');
        $this->smtp_pass = setting('smtp_pass', '');
        $this->from_email = setting('admin_email', 'noreply@yourdomain.com');
        $this->from_name = setting('site_name', 'VTU Fintech');
    }

    /**
     * Send email using PHP's built-in mail() as fallback
     */
    public function send(string $to, string $subject, string $body, string $alt_body = ''): bool {
        // Use SMTP if configured
        if ($this->smtp_user && $this->smtp_pass) {
            return $this->sendSMTP($to, $subject, $body, $alt_body);
        }

        // Fallback to PHP mail()
        $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        return mail($to, $subject, $body, $headers);
    }

    /**
     * Send via SMTP (no external dependencies)
     */
    private function sendSMTP(string $to, string $subject, string $body, string $alt_body): bool {
        $errno = 0;
        $errstr = '';

        // Connect to SMTP server
        $socket = fsockopen(
            $this->smtp_host,
            $this->smtp_port,
            $errno,
            $errstr,
            30
        );

        if (!$socket) {
            error_log("SMTP Connection Error: {$errstr} ({$errno})");
            return false;
        }

        // Read server greeting
        $this->smtpRead($socket);

        // EHLO
        fputs($socket, "EHLO {$this->smtp_host}\r\n");
        $this->smtpRead($socket);

        // STARTTLS
        fputs($socket, "STARTTLS\r\n");
        $this->smtpRead($socket);

        // Upgrade to TLS
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        // Re-EHLO
        fputs($socket, "EHLO {$this->smtp_host}\r\n");
        $this->smtpRead($socket);

        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $this->smtpRead($socket);
        fputs($socket, base64_encode($this->smtp_user) . "\r\n");
        $this->smtpRead($socket);
        fputs($socket, base64_encode($this->smtp_pass) . "\r\n");
        $this->smtpRead($socket);

        // MAIL FROM
        fputs($socket, "MAIL FROM:<{$this->from_email}>\r\n");
        $this->smtpRead($socket);

        // RCPT TO
        fputs($socket, "RCPT TO:<{$to}>\r\n");
        $this->smtpRead($socket);

        // DATA
        fputs($socket, "DATA\r\n");
        $this->smtpRead($socket);

        // Build message
        $message_id = '<' . uniqid() . '@' . $_SERVER['HTTP_HOST'] . '>';
        $date = date('r');

        $headers = "Date: {$date}\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Message-ID: {$message_id}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"boundary\"\r\n";

        $content = "--boundary\r\n";
        $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $content .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $content .= $alt_body ?: strip_tags($body) . "\r\n\r\n";

        $content .= "--boundary\r\n";
        $content .= "Content-Type: text/html; charset=UTF-8\r\n";
        $content .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $content .= $body . "\r\n\r\n";
        $content .= "--boundary--\r\n";

        // Send email
        fputs($socket, $headers . "\r\n" . $content . "\r\n.\r\n");
        $response = $this->smtpRead($socket);

        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return strpos($response, '250') !== false;
    }

    private function smtpRead($socket): string {
        $data = '';
        while ($str = fgets($socket, 4096)) {
            $data .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        return $data;
    }

    /**
     * Send transactional email
     */
    public function sendTransactionEmail(int $user_id, string $type, float $amount, string $status, ?string $ref = null): void {
        $user = db_find('users', 'id = ?', [$user_id]);
        if (!$user) return;

        $subject = "VTU Transaction: " . ucfirst($status);
        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 20px; text-align: center;'>
                    <h1>" . htmlspecialchars(setting('site_name', 'VTU Fintech')) . "</h1>
                    <p>Transaction Notification</p>
                </div>
                <div style='padding: 20px; background: #f8f9fa;'>
                    <h2 style='color: #2b2d42;'>Hello " . htmlspecialchars($user['username']) . ",</h2>
                    <p>Your <strong>" . ucfirst($type) . "</strong> transaction has been <strong>" . ucfirst($status) . "</strong>.</p>
                    
                    <div style='background: white; border-radius: 10px; padding: 15px; margin: 20px 0;'>
                        <p><strong>Amount:</strong> ₦" . number_format($amount, 2) . "</p>
                        <p><strong>Type:</strong> " . ucfirst($type) . "</p>
                        <p><strong>Status:</strong> " . ucfirst($status) . "</p>
                        " . ($ref ? "<p><strong>Reference:</strong> " . htmlspecialchars($ref) . "</p>" : "") . "
                    </div>

                    <p>Thank you for using our service!</p>
                    <p style='color: #6c757d; font-size: 0.9em; margin-top: 30px;'>
                        This is an automated message. Please do not reply.
                    </p>
                </div>
            </div>
        ";

        $this->send($user['email'], $subject, $body);
    }
}

// Helper function
function email(): Email {
    static $email;
    if ($email === null) {
        $email = new Email();
    }
    return $email;
}