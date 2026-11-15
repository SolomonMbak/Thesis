<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Maps ?r=area/action to a controller method and enforces, per route:
 *   - who may call it (public, guest, user, admin)
 *   - a valid CSRF token on every POST
 * Access is checked here for every action, not only hidden in menus.
 */
final class Router
{
    public const PUBLIC = 'public';   // anyone, signed in or not
    public const GUEST = 'guest';     // sign-in page and similar
    public const USER = 'user';       // any signed-in user (officer or admin)
    public const ADMIN = 'admin';     // administrators only

    /** @var array<string, array{0:class-string,1:string,2:string}> */
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

    public function has(string $method, string $route): bool
    {
        return isset($this->routes[strtoupper($method) . ' ' . $route]);
    }

    public function dispatch(App $app): void
    {
        $request = $app->request;
        $route = $request->route() ?: $this->defaultRoute;
        $key = $request->method() . ' ' . $route;

        if (!isset($this->routes[$key])) {
            $otherMethod = $request->isPost() ? 'GET' : 'POST';
            if ($this->has($otherMethod, $route)) {
                throw new HttpException(405, 'That action cannot be done this way. Go back and use the button on the page.');
            }
            throw new HttpException(404, 'The page you asked for does not exist.');
        }

        [$controller, $action, $access] = $this->routes[$key];
        $this->checkAccess($app, $access);

        if ($request->isPost() && !$app->csrf->checkRequest($request)) {
            throw new HttpException(400, 'This form has expired. Go back, refresh the page and try again.');
        }

        (new $controller($app))->$action();
    }

    private function checkAccess(App $app, string $access): void
    {
        if ($access === self::PUBLIC || $access === self::GUEST) {
            return;
        }
        if (!$app->auth()->check()) {
            if ($app->request->isPost() || $app->request->method() !== 'GET') {
                throw new HttpException(401, 'Your session has ended. Please sign in again.');
            }
            $app->flash->info('Please sign in to continue.');
            Response::redirect(url('auth/login'));
        }
        if ($access === self::ADMIN && !$app->auth()->isAdmin()) {
            throw new HttpException(403, 'Only administrators can open this page.');
        }
    }
}
