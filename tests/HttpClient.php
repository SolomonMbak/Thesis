<?php
declare(strict_types=1);

namespace Tests;

/**
 * For HTTP-level tests: starts PHP's built-in server on the public folder
 * against the test database (TT_TEST_DB=1, see Config), and acts as one
 * browser session with cookies and CSRF tokens.
 */
final class HttpClient
{
    /** @var resource|null */
    private static $process = null;
    private static int $port = 0;
    private array $cookies = [];

    public static function startServer(): void
    {
        if (self::$process !== null) {
            return;
        }
        self::$port = self::freePort();
        $env = getenv() + ['TT_TEST_DB' => '1'];
        $env['TT_TEST_DB'] = '1';
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        self::$process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, '-t', APP_ROOT . '/public'],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes, APP_ROOT, $env
        );
        if (!is_resource(self::$process)) {
            throw new \RuntimeException('Could not start the PHP built-in server for the HTTP tests.');
        }
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $error, 0.2);
            if ($socket) {
                fclose($socket);
                register_shutdown_function([self::class, 'stopServer']);
                return;
            }
            usleep(100000);
        }
        throw new \RuntimeException('The PHP built-in server did not start.');
    }

    public static function stopServer(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
    }

    /** @return array{status:int, headers:array<string,string>, body:string} */
    public function get(string $pathAndQuery): array
    {
        return $this->request('GET', $pathAndQuery);
    }

    /** POST a form; the CSRF token of this session is added. */
    public function post(string $pathAndQuery, array $fields, ?string $csrfFrom = null): array
    {
        $fields['_csrf'] = $this->csrf($csrfFrom ?? '/index.php?r=help');
        return $this->request('POST', $pathAndQuery, http_build_query($fields), 'application/x-www-form-urlencoded');
    }

    /** POST to an API endpoint with the X-CSRF-Token header, as app.js does. */
    public function postApi(string $path, array $fields): array
    {
        return $this->request('POST', $path, http_build_query($fields), 'application/x-www-form-urlencoded',
            ['X-CSRF-Token: ' . $this->csrf('/index.php?r=help')]);
    }

    public function login(string $email, string $password): void
    {
        $response = $this->post('/index.php?r=auth/login', ['email' => $email, 'password' => $password], '/index.php?r=auth/login');
        if ($response['status'] !== 303) {
            throw new AssertionFailed("Sign-in as {$email} failed (HTTP {$response['status']}).");
        }
    }

    private function csrf(string $page): string
    {
        $body = $this->get($page)['body'];
        if (!preg_match('/name="csrf-token" content="([^"]+)"/', $body, $m)) {
            throw new AssertionFailed("No CSRF token on {$page}.");
        }
        return html_entity_decode($m[1]);
    }

    private function request(string $method, string $pathAndQuery, string $body = '', string $type = '', array $extraHeaders = []): array
    {
        $headers = $extraHeaders;
        if ($this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($this->cookies), $this->cookies));
        }
        if ($type !== '') {
            $headers[] = 'Content-Type: ' . $type;
        }
        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body,
            'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 30,
        ]]);
        $content = @file_get_contents('http://127.0.0.1:' . self::$port . $pathAndQuery, false, $context);
        $status = 0;
        $parsed = [];
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = array_map('trim', explode(':', $line, 2));
                $parsed[strtolower($name)] = $value;
                if (strtolower($name) === 'set-cookie' && preg_match('/^([^=]+)=([^;]*)/', $value, $c)) {
                    $this->cookies[$c[1]] = $c[2];
                }
            }
        }
        return ['status' => $status, 'headers' => $parsed, 'body' => (string) $content];
    }

    private static function freePort(): int
    {
        for ($i = 0; $i < 20; $i++) {
            $port = random_int(18100, 18900);
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if (!$socket) {
                return $port;
            }
            fclose($socket);
        }
        throw new \RuntimeException('No free port for the test server.');
    }
}
