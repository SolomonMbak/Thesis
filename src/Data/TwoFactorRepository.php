<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\Crypto;
use PDO;

/**
 * Two-step sign-in settings of a user (BUILD_SPEC 13.3): the authenticator
 * key (encrypted), the last code step used, and one-time recovery codes
 * (only their hashes are stored).
 */
final class TwoFactorRepository
{
    public const RECOVERY_CODES = 10;
    /** No 0/o, 1/l/i: easy to read back from paper. */
    private const CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(private PDO $pdo)
    {
    }

    public static function isOn(?array $user): bool
    {
        return !empty($user['totp_enabled_at']) && !empty($user['totp_secret']);
    }

    public function enable(int $userId, string $secretBase32, int $step): void
    {
        $this->pdo->prepare('UPDATE users SET totp_secret = ?, totp_enabled_at = NOW(), totp_last_step = ? WHERE id = ?')
            ->execute([Crypto::encrypt($secretBase32), $step, $userId]);
    }

    public function disable(int $userId): void
    {
        $this->pdo->prepare('UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ?')
            ->execute([$userId]);
        $this->pdo->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$userId]);
    }

    /** The user's key in base32, or null if it cannot be read (key file lost). */
    public function secret(array $user): ?string
    {
        return empty($user['totp_secret']) ? null : Crypto::decrypt((string) $user['totp_secret']);
    }

    /**
     * Record that a code of this step was used. Fails if the same or a later
     * step was already used, so one code never works twice, even when two
     * requests arrive together.
     */
    public function useStep(int $userId, int $step): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)');
        $stmt->execute([$step, $userId, $step]);
        return $stmt->rowCount() === 1;
    }

    /** Replace the user's recovery codes with new ones; the codes are returned once, to show. */
    public function newRecoveryCodes(int $userId): array
    {
        $this->pdo->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$userId]);
        $insert = $this->pdo->prepare('INSERT INTO recovery_codes (user_id, code_hash) VALUES (?, ?)');
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $code = '';
            for ($c = 0; $c < 10; $c++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = substr($code, 0, 5) . '-' . substr($code, 5);
            $insert->execute([$userId, self::hashCode($code)]);
            $codes[] = $code;
        }
        return $codes;
    }

    /** Use a recovery code once. Spaces, dashes and capitals are ignored. */
    public function useRecoveryCode(int $userId, string $code): bool
    {
        $stmt = $this->pdo->prepare('UPDATE recovery_codes SET used_at = NOW() WHERE user_id = ? AND code_hash = ? AND used_at IS NULL LIMIT 1');
        $stmt->execute([$userId, self::hashCode($code)]);
        return $stmt->rowCount() === 1;
    }

    public function recoveryCodesLeft(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function looksLikeRecoveryCode(string $code): bool
    {
        return strlen(self::normalise($code)) === 10;
    }

    private static function normalise(string $code): string
    {
        return strtolower(preg_replace('/[\s-]+/', '', $code) ?? '');
    }

    private static function hashCode(string $code): string
    {
        return hash('sha256', self::normalise($code));
    }
}
