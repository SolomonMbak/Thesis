<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Thin, read-only wrapper over the current HTTP request.
 * Controllers use this instead of touching superglobals directly.
 */
final class Request
{
    private ?array $pathRoute = null;

    public function __construct(
        private array $get,
        private array $post,
        private array $server,
        private array $files = [],
        private array $cookies = []
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /** The route name from ?r=... (limited to safe characters), or from a pretty path such as /s/demo. */
    public function route(): string
    {
        $r = (string) ($this->get['r'] ?? '');
        if ($r !== '') {
            return preg_match('#^[a-z0-9_/-]{1,60}$#', $r) ? $r : '';
        }
        return $this->pathRoute()['r'] ?? '';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $this->pathRoute()[$key] ?? $default;
    }

    /**
     * Pretty public paths (BUILD_SPEC 10.4): /s/{slug}, /s/{slug}/{level|lecturer|room}/{id} and /help.
     * Anything else is left to ?r=.
     */
    private function pathRoute(): array
    {
        if ($this->pathRoute !== null) {
            return $this->pathRoute;
        }
        $path = (string) parse_url((string) ($this->server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $base = self::basePath();
        // With the root .htaccess fallback the address may leave out "/public"
        // (/timetable/s/demo is served from /timetable/public/), so try both.
        foreach (array_unique([$base, (string) preg_replace('#/public$#', '', $base)]) as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }
        $path = preg_replace('#^/index\.php#', '', $path);
        if (preg_match('#^/s/([a-z0-9-]{1,60})(?:/(level|lecturer|room)/(\d{1,10}))?/?$#', $path, $m)) {
            return $this->pathRoute = ['r' => 'public/school', 'slug' => $m[1], 'view' => $m[2] ?? '', 'id' => $m[3] ?? ''];
        }
        if (preg_match('#^/help/?$#', $path)) {
            return $this->pathRoute = ['r' => 'help'];
        }
        // Any other path (for example /phpinfo.php or /backup.zip) is not a page: answer 404,
        // not the home page, so scanners and search engines see that nothing is there.
        if ($path !== '' && $path !== '/' && !preg_match('#^/public/?$#', $path)) {
            return $this->pathRoute = ['r' => 'not-found'];
        }
        return $this->pathRoute = [];
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

    public function cookie(string $name): ?string
    {
        return isset($this->cookies[$name]) && is_string($this->cookies[$name]) ? $this->cookies[$name] : null;
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    public function ip(): string
    {
        // REMOTE_ADDR, because forwarded headers can be forged by the client. Only when the request
        // comes from a proxy listed in trusted_proxies (for example a load balancer or Cloudflare) is
        // X-Forwarded-For used, and then only the address that proxy added (the last one).
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = (array) Config::get('trusted_proxies', []);
        if ($trusted === [] || !in_array($remote, $trusted, true)) {
            return $remote;
        }
        $chain = array_map('trim', explode(',', (string) ($this->server['HTTP_X_FORWARDED_FOR'] ?? '')));
        $client = (string) end($chain);
        return filter_var($client, FILTER_VALIDATE_IP) ? $client : $remote;
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
