<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Sign-in, sign-out and role checks.
 *
 * Roles: admin (everything) and officer (data, generation, reports).
 * The signed-in user is re-read from the database on every request, so a
 * deactivated account or a changed role takes effect immediately.
 */
final class Auth
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OFFICER = 'officer';
    public const MIN_PASSWORD_LENGTH = 10;

    private const SESSION_KEY = '_user_id';
    private const ROLE_KEY = '_user_role';
    /** Hash of a random string nobody knows; used only to equalise timing. */
    private const DUMMY_HASH = '$2y$12$UTvbRMQ5NdgZX6NMGLdNPe7Nlv8PCx/y6VxZxIbQfFQMaeUjYt35O';

    private ?array $user = null;
    private bool $loaded = false;
    private array $session;

    public function __construct(
        private PDO $pdo,
        private RateLimiter $limiter,
        private Audit $audit,
        array &$session
    ) {
        $this->session = &$session;
    }

    /**
     * Attempt to sign in. Returns null on success, or a message to show.
     * The message never says whether the email exists.
     */
    public function attempt(string $email, string $password, string $ip): ?string
    {
        $email = RateLimiter::normalise($email);
        if ($this->limiter->isLocked($email, $ip)) {
            $minutes = $this->limiter->minutesUntilUnlock($email, $ip);
            $this->audit->log(null, 'auth.locked', null, null, ['email' => $email], $ip);
            return "Too many failed sign-in attempts. Please wait {$minutes} minute(s) and try again.";
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch() ?: null;

        // Verify against a dummy hash when the user is unknown, so response
        // time does not reveal which emails have accounts.
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $passwordOk = password_verify($password, $hash);

        if (!$user || !$passwordOk || !(int) $user['is_active']) {
            $this->limiter->record($email, $ip, false);
            $this->audit->log($user ? (int) $user['id'] : null, 'auth.failed', 'user', $user ? (int) $user['id'] : null, ['email' => $email], $ip);
            return 'The email or password is not correct, or the account is inactive.';
        }

        $this->limiter->record($email, $ip, true);
        $this->upgradeHashIfNeeded((int) $user['id'], $password, $user['password_hash']);
        $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

        Session::regenerate();
        $this->session[self::SESSION_KEY] = (int) $user['id'];
        $this->session[self::ROLE_KEY] = $user['role'];
        $this->user = $user;
        $this->loaded = true;
        $this->audit->log((int) $user['id'], 'auth.login', 'user', (int) $user['id'], [], $ip);
        return null;
    }

    public function logout(string $ip): void
    {
        $id = $this->id();
        if ($id !== null) {
            $this->audit->log($id, 'auth.logout', 'user', $id, [], $ip);
        }
        Session::destroy();
        $this->user = null;
        $this->loaded = true;
    }

    /** The signed-in, active user row, or null. */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $id = $this->session[self::SESSION_KEY] ?? null;
        if (!is_int($id)) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        $this->user = $stmt->fetch() ?: null;
        if ($this->user === null) {
            unset($this->session[self::SESSION_KEY], $this->session[self::ROLE_KEY]);
        } elseif (($this->session[self::ROLE_KEY] ?? null) !== $this->user['role']) {
            // Role changed by an admin since sign-in: new session ID (spec section 13).
            Session::regenerate();
            $this->session[self::ROLE_KEY] = $this->user['role'];
        }
        return $this->user;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user ? (int) $user['id'] : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? null) === self::ROLE_ADMIN;
    }

    /** Admins may do everything an officer can. */
    public function hasRole(string $role): bool
    {
        $current = $this->user()['role'] ?? null;
        if ($current === null) {
            return false;
        }
        return $current === self::ROLE_ADMIN || $current === $role;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function upgradeHashIfNeeded(int $userId, string $password, string $hash): void
    {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([self::hashPassword($password), $userId]);
        }
    }
}
