<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Thin, read-only wrapper over the current HTTP request.
 * Controllers use this instead of touching superglobals directly.
 */
final class Request
{
    public function __construct(
        private array $get,
        private array $post,
        private array $server,
        private array $files = []
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /** The route name from ?r=..., limited to safe characters. */
    public function route(): string
    {
        $r = (string) ($this->get['r'] ?? '');
        return preg_match('#^[a-z0-9_/-]{1,60}$#', $r) ? $r : '';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function allInput(): array
    {
        return $this->post;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    public function ip(): string
    {
        // REMOTE_ADDR only: forwarded headers can be forged by the client.
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        return ($https !== '' && strtolower((string) $https) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    /**
     * URL path of the public/ folder, e.g. "" or "/timetable".
     * Scripts in public/api/ report their own folder, so that suffix is removed.
     */
    public static function basePath(): string
    {
        $configured = (string) Config::get('app_url', '');
        if ($configured !== '') {
            return rtrim((string) parse_url($configured, PHP_URL_PATH), '/');
        }
        $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
        $dir = preg_replace('#/api$#', '', $dir);
        return rtrim((string) $dir, '/');
    }
}
