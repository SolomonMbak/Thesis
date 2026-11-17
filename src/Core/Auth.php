<?php
declare(strict_types=1);

namespace App\Core;

use App\Data\RememberTokenRepository;
use App\Data\TwoFactorRepository;
use PDO;

/**
 * Sign-in and sign-out. The signed-in user is re-read from the database on
 * every request, so a deactivated account takes effect immediately.
 *
 * Roles belong to school memberships (owner, admin, officer, viewer) and are
 * resolved by App::school(); this class only knows whether someone is
 * signed in, whether they are a platform administrator, and whether their
 * email has been verified.
 *
 * Login security (BUILD_SPEC 13.3): "Keep me signed in" restores the session
 * from a cookie for up to 30 days after the 60-minute idle timeout; users
 * with two-step sign-in give a code from their authenticator app (or a
 * recovery code) after the password; members added with a temporary
 * password must choose their own before going on.
 */
final class Auth
{
    public const MIN_PASSWORD_LENGTH = 10;
    /** Unverified accounts may use the app for this long after sign-up (BUILD_SPEC section 2). */
    public const VERIFY_GRACE_DAYS = 7;

    private const SESSION_KEY = '_user_id';
    private const PASSWORD_MARK_KEY = '_pw_mark';
    /** Password accepted, waiting for the two-step code: [user_id, at, remember]. */
    private const PENDING_KEY = '_2fa_pending';
    /** Minutes allowed between the password and the code. */
    public const PENDING_MINUTES = 10;
    /** Hash of a random string nobody knows; used only to equalise timing. */
    private const DUMMY_HASH = '$2y$12$UTvbRMQ5NdgZX6NMGLdNPe7Nlv8PCx/y6VxZxIbQfFQMaeUjYt35O';

    private ?array $user = null;
    private bool $loaded = false;
    private array $session;

    public function __construct(
        private PDO $pdo,
        private RateLimiter $limiter,
        private Audit $audit,
        array &$session,
        private ?Request $request = null
    ) {
        $this->session = &$session;
    }

    /**
     * Attempt to sign in. Returns null on success, or a message to show.
     * The message never says whether the email exists.
     */
    public function attempt(string $email, string $password, string $ip, bool $remember = false): ?string
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
        $user['password_hash'] = $this->upgradeHashIfNeeded((int) $user['id'], $password, $user['password_hash']);

        if (TwoFactorRepository::isOn($user)) {
            // Not signed in yet: the code comes next (awaitingSecondStep, verifySecondStep).
            Session::regenerate();
            $this->session[self::PENDING_KEY] = ['user_id' => (int) $user['id'], 'at' => time(), 'remember' => $remember];
            return null;
        }
        $this->completeSignIn($user, $remember, $ip, 'password');
        return null;
    }

    /** The user whose password was accepted and who must now give a two-step code, or null. */
    public function awaitingSecondStep(): ?array
    {
        $pending = $this->session[self::PENDING_KEY] ?? null;
        if (!is_array($pending) || time() - (int) $pending['at'] > self::PENDING_MINUTES * 60) {
            unset($this->session[self::PENDING_KEY]);
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$pending['user_id']]);
        $user = $stmt->fetch() ?: null;
        return TwoFactorRepository::isOn($user) ? $user : null;
    }

    /**
     * Second step: a 6-digit code from the authenticator app, or a recovery
     * code. Returns null when signed in, or a message to show. Throttled
     * like the password (5 failures per 15 minutes).
     */
    public function verifySecondStep(string $code, string $ip): ?string
    {
        $user = $this->awaitingSecondStep();
        if ($user === null) {
            return 'Your sign-in took longer than ' . self::PENDING_MINUTES . ' minutes. Please enter your email and password again.';
        }
        $key = '2fa|' . $user['email'];
        if ($this->limiter->isLocked($key, $ip)) {
            $minutes = $this->limiter->minutesUntilUnlock($key, $ip);
            return "Too many wrong codes. Please wait {$minutes} minute(s) and try again.";
        }
        $codes = new TwoFactorRepository($this->pdo);
        $userId = (int) $user['id'];
        $via = 'authenticator';
        if (TwoFactorRepository::looksLikeRecoveryCode($code)) {
            $via = 'recovery_code';
            $ok = $codes->useRecoveryCode($userId, $code);
        } else {
            $secret = $codes->secret($user);
            if ($secret === null) {
                return 'Your two-step key cannot be read on this server. Use one of your recovery codes, '
                    . 'or ask the platform administrator to turn two-step sign-in off for your account.';
            }
            $step = Totp::match($secret, $code, time(), $user['totp_last_step'] === null ? null : (int) $user['totp_last_step']);
            $ok = $step !== null && $codes->useStep($userId, $step);
        }
        if (!$ok) {
            $this->limiter->record($key, $ip, false);
            $this->audit->log($userId, 'auth.2fa_failed', 'user', $userId, [], $ip);
            return 'That code is not correct, or it was already used. Type the 6 digits your app shows now, or one of your recovery codes.';
        }
        $this->limiter->record($key, $ip, true);
        $remember = (bool) ($this->session[self::PENDING_KEY]['remember'] ?? false);
        unset($this->session[self::PENDING_KEY]);
        $this->completeSignIn($user, $remember, $ip, $via);
        return null;
    }

    public function cancelSecondStep(): void
    {
        unset($this->session[self::PENDING_KEY]);
    }

    private function completeSignIn(array $user, bool $remember, string $ip, string $via): void
    {
        $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        $this->startSession($user);
        if ($remember) {
            $this->rememberThisBrowser();
        }
        $this->audit->log((int) $user['id'], 'auth.login', 'user', (int) $user['id'], ['via' => $via, 'remember' => $remember], $ip,
            (int) ($user['last_school_id'] ?? 0) ?: null);
    }

    // ---- Keep me signed in ----

    /** Days a "Keep me signed in" cookie lasts, counted from sign-in (never extended). */
    public static function rememberDays(): int
    {
        return max(1, min(90, (int) Config::get('remember_days', 30)));
    }

    /** Keep the signed-in user signed in on this browser (a new cookie and token). */
    public function rememberThisBrowser(): void
    {
        $id = $this->id();
        if ($id === null || $this->request === null) {
            return;
        }
        $old = $this->request->cookie(self::rememberCookieName());
        if ($old !== null) {
            $this->remembered()->forgetCookie($old);
        }
        $token = $this->remembered()->issue($id, self::rememberDays(), $this->request->userAgent(), $this->request->ip());
        self::sendRememberCookie($token['cookie'], $token['expires']);
    }

    /** Did the user choose to stay signed in on this browser? */
    public function isRemembered(): bool
    {
        return $this->request?->cookie(self::rememberCookieName()) !== null;
    }

    /**
     * Sign in again from the "Keep me signed in" cookie when there is no
     * session (it ended after 60 minutes idle, or the browser was closed).
     * Call once per request before any output. Returns true if it signed in.
     */
    public function restoreFromCookie(): bool
    {
        if ($this->request === null || isset($this->session[self::SESSION_KEY])) {
            return false;
        }
        $cookie = $this->request->cookie(self::rememberCookieName());
        if ($cookie === null || $cookie === '') {
            return false;
        }
        $result = $this->remembered()->use($cookie);
        $user = null;
        if ($result !== null) {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$result['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
        if ($user === null) {
            $this->remembered()->forgetCookie($cookie);
            self::sendRememberCookie('', time() - 3600);
            return false;
        }
        if ($result['cookie'] !== null) {
            self::sendRememberCookie($result['cookie'], $result['expires']);
        }
        $this->startSession($user);
        $this->audit->log((int) $user['id'], 'auth.restored', 'user', (int) $user['id'], [], $this->request->ip());
        return true;
    }

    /** Sign out on every browser where the user chose to stay signed in. Returns how many. */
    public function forgetAllBrowsers(int $userId): int
    {
        return $this->remembered()->forgetUser($userId);
    }

    private function remembered(): RememberTokenRepository
    {
        return new RememberTokenRepository($this->pdo);
    }

    private static function rememberCookieName(): string
    {
        return (string) Config::get('remember_cookie', 'tt_remember');
    }

    private static function sendRememberCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::rememberCookieName(), $value, [
            'expires' => $expires,
            'path' => Session::cookiePath(),
            'secure' => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
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
        $this->session[self::PASSWORD_MARK_KEY] = self::passwordMark($user);
        $this->user = $user;
        $this->loaded = true;
    }

    /**
     * A short fingerprint of the password hash, kept in the session. When the
     * password changes (or is reset), every other session no longer matches
     * and is signed out at its next request, including a stolen one.
     */
    private static function passwordMark(array $user): string
    {
        return substr(hash('sha256', (string) $user['password_hash']), 0, 16);
    }

    /** After the signed-in user changed their own password: keep this session valid. */
    public function keepSessionAfterPasswordChange(): void
    {
        $id = $this->session[self::SESSION_KEY] ?? null;
        if (!is_int($id)) {
            return;
        }
        // Read the row directly: user() would sign this session out, as its mark is still the old one.
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        if ($hash !== false) {
            $this->session[self::PASSWORD_MARK_KEY] = self::passwordMark(['password_hash' => $hash]);
        }
        $this->refresh();
    }

    public function logout(string $ip): void
    {
        $id = $this->id();
        if ($id !== null) {
            $this->audit->log($id, 'auth.logout', 'user', $id, [], $ip);
        }
        $cookie = $this->request?->cookie(self::rememberCookieName());
        if ($cookie !== null) {
            $this->remembered()->forgetCookie($cookie);
            self::sendRememberCookie('', time() - 3600);
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
        if ($this->user !== null && !hash_equals((string) ($this->session[self::PASSWORD_MARK_KEY] ?? ''), self::passwordMark($this->user))) {
            $this->user = null;   // the password changed since this session signed in
        }
        if ($this->user === null) {
            unset($this->session[self::SESSION_KEY], $this->session[self::PASSWORD_MARK_KEY]);
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

    /** Added by a school administrator with a temporary password: must choose their own before going on. */
    public function mustChangePassword(): bool
    {
        return (int) ($this->user()['must_change_password'] ?? 0) === 1;
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

    /**
     * bcrypt cost 12 (PHP 8.4's own default; 8.2 and 8.3 default to 10, which
     * is now cheap to attack). Older hashes are upgraded at the next sign-in.
     */
    private const HASH_OPTIONS = ['cost' => 12];

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT, self::HASH_OPTIONS);
    }

    /** Re-hash with the current cost if needed. Returns the hash now stored. */
    private function upgradeHashIfNeeded(int $userId, string $password, string $hash): string
    {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT, self::HASH_OPTIONS)) {
            $hash = self::hashPassword($password);
            $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
        }
        return $hash;
    }
}
