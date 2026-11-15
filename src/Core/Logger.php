<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Writes one line per event to storage/logs/app-YYYY-MM-DD.log.
 * Never throws: a logging failure must not break the page.
 */
final class Logger
{
    private static string $dir = '';

    public static function setDirectory(string $dir): void
    {
        self::$dir = $dir;
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function exception(\Throwable $ex): void
    {
        self::error(get_class($ex) . ': ' . $ex->getMessage(), [
            'file' => $ex->getFile() . ':' . $ex->getLine(),
            'trace' => $ex->getTraceAsString(),
        ]);
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (self::$dir === '') {
            return;
        }
        $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), $level, $message);
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        $file = self::$dir . '/app-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
