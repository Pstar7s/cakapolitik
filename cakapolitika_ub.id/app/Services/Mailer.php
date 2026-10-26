<?php
namespace App\Services;

/**
 * Minimal mailer. driver=log writes the message to storage/logs/otp.log
 * (default for dev). driver=smtp uses PHPMailer if vendor/ is present,
 * otherwise falls back to PHP mail().
 */
class Mailer
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('mail');
    }

    public function send(string $to, string $subject, string $bodyText): bool
    {
        $driver = $this->cfg['driver'] ?? 'log';

        if ($driver === 'log') {
            $line = sprintf(
                "[%s] TO:%s | %s\n%s\n%s\n",
                date('c'), $to, $subject, str_repeat('-', 40), $bodyText
            );
            @file_put_contents(ROOT_PATH . '/storage/logs/otp.log', $line . "\n", FILE_APPEND);
            return true;
        }

        if ($this->loadPhpMailer()) {
            return $this->sendSmtp($to, $subject, $bodyText);
        }

        // Last resort — server-configured sendmail.
        $headers = 'From: ' . $this->fromHeader() . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";
        return @mail($to, $subject, $bodyText, $headers);
    }

    private function loadPhpMailer(): bool
    {
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return true;
        }
        $src = ROOT_PATH . '/vendor/phpmailer/phpmailer/src/';
        if (!is_file($src . 'PHPMailer.php')) {
            return false;
        }
        require_once $src . 'Exception.php';
        require_once $src . 'PHPMailer.php';
        require_once $src . 'SMTP.php';
        return true;
    }

    private function sendSmtp(string $to, string $subject, string $body): bool
    {
        $s = $this->cfg['smtp'];
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $s['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $s['user'];
            $mail->Password   = $s['pass'];
            $mail->SMTPSecure = $s['secure'] ?: 'tls';
            $mail->Port       = (int)$s['port'];
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($this->cfg['from'], $this->cfg['from_name'] ?? 'CAKAPOLITIK_UB.ID');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('[mailer] SMTP gagal: ' . $e->getMessage());
            return false;
        }
    }

    private function fromHeader(): string
    {
        $name = $this->cfg['from_name'] ?? 'CAKAPOLITIK_UB.ID';
        return sprintf('%s <%s>', $name, $this->cfg['from']);
    }
}
