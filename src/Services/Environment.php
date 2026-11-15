<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Data\SettingsRepository;

/**
 * Facts about the server, used by the installer, the job launcher and the
 * health page.
 */
final class Environment
{
    public static function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    /** Can this PHP start background processes (exec mode)? */
    public static function canExec(): bool
    {
        $disabled = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));
        $needed = self::isWindows() ? ['popen', 'pclose'] : ['exec'];
        foreach ($needed as $fn) {
            if (!function_exists($fn) || in_array($fn, $disabled, true)) {
                return false;
            }
        }
        return true;
    }

    public static function recommendedLaunchMode(): string
    {
        return self::canExec() ? 'exec' : 'queue';
    }

    /** PHP command line program: the setting, then the config file, then a guess. */
    public static function phpCliPath(?SettingsRepository $settings = null): string
    {
        $fromSettings = $settings ? trim((string) $settings->get('php_cli_path', '')) : '';
        if ($fromSettings !== '') {
            return $fromSettings;
        }
        $fromConfig = trim((string) Config::get('php_cli_path', ''));
        if ($fromConfig !== '' && $fromConfig !== 'php') {
            return $fromConfig;
        }
        return self::guessPhpCli();
    }

    /** Best guess at the CLI binary. Under the web server PHP_BINARY is often php-fpm or apache. */
    public static function guessPhpCli(): string
    {
        $binary = PHP_BINARY;
        if (PHP_SAPI === 'cli' && $binary !== '') {
            return $binary;
        }
        $dir = $binary !== '' ? dirname($binary) : '';
        $sep = DIRECTORY_SEPARATOR;
        foreach ([$dir . $sep . 'php', $dir . $sep . 'php.exe', dirname($dir) . $sep . 'bin' . $sep . 'php'] as $candidate) {
            if ($dir !== '' && is_file($candidate) && !str_contains(basename($candidate), 'fpm')) {
                return $candidate;
            }
        }
        foreach (['/usr/local/bin/php', '/usr/bin/php', '/opt/cpanel/ea-php83/root/usr/bin/php'] as $candidate) {
            if (@is_file($candidate)) {
                return $candidate;
            }
        }
        return 'php';
    }

    /** Extensions the application needs. */
    public static function requiredExtensions(): array
    {
        return ['pdo_mysql', 'mbstring', 'json', 'fileinfo'];
    }
}
