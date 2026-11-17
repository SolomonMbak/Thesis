<?php
declare(strict_types=1);

namespace App\Core;

use App\Data\SettingsRepository;

/**
 * Sends the system's emails: verification, password reset and invitations
 * (BUILD_SPEC 14.4). Plain text only.
 *
 *  - Development (app_env = development): written to storage/logs/mail.log, never sent.
 *  - SMTP host set in Platform settings: a small SMTP client, AUTH LOGIN over
 *    STARTTLS (port 587) or SSL (port 465).
 *  - Otherwise PHP mail(), which works on most shared hosts.
 */
final class Mailer
{
    private const TIMEOUT = 15;

    public function __construct(private SettingsRepository $settings)
    {
    }

    public static function logFile(): string
    {
        return APP_ROOT . '/storage/logs/mail.log';
    }

    /** Returns true when the message was handed over (or logged). Failures are logged, never shown raw. */
    public function send(string $to, string $subject, string $body): bool
    {
        $to = self::clean($to);
        $subject = self::clean($subject);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            if (Config::get('app_env') === 'development') {
                return $this->writeToLog($to, $subject, $body);
            }
            if (trim((string) $this->settings->get('smtp_host', '')) !== '') {
                $this->sendSmtp($to, $subject, $body);
                return true;
            }
            return mail($to, self::encodeHeader($subject), $body, $this->headers($to, false));
        } catch (\Throwable $ex) {
            Logger::exception($ex);
            return false;
        }
    }

    private function writeToLog(string $to, string $subject, string $body): bool
    {
        $entry = '----- ' . date('Y-m-d H:i:s') . " -----\nTo: {$to}\nSubject: {$subject}\n\n{$body}\n\n";
        return file_put_contents(self::logFile(), $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private function sendSmtp(string $to, string $subject, string $body): void
    {
        $host = trim((string) $this->settings->get('smtp_host'));
        $port = (int) $this->settings->get('smtp_port', '587');
        $encryption = (string) $this->settings->get('smtp_encryption', 'tls');
        $address = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($address, $errno, $error, self::TIMEOUT);
        if ($socket === false) {
            throw new \RuntimeException("Could not connect to the mail server {$host}:{$port}: {$error}");
        }
        stream_set_timeout($socket, self::TIMEOUT);
        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO ' . self::heloName(), 250);
            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('The mail server did not accept an encrypted connection (STARTTLS).');
                }
                $this->command($socket, 'EHLO ' . self::heloName(), 250);
            }
            $user = (string) $this->settings->get('smtp_username', '');
            if ($user !== '') {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($user), 334);
                $this->command($socket, base64_encode(self::smtpPassword((string) $this->settings->get('smtp_password', ''))), 235);
            }
            $this->command($socket, 'MAIL FROM:<' . $this->fromAddress() . '>', 250);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', 354);
            // Lines starting with a dot are doubled so they cannot end the message early (RFC 5321).
            $message = $this->headers($to, true) . "\r\nSubject: " . self::encodeHeader($subject) . "\r\n\r\n"
                . preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", $body));
            $this->command($socket, $message . "\r\n.", 250);
            $this->command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    private function headers(string $to, bool $withTo): string
    {
        $fromName = self::clean((string) $this->settings->get('smtp_from_name', '')) ?: (string) $this->settings->get('platform_name', 'Timetable');
        $lines = [
            'From: ' . self::encodeHeader($fromName) . ' <' . $this->fromAddress() . '>',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::heloName() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        if ($withTo) {
            array_unshift($lines, 'To: <' . $to . '>');
        }
        return implode("\r\n", $lines);
    }

    private function fromAddress(): string
    {
        $from = self::clean((string) $this->settings->get('smtp_from_email', ''));
        return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'no-reply@' . self::heloName();
    }

    /** @param int|int[] $expected */
    private function command($socket, string $line, int|array $expected): void
    {
        fwrite($socket, $line . "\r\n");
        $this->expect($socket, $expected);
    }

    /** Read a (possibly multi-line) reply and check its code. */
    private function expect($socket, int|array $expected): void
    {
        $reply = '';
        while (($line = fgets($socket, 515)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            throw new \RuntimeException('The mail server replied: ' . trim($reply));
        }
    }

    private static function heloName(): string
    {
        $host = parse_url((string) Config::get('app_url', ''), PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $host : (gethostname() ?: 'localhost');
    }

    /** Remove line breaks so a value can never add extra headers. */
    /**
     * The SMTP password is saved encrypted with the server key (Crypto), so a
     * database backup does not reveal it. A value saved before that change is
     * still plain text and is used as it is.
     */
    public static function smtpPassword(string $stored): string
    {
        if (preg_match('/^(s1|o1):/', $stored)) {
            return Crypto::decrypt($stored) ?? '';
        }
        return $stored;
    }

    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $value));
    }

    private static function encodeHeader(string $text): string
    {
        return preg_match('/[^\x20-\x7E]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
    }
}
