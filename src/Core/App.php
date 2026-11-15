<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Holds the services for one web request. Database-backed services are
 * created on first use, so pages that do not need the database (such as
 * error pages) still work when it is down.
 */
final class App
{
    public readonly Csrf $csrf;
    public readonly Flash $flash;
    public readonly View $view;

    private ?Auth $auth = null;
    private ?Audit $audit = null;
    private array $session;

    public function __construct(public readonly Request $request, array &$session)
    {
        $this->session = &$session;
        $this->csrf = new Csrf($session);
        $this->flash = new Flash($session);
        $this->view = new View(APP_ROOT . '/views');
    }

    public function db(): PDO
    {
        return Db::pdo();
    }

    public function audit(): Audit
    {
        return $this->audit ??= new Audit($this->db());
    }

    public function auth(): Auth
    {
        if ($this->auth === null) {
            $limiter = new RateLimiter(
                $this->db(),
                (int) Config::get('login_max_failures', 5),
                (int) Config::get('login_window_minutes', 15)
            );
            $this->auth = new Auth($this->db(), $limiter, $this->audit(), $this->session);
        }
        return $this->auth;
    }

    /** Write an audit entry for the signed-in user. */
    public function log(string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void
    {
        $this->audit()->log($this->auth()->id(), $action, $entity, $entityId, $details, $this->request->ip());
    }
}
