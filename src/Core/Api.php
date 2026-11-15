<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Installer;

/**
 * Start-up for JSON endpoints in public/api/: security headers, session,
 * sign-in check and (for POST) CSRF check. Failures answer in JSON.
 */
final class Api
{
    public static function boot(string $method = 'GET'): App
    {
        Response::sendSecurityHeaders();
        if (!Installer::isInstalled()) {
            Response::json(['error' => 'The system is not installed yet.'], 503);
        }
        Session::start();
        $app = new App(Request::fromGlobals(), $_SESSION);

        if ($app->request->method() !== $method) {
            Response::json(['error' => "Use {$method} for this endpoint."], 405);
        }
        try {
            $signedIn = $app->auth()->check();
        } catch (\PDOException $ex) {
            Logger::exception($ex);
            Response::json(['error' => 'The database is not reachable.'], 500);
        }
        if (!$signedIn) {
            Response::json(['error' => 'Your session has ended. Please sign in again.'], 401);
        }
        if ($method === 'POST' && !$app->csrf->checkRequest($app->request)) {
            Response::json(['error' => 'The page has expired. Refresh it and try again.'], 400);
        }
        // Release the session lock so polling does not block the user's other pages.
        session_write_close();
        return $app;
    }
}
