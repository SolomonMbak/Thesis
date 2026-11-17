<?php
declare(strict_types=1);

namespace App\Core;

use App\Data\MemberRepository;
use App\Data\UserRepository;
use PDO;

/**
 * Holds the services for one web request. Database-backed services are
 * created on first use, so pages that do not need the database (such as
 * error pages) still work when it is down.
 *
 * The current school is resolved once per request (BUILD_SPEC 13.1): the
 * school id kept in the session is checked against the user's membership
 * on every request, so a removed member loses access at once.
 */
final class App
{
    private const SCHOOL_KEY = '_school_id';
    private const SCHOOL_ROLE_KEY = '_school_role';

    public readonly Csrf $csrf;
    public readonly Flash $flash;
    public readonly View $view;

    private ?Auth $auth = null;
    private ?Audit $audit = null;
    private ?SchoolContext $school = null;
    private bool $schoolResolved = false;
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
            $this->auth = new Auth($this->db(), $limiter, $this->audit(), $this->session, $this->request);
        }
        return $this->auth;
    }

    /**
     * The school the signed-in user is working in, with their role, or null
     * (signed out, or a member of no school yet).
     */
    public function school(): ?SchoolContext
    {
        if ($this->schoolResolved) {
            return $this->school;
        }
        $this->schoolResolved = true;
        $user = $this->auth()->user();
        if ($user === null) {
            return null;
        }
        $members = new MemberRepository($this->db());
        $userId = (int) $user['id'];
        $wanted = (int) ($this->session[self::SCHOOL_KEY] ?? $user['last_school_id'] ?? 0);
        $row = $wanted > 0 ? $members->membership($userId, $wanted) : null;
        $row ??= $members->memberships($userId)[0] ?? null;
        if ($row === null) {
            unset($this->session[self::SCHOOL_KEY], $this->session[self::SCHOOL_ROLE_KEY]);
            return null;
        }
        $this->remember($row);
        $this->school = new SchoolContext($row, $row['role'], $userId);
        return $this->school;
    }

    /** Switch to another school the user belongs to. Returns false if they do not belong to it. */
    public function switchSchool(int $schoolId): bool
    {
        $user = $this->auth()->user();
        $row = $user ? (new MemberRepository($this->db()))->membership((int) $user['id'], $schoolId) : null;
        if ($row === null) {
            return false;
        }
        Session::regenerate();
        $this->remember($row);
        $this->school = new SchoolContext($row, $row['role'], (int) $user['id']);
        $this->schoolResolved = true;
        return true;
    }

    /** Schools the signed-in user belongs to, for the switcher. */
    public function memberships(): array
    {
        $id = $this->auth()->id();
        return $id ? (new MemberRepository($this->db()))->memberships($id) : [];
    }

    /** A value kept in the session between pages (for example a key being set up). */
    public function sessionGet(string $key): mixed
    {
        return $this->session[$key] ?? null;
    }

    public function sessionSet(string $key, mixed $value): void
    {
        $this->session[$key] = $value;
    }

    public function sessionForget(string $key): void
    {
        unset($this->session[$key]);
    }

    /** Write an audit entry for the signed-in user in the current school. */
    public function log(string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void
    {
        $this->audit()->log($this->auth()->id(), $action, $entity, $entityId, $details, $this->request->ip(), $this->school()?->id);
    }

    /** Store the school in the session; a new school or a changed role gets a new session id (BUILD_SPEC 13.3). */
    private function remember(array $row): void
    {
        $changed = (int) ($this->session[self::SCHOOL_KEY] ?? 0) !== (int) $row['id']
            || ($this->session[self::SCHOOL_ROLE_KEY] ?? null) !== $row['role'];
        if ($changed && isset($this->session[self::SCHOOL_ROLE_KEY])) {
            Session::regenerate();
        }
        $this->session[self::SCHOOL_KEY] = (int) $row['id'];
        $this->session[self::SCHOOL_ROLE_KEY] = $row['role'];
        $user = $this->auth()->user();
        if ($user && (int) ($user['last_school_id'] ?? 0) !== (int) $row['id']) {
            (new UserRepository($this->db()))->setLastSchool((int) $user['id'], (int) $row['id']);
        }
    }
}
