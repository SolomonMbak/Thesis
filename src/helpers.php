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

/** Encode data as JSON that is safe to embed in HTML or return from an API. */
function json_safe(mixed $data): string
{
    return json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    );
}
