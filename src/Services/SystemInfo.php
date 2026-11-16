<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Machine description recorded in experiment exports (BUILD_SPEC section 15):
 * CPU, RAM, operating system, PHP version and date.
 */
final class SystemInfo
{
    public static function describe(): array
    {
        return [
            'cpu' => self::cpu(),
            'ram' => self::ram(),
            'os' => php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')',
            'php' => PHP_VERSION . ' (' . PHP_SAPI . ', opcache ' . (function_exists('opcache_get_status') && @opcache_get_status() ? 'on' : 'off') . ')',
            'date' => date('Y-m-d H:i:s T'),
        ];
    }

    private static function cpu(): string
    {
        if (is_readable('/proc/cpuinfo')) {
            $info = (string) file_get_contents('/proc/cpuinfo');
            $cores = substr_count($info, 'processor');
            if (preg_match('/model name\s*:\s*(.+)/', $info, $m)) {
                return trim($m[1]) . " ({$cores} logical cores)";
            }
        }
        $win = getenv('PROCESSOR_IDENTIFIER');
        if ($win) {
            return $win . ' (' . (getenv('NUMBER_OF_PROCESSORS') ?: '?') . ' logical cores)';
        }
        return 'unknown';
    }

    private static function ram(): string
    {
        if (is_readable('/proc/meminfo') && preg_match('/MemTotal:\s+(\d+) kB/', (string) file_get_contents('/proc/meminfo'), $m)) {
            return round((int) $m[1] / 1048576, 1) . ' GB';
        }
        if (Environment::isWindows() && Environment::canExec() && function_exists('exec')) {
            $out = [];
            @exec('powershell -NoProfile -Command "(Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory"', $out);
            $bytes = (int) trim((string) ($out[0] ?? '0'));
            if ($bytes > 0) {
                return round($bytes / 1073741824, 1) . ' GB';
            }
        }
        return 'unknown';
    }
}
