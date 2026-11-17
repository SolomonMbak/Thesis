<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Encrypts small secrets kept in the database (the two-step sign-in key of
 * each user), so a copy of the database or a backup alone cannot be used to
 * make sign-in codes.
 *
 * The key is `app_key` in config.local.php (base64 of 32 random bytes), or
 * else storage/keys/app.key, created on first use. Losing the key turns
 * two-step sign-in off in effect: users then sign in with a recovery code or
 * have it reset (bin/reset_2fa.php). See docs/DEPLOYMENT.md.
 */
final class Crypto
{
    private const SODIUM = 's1:';
    private const OPENSSL = 'o1:';

    public static function encrypt(string $plain): string
    {
        $key = self::key();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return self::SODIUM . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
        }
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return self::OPENSSL . base64_encode($iv . $tag . $cipher);
    }

    /** The plain text, or null when the value was changed or the key is not the one it was encrypted with. */
    public static function decrypt(string $stored): ?string
    {
        $key = self::key();
        $raw = base64_decode(substr($stored, 3), true);
        if ($raw === false) {
            return null;
        }
        if (str_starts_with($stored, self::SODIUM) && function_exists('sodium_crypto_secretbox_open')) {
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
            return $plain === false ? null : $plain;
        }
        if (str_starts_with($stored, self::OPENSSL)) {
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            return $plain === false ? null : $plain;
        }
        return null;
    }

    /** 32-byte key from the config, or from the key file (created with owner-only permissions when missing). */
    private static function key(): string
    {
        $configured = (string) Config::get('app_key', '');
        if ($configured !== '') {
            $key = base64_decode($configured, true);
            if ($key === false || strlen($key) !== 32) {
                throw new \RuntimeException('app_key in config.local.php must be base64 of 32 random bytes.');
            }
            return $key;
        }
        $file = APP_ROOT . '/storage/keys/app.key';
        if (!is_file($file)) {
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0700, true);
            }
            // 'x' mode fails if another request created the file a moment ago; then that file is read below.
            $handle = @fopen($file, 'x');
            if ($handle !== false) {
                fwrite($handle, base64_encode(random_bytes(32)));
                fclose($handle);
                @chmod($file, 0600);
            }
        }
        $key = base64_decode(trim((string) file_get_contents($file)), true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('storage/keys/app.key is damaged. Restore it from a backup.');
        }
        return $key;
    }
}
