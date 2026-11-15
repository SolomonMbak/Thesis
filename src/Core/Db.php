<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * One shared PDO connection per process.
 * Exceptions on error, real (not emulated) prepared statements, utf8mb4.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect([
                'host' => Config::get('db_host'),
                'port' => Config::get('db_port'),
                'name' => Config::get('db_name'),
                'user' => Config::get('db_user'),
                'pass' => Config::get('db_pass'),
            ]);
        }
        return self::$pdo;
    }

    /** Open a new connection. The installer and tests call this directly. */
    public static function connect(array $c): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4%s',
            $c['host'],
            (int) $c['port'],
            ($c['name'] ?? '') !== '' ? ';dbname=' . $c['name'] : ''
        );
        $pdo = new PDO($dsn, (string) $c['user'], (string) $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        // Keep MySQL's NOW() in step with PHP's clock. Stale-job detection
        // compares times written by both.
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        return $pdo;
    }

    /** Replace the shared connection (tests use an isolated database). */
    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
