<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sending headers, redirects and JSON.
 */
final class Response
{
    /** Security headers for every PHP response (BUILD_SPEC section 13). */
    public static function sendSecurityHeaders(): void
    {
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' https://cdnjs.cloudflare.com " . Theme::cspSource(),
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_safe($data);
        exit;
    }
}
