<?php
/**
 * Shared start-up for web requests and CLI scripts:
 * autoloader, configuration, time zone and error handling.
 */
declare(strict_types=1);

use App\Core\Config;
use App\Core\Logger;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/src/helpers.php';

// PSR-4 style autoloader: App\Core\Db lives in src/Core/Db.php.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = APP_ROOT . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Config::load(APP_ROOT . '/config');
date_default_timezone_set((string) Config::get('timezone', 'Africa/Lagos'));

// Never show error details to visitors in production; they go to the log instead.
error_reporting(E_ALL);
ini_set('display_errors', Config::isDevelopment() || PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');

// Turn warnings and notices into exceptions so nothing fails silently.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

Logger::setDirectory(APP_ROOT . '/storage/logs');
