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
