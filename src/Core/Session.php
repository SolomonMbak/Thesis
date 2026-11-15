<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PHP session with secure cookie flags and an idle timeout.
 *
 * Session files live in storage/cache/sessions so that the operating
 * system's own clean-up (which on Ubuntu ignores our timeout) cannot end
 * sessions early, and so they are never in a folder shared with other sites.
 */
final class Session
{
    private const LAST_ACTIVITY = '_last_activity';

    /** Starts the session. Returns true if it had just expired from inactivity. */
    public static function start(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return false;
        }
        $idleSeconds = (int) Config::get('session_idle_minutes', 60) * 60;
        $savePath = APP_ROOT . '/storage/cache/sessions';
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0770, true);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) ($idleSeconds + 300));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        session_save_path($savePath);
        session_name((string) Config::get('session_name', 'tt_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $last = $_SESSION[self::LAST_ACTIVITY] ?? null;
        $expired = is_int($last) && (time() - $last) > $idleSeconds;
        if ($expired) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION[self::LAST_ACTIVITY] = time();
        return $expired;
    }

    /** New session ID, keeping the data. Call on sign-in and role change. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
        session_destroy();
    }
}
