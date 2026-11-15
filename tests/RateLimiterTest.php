<?php
declare(strict_types=1);

namespace Tests;

use App\Core\RateLimiter;
use PDO;

/**
 * Sign-in throttling: 5 failures per 15 minutes, per email and per IP.
 * Uses an in-memory SQLite table with the same columns and a fake clock.
 */
final class RateLimiterTest extends TestCase
{
    private PDO $pdo;
    private int $now;

    public function setUp(): void
    {
        $this->pdo = $this->sqlite();
        $this->pdo->exec(
            'CREATE TABLE login_attempts (
               id INTEGER PRIMARY KEY AUTOINCREMENT,
               email VARCHAR(120) NOT NULL,
               ip VARCHAR(45) NOT NULL,
               success TINYINT NOT NULL,
               attempted_at DATETIME NOT NULL
             )'
        );
        $this->now = strtotime('2026-01-12 09:00:00');
    }

    private function limiter(): RateLimiter
    {
        return new RateLimiter($this->pdo, 5, 15, fn () => $this->now);
    }

    public function testFourFailuresDoNotLock(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 4; $i++) {
            $limiter->record('ada@example.edu', '10.0.0.1', false);
        }
        $this->assertFalse($limiter->isLocked('ada@example.edu', '10.0.0.1'));
    }

    public function testFiveFailuresLockTheEmail(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            // Different IPs each time: the email alone must trigger the lock.
            $limiter->record('ada@example.edu', "10.0.0.{$i}", false);
        }
        $this->assertTrue($limiter->isLocked('ada@example.edu', '192.168.1.1'));
        $this->assertTrue($limiter->isLocked('  ADA@Example.edu ', '192.168.1.1'), 'Email match ignores case and spaces.');
        $this->assertFalse($limiter->isLocked('someone.else@example.edu', '192.168.1.1'));
    }

    public function testFiveFailuresLockTheIp(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $limiter->record("user{$i}@example.edu", '10.0.0.9', false);
        }
        $this->assertTrue($limiter->isLocked('new@example.edu', '10.0.0.9'));
        $this->assertFalse($limiter->isLocked('new@example.edu', '10.0.0.10'));
    }

    public function testSuccessesDoNotCount(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 10; $i++) {
            $limiter->record('ada@example.edu', '10.0.0.1', true);
        }
        $this->assertFalse($limiter->isLocked('ada@example.edu', '10.0.0.1'));
    }

    public function testLockLiftsAfterWindowPasses(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $limiter->record('ada@example.edu', '10.0.0.1', false);
        }
        $this->now += 14 * 60;
        $this->assertTrue($limiter->isLocked('ada@example.edu', '10.0.0.1'), 'Still locked after 14 minutes.');
        $this->assertSame(1, $limiter->minutesUntilUnlock('ada@example.edu', '10.0.0.1'));

        $this->now += 61;
        $this->assertFalse($limiter->isLocked('ada@example.edu', '10.0.0.1'), 'Unlocked after 15 minutes.');
    }

    public function testMinutesUntilUnlockAtStartOfLock(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $limiter->record('ada@example.edu', '10.0.0.1', false);
        }
        $this->assertSame(15, $limiter->minutesUntilUnlock('ada@example.edu', '10.0.0.1'));
    }

    public function testFailuresSpreadOverTimeOnlyCountInsideWindow(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $limiter->record('ada@example.edu', '10.0.0.1', false);
            $this->now += 5 * 60; // one failure every 5 minutes
        }
        // Failures at 0, 5, 10, 15, 20 minutes; now is 25: only 15 and 20 are inside the window.
        $this->assertFalse($limiter->isLocked('ada@example.edu', '10.0.0.1'));
    }

    public function testPruneRemovesOldRows(): void
    {
        $limiter = $this->limiter();
        $limiter->record('ada@example.edu', '10.0.0.1', false);
        $this->now += 2 * 86400;
        $limiter->record('ada@example.edu', '10.0.0.1', false);
        $limiter->prune();
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn());
    }
}
