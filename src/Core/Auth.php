<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Sign-in and sign-out. The signed-in user is re-read from the database on
 * every request, so a deactivated account takes effect immediately.
 *
 * Roles belong to school memberships (owner, admin, officer, viewer) and are
 * resolved by App::school(); this class only knows whether someone is
 * signed in, whether they are a platform administrator, and whether their
 * email has been verified.
 */
final class Auth
{
    public const MIN_PASSWORD_LENGTH = 10;
    /** Unverified accounts may use the app for this long after sign-up (BUILD_SPEC section 2). */
    public const VERIFY_GRACE_DAYS = 7;

    private const SESSION_KEY = '_user_id';
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

        $this->startSession($user);
        $this->audit->log((int) $user['id'], 'auth.login', 'user', (int) $user['id'], [], $ip, (int) ($user['last_school_id'] ?? 0) ?: null);
        return null;
    }

    /** Sign in a user whose identity was just proven another way (sign-up, accepted invitation). */
    public function loginAs(int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
            $this->startSession($user);
        }
    }

    private function startSession(array $user): void
    {
        Session::regenerate();
        // A new sign-in starts with no school chosen; App::school() picks the last one used.
        unset($this->session['_school_id'], $this->session['_school_role']);
        $this->session[self::SESSION_KEY] = (int) $user['id'];
        $this->user = $user;
        $this->loaded = true;
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
            unset($this->session[self::SESSION_KEY]);
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

    /** Manages the installation (schools, platform settings, health, backups); not a member of every school. */
    public function isPlatformAdmin(): bool
    {
        return (int) ($this->user()['is_platform_admin'] ?? 0) === 1;
    }

    public function isVerified(): bool
    {
        return !empty($this->user()['email_verified_at']);
    }

    /** Unverified and past the grace period: must verify the email before going on. */
    public function mustVerify(): bool
    {
        $user = $this->user();
        if ($user === null || $this->isVerified()) {
            return false;
        }
        return strtotime((string) $user['created_at']) < time() - self::VERIFY_GRACE_DAYS * 86400;
    }

    /** Days left to verify, for the reminder banner (0 when overdue, null when verified). */
    public function verifyDaysLeft(): ?int
    {
        $user = $this->user();
        if ($user === null || $this->isVerified()) {
            return null;
        }
        $deadline = strtotime((string) $user['created_at']) + self::VERIFY_GRACE_DAYS * 86400;
        return max(0, (int) ceil(($deadline - time()) / 86400));
    }

    /** Forget the cached user so the next call re-reads it (after a profile change). */
    public function refresh(): void
    {
        $this->loaded = false;
        $this->user = null;
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
