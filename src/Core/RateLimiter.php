<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Sign-in throttling: after N failed attempts within a time window, for the
 * same email OR the same IP address, further attempts are refused until the
 * oldest failure leaves the window.
 *
 * Times are written from PHP (not the database clock) so the rule behaves the
 * same on any server and can be tested with a fake clock.
 */
final class RateLimiter
{
    /** @var callable():int */
    private $clock;

    public function __construct(
        private PDO $pdo,
        private int $maxFailures = 5,
        private int $windowMinutes = 15,
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? 'time';
    }

    public function record(string $email, string $ip, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip, success, attempted_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([self::normalise($email), $ip, $success ? 1 : 0, $this->format($this->now())]);
    }

    public function isLocked(string $email, string $ip): bool
    {
        return $this->failuresFor('email', self::normalise($email)) >= $this->maxFailures
            || $this->failuresFor('ip', $ip) >= $this->maxFailures;
    }

    /**
     * Count one use of a throttled action (sign-up, password reset request)
     * under a key such as "signup|203.0.113.5" and say whether the limit is
     * now passed. These rows use the placeholder ip "throttle", so they never
     * add to the sign-in lockout of a real address.
     */
    public function hit(string $key): bool
    {
        $this->record($key, 'throttle', false);
        return $this->failuresFor('email', self::normalise($key)) > $this->maxFailures;
    }

    /** Whole minutes until the lock lifts (at least 1), for the error message. */
    public function minutesUntilUnlock(string $email, string $ip): int
    {
        $cutoff = $this->format($this->now() - $this->windowMinutes * 60);
        $stmt = $this->pdo->prepare(
            'SELECT MIN(attempted_at) FROM login_attempts
             WHERE success = 0 AND attempted_at >= ? AND (email = ? OR ip = ?)'
        );
        $stmt->execute([$cutoff, self::normalise($email), $ip]);
        $oldest = $stmt->fetchColumn();
        if (!$oldest) {
            return 1;
        }
        $unlockAt = strtotime((string) $oldest) + $this->windowMinutes * 60;
        return max(1, (int) ceil(($unlockAt - $this->now()) / 60));
    }

    /** Remove rows older than a day so the table stays small. */
    public function prune(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
        $stmt->execute([$this->format($this->now() - 86400)]);
    }

    public static function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function failuresFor(string $column, string $value): int
    {
        // $column is one of two fixed names, never user input.
        $sql = "SELECT COUNT(*) FROM login_attempts WHERE {$column} = ? AND success = 0 AND attempted_at >= ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$value, $this->format($this->now() - $this->windowMinutes * 60)]);
        return (int) $stmt->fetchColumn();
    }

    private function now(): int
    {
        return (int) ($this->clock)();
    }

    private function format(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
}
