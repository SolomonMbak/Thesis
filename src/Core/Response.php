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
        // The app uses no camera, microphone, location or payment features: switch them off for every page.
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        // Do not tell visitors which PHP version runs the site.
        header_remove('X-Powered-By');
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    /**
     * In production with an https app_url, send plain-HTTP requests to the
     * same page over HTTPS. The address is built from app_url, never from the
     * Host header. A proxy that already ended TLS says so in
     * X-Forwarded-Proto; trusting it here can only skip a redirect.
     */
    public static function enforceHttps(): void
    {
        $appUrl = (string) Config::get('app_url', '');
        if (Config::isDevelopment() || !str_starts_with($appUrl, 'https://') || Request::isHttps()
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return;
        }
        $origin = (string) preg_replace('#^(https://[^/]+).*$#', '$1', $appUrl);
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $origin . (str_starts_with($uri, '/') && !str_starts_with($uri, '//') ? $uri : '/'), true, 301);
        exit;
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
