<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Reads config/config.php (defaults) and then config/config.local.php
 * (server-specific overrides written by the installer).
 */
final class Config
{
    private static array $values = [];

    public static function load(string $dir): void
    {
        $values = require $dir . '/config.php';
        $local = $dir . '/config.local.php';
        if (is_file($local)) {
            $values = array_merge($values, require $local);
        }
        self::$values = $values;
        self::useTestDatabaseIfAsked();
    }

    /**
     * The HTTP tests (TenantIsolationTest) start PHP's built-in server with
     * TT_TEST_DB=1 so the pages run against the test database. This only
     * works with the built-in development server, in development mode, with
     * a test database configured, so it can never switch a live site.
     */
    private static function useTestDatabaseIfAsked(): void
    {
        if (getenv('TT_TEST_DB') === '1' && PHP_SAPI === 'cli-server'
            && (self::$values['app_env'] ?? '') === 'development' && (string) (self::$values['test_db_name'] ?? '') !== '') {
            self::$values['db_name'] = self::$values['test_db_name'];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? $default;
    }

    /** Used by tests to change a value for one test. */
    public static function set(string $key, mixed $value): void
    {
        self::$values[$key] = $value;
    }

    public static function isDevelopment(): bool
    {
        return self::get('app_env') === 'development';
    }
}
