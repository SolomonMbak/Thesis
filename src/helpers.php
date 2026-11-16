<?php
/**
 * Small global helpers used by templates. Kept deliberately few.
 */
declare(strict_types=1);

use App\Core\Request;

/** Escape any value for safe output in HTML text and attributes. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a link to a route, e.g. url('courses/edit', ['id' => 4]). */
function url(string $route = '', array $params = []): string
{
    $query = $route === '' ? $params : ['r' => $route] + $params;
    // Slashes are safe in a query string; keeping them makes links readable.
    $qs = $query ? '?' . str_replace('%2F', '/', http_build_query($query)) : '';
    return Request::basePath() . '/index.php' . $qs;
}

/** Link to a file under public/assets, with a version stamp to beat stale caches. */
function asset(string $path): string
{
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return Request::basePath() . '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

/**
 * Make a text value safe for a CSV that people open in Excel: a cell that
 * starts with = + - @ (or a tab or carriage return) would run as a formula,
 * so it gets a leading apostrophe.
 */
function csv_text(mixed $value): string
{
    $text = (string) ($value ?? '');
    return preg_match('/^[=+\-@\t\r]/', $text) ? "'" . $text : $text;
}

/** Encode data as JSON that is safe to embed in HTML or return from an API. */
function json_safe(mixed $data): string
{
    return json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    );
}

/**
 * Link to a school's public timetable page: /s/{slug}, or one grid of it,
 * e.g. public_url('demo', 'level', 3) => /s/demo/level/3.
 */
function public_url(string $slug, string $view = '', int $id = 0): string
{
    $path = '/s/' . rawurlencode($slug);
    if ($view !== '' && $id > 0) {
        $path .= '/' . rawurlencode($view) . '/' . $id;
    }
    return Request::basePath() . $path;
}

/** Full address for links that leave the site (emails, the share box), from app_url or this request. */
function absolute_url(string $pathOrUrl): string
{
    if (preg_match('#^https?://#', $pathOrUrl)) {
        return $pathOrUrl;
    }
    $configured = (string) App\Core\Config::get('app_url', '');
    if ($configured !== '') {
        $parts = parse_url($configured);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $origin . $pathOrUrl;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Only a plain host name (and port) is used, so a forged Host header cannot inject markup or paths.
    $host = preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host) ? $host : 'localhost';
    return (Request::isHttps() ? 'https' : 'http') . '://' . $host . $pathOrUrl;
}

/**
 * Full address for a link inside an email (verification, password reset,
 * invitation). In production it must come from app_url: building it from the
 * request's Host header would let an attacker send someone a reset link that
 * points to the attacker's site. Returns null when app_url is not set.
 */
function mail_url(string $path): ?string
{
    if ((string) App\Core\Config::get('app_url', '') === '' && App\Core\Config::get('app_env') === 'production') {
        return null;
    }
    return absolute_url($path);
}
