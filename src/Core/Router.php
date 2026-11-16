<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Maps a route to a controller method and enforces, per route, who may call
 * it and a valid CSRF token on every POST (for every action, not only in menus).
 *
 * School levels need a current school (App::school()) and at least that role
 * there: viewer < officer < admin < owner.
 */
final class Router
{
    public const PUBLIC = 'public';       // anyone, signed in or not
    public const GUEST = 'guest';         // sign-in, sign-up and similar
    public const AUTH = 'auth';           // any signed-in user, school or not
    public const VIEWER = 'viewer';
    public const OFFICER = 'officer';
    public const ADMIN = 'admin';
    public const OWNER = 'owner';
    public const PLATFORM = 'platform';   // platform administrators

    /** Routes an unverified user past the grace period can still use. */
    private const VERIFY_ALLOWED = ['auth/logout', 'auth/verify', 'auth/verify-required', 'auth/resend-verification', 'help'];

    private array $routes = [];

    public function __construct(private string $defaultRoute = 'dashboard')
    {
    }

    public function get(string $route, string $controller, string $action, string $access): void
    {
        $this->routes['GET ' . $route] = [$controller, $action, $access];
    }

    public function post(string $route, string $controller, string $action, string $access): void
    {
        $this->routes['POST ' . $route] = [$controller, $action, $access];
    }

    public function dispatch(App $app): void
    {
        $request = $app->request;
        $route = $request->route() ?: $this->defaultRoute;
        $key = $request->method() . ' ' . $route;
        if (!isset($this->routes[$key])) {
            $otherMethod = ($request->isPost() ? 'GET ' : 'POST ') . $route;
            throw isset($this->routes[$otherMethod])
                ? new HttpException(405, 'That action cannot be done this way. Go back and use the button on the page.')
                : new HttpException(404, 'The page you asked for does not exist.');
        }
        [$controller, $action, $access] = $this->routes[$key];
        $this->checkAccess($app, $access, $route);
        if ($request->isPost() && !$app->csrf->checkRequest($request)) {
            throw new HttpException(400, 'This form has expired. Go back, refresh the page and try again.');
        }
        (new $controller($app))->$action();
    }

    private function checkAccess(App $app, string $access, string $route): void
    {
        if ($access === self::PUBLIC || $access === self::GUEST) {
            return;
        }
        $auth = $app->auth();
        if (!$auth->check()) {
            if ($app->request->isPost()) {
                throw new HttpException(401, 'Your session has ended. Please sign in again.');
            }
            $app->flash->info('Please sign in to continue.');
            Response::redirect(url('auth/login'));
        }
        if ($auth->mustVerify() && !in_array($route, self::VERIFY_ALLOWED, true)) {
            Response::redirect(url('auth/verify-required'));
        }
        if ($access === self::AUTH) {
            return;
        }
        if ($access === self::PLATFORM) {
            $auth->isPlatformAdmin() || throw new HttpException(403, 'Only platform administrators can open this page.');
            return;
        }
        $school = $app->school();
        if ($school === null) {
            $app->flash->info('You are not a member of any school yet. Create your school to get started, or ask a school administrator to invite you.');
            Response::redirect(url('schools/new'));
        }
        if (!$school->isActive()) {
            throw new HttpException(403, 'This school\'s workspace is suspended by the platform administrator. '
                . 'Switch to another school from the top bar, or contact the platform administrator.');
        }
        if (!$school->can($access)) {
            throw new HttpException(403, 'In ' . $school->name() . ' you are ' . $school->roleLabel() . ', which cannot open this page. '
                . 'Ask an owner or administrator of the school if you need it.');
        }
    }
}
