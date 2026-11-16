<?php
declare(strict_types=1);

namespace App\Data;

use PDO;

/**
 * One-time tokens for email verification and password reset (user_tokens).
 * Only a SHA-256 hash is stored; the raw token exists only in the email,
 * so a copy of the database cannot be used to take over accounts.
 */
final class TokenRepository
{
    public const VERIFY_HOURS = 24 * 7;
    public const RESET_HOURS = 1;

    public function __construct(private PDO $pdo)
    {
    }

    /** Create a token and return the raw value for the link. Older unused tokens of the same purpose stop working. */
    public function issue(int $userId, string $purpose): string
    {
        $hours = $purpose === 'reset_password' ? self::RESET_HOURS : self::VERIFY_HOURS;
        $this->pdo->prepare('UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL')
            ->execute([$userId, $purpose]);
        $raw = bin2hex(random_bytes(32));
        $this->pdo->prepare(
            'INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ? HOUR)'
        )->execute([$userId, $purpose, self::hash($raw), $hours]);
        return $raw;
    }

    /** The token row if it is valid (right purpose, not used, not expired), else null. */
    public function check(string $raw, string $purpose): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_tokens WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()'
        );
        $stmt->execute([self::hash($raw), $purpose]);
        return $stmt->fetch() ?: null;
    }

    /** Mark a token used. Returns false if it was already used (two clicks at once). */
    public function consume(int $tokenId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $stmt->execute([$tokenId]);
        return $stmt->rowCount() === 1;
    }

    public static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
