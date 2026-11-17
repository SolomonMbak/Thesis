<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Time-based one-time passwords (RFC 6238, the codes shown by Google
 * Authenticator, Microsoft Authenticator and similar apps): HMAC-SHA1,
 * 6 digits, a new code every 30 seconds.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    /** Codes from one step before or after are accepted too, for phones whose clock is a little off. */
    public const WINDOW = 1;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A new random secret (160 bits) in base32, the form authenticator apps expect. */
    public static function newSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** The code for a 30-second step. */
    public static function codeAt(string $secretBase32, int $step): string
    {
        $key = self::base32Decode($secretBase32);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $number = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($number % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function stepAt(int $time): int
    {
        return intdiv($time, self::PERIOD);
    }

    /**
     * The step a typed code matches (within the window), or null. The caller
     * refuses steps not later than the last one used, so a code cannot be
     * used twice.
     */
    public static function match(string $secretBase32, string $code, int $time, ?int $lastStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }
        $now = self::stepAt($time);
        for ($step = $now - self::WINDOW; $step <= $now + self::WINDOW; $step++) {
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals(self::codeAt($secretBase32, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    /** The link an authenticator app reads from the QR code. */
    public static function uri(string $secretBase32, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secretBase32 . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** The secret in groups of four letters, easier to type by hand. */
    public static function grouped(string $secretBase32): string
    {
        return trim(chunk_split($secretBase32, 4, ' '));
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=]/', '', $text) ?? '');
        $bits = '';
        foreach (str_split($text) as $char) {
            $value = strpos(self::BASE32, $char);
            if ($value === false) {
                throw new \InvalidArgumentException('The key contains a character that is not base32.');
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
