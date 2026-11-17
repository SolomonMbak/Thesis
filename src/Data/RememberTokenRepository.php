<?php
declare(strict_types=1);

namespace App\Data;

use PDO;

/**
 * "Keep me signed in" tokens (BUILD_SPEC 13.3). The cookie holds
 * selector.validator. The selector finds the row; only a sha256 hash of the
 * validator is stored, so a copy of the database cannot be used to sign in.
 *
 * The validator changes every time the cookie is used. The previous one is
 * still accepted for 2 minutes, because a browser can send several requests
 * at once with the old cookie. A wrong validator for a known selector means
 * the cookie may have been copied, so that token is deleted.
 */
final class RememberTokenRepository
{
    public const GRACE_SECONDS = 120;

    /** @var callable():int */
    private $clock;

    public function __construct(private PDO $pdo, ?callable $clock = null)
    {
        $this->clock = $clock ?? 'time';
    }

    /** @return array{cookie:string, expires:int} */
    public function issue(int $userId, int $days, string $userAgent, string $ip): array
    {
        $now = ($this->clock)();
        $this->pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ? AND expires_at < ?')->execute([$userId, $this->at($now)]);
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = $now + $days * 86400;
        $this->pdo->prepare(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at, user_agent, ip, last_used_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $selector, hash('sha256', $validator), $this->at($expires), mb_substr($userAgent, 0, 200), $ip,
            $this->at($now), $this->at($now)]);
        return ['cookie' => $selector . '.' . $validator, 'expires' => $expires];
    }

    /**
     * Check a cookie. Returns the user id, and a new cookie value to send
     * (null when the browser already has the newest one), or null when the
     * cookie is not valid.
     * @return array{user_id:int, cookie:?string, expires:int}|null
     */
    public function use(string $cookie): ?array
    {
        if (!preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $cookie, $m)) {
            return null;
        }
        [, $selector, $validator] = $m;
        $now = ($this->clock)();
        $stmt = $this->pdo->prepare('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > ?');
        $stmt->execute([$selector, $this->at($now)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $hash = hash('sha256', $validator);
        $expires = (int) strtotime((string) $row['expires_at']);
        if (hash_equals((string) $row['validator_hash'], $hash)) {
            $next = bin2hex(random_bytes(32));
            $this->pdo->prepare(
                'UPDATE remember_tokens SET validator_hash = ?, previous_hash = ?, rotated_at = ?, last_used_at = ? WHERE id = ?'
            )->execute([hash('sha256', $next), $hash, $this->at($now), $this->at($now), $row['id']]);
            return ['user_id' => (int) $row['user_id'], 'cookie' => $selector . '.' . $next, 'expires' => $expires];
        }
        $recent = $row['rotated_at'] !== null && $now - (int) strtotime((string) $row['rotated_at']) <= self::GRACE_SECONDS;
        if ($recent && $row['previous_hash'] !== null && hash_equals((string) $row['previous_hash'], $hash)) {
            return ['user_id' => (int) $row['user_id'], 'cookie' => null, 'expires' => $expires];
        }
        $this->pdo->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$row['id']]);
        return null;
    }

    public function forgetCookie(string $cookie): void
    {
        $selector = explode('.', $cookie)[0];
        $this->pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
    }

    /** Sign out on every device (after a password change, or on request). Returns how many were removed. */
    public function forgetUser(int $userId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    /** Browsers where the user stays signed in, newest use first. */
    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT selector, user_agent, ip, created_at, last_used_at, expires_at FROM remember_tokens
             WHERE user_id = ? AND expires_at > ? ORDER BY last_used_at DESC'
        );
        $stmt->execute([$userId, $this->at(($this->clock)())]);
        return $stmt->fetchAll();
    }

    private function at(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
}
