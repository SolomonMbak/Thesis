<?php
/**
 * Front controller: every page request comes through here.
 * index.php?r=area/action
 */
declare(strict_types=1);

use App\Core\App;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Installer;

require dirname(__DIR__) . '/src/bootstrap.php';

// Until the installer has run there is no database to talk to.
if (!Installer::isInstalled()) {
    header('Location: ' . Request::basePath() . '/install.php', true, 302);
    exit;
}

Response::sendSecurityHeaders();
header('Cache-Control: no-store');

$expired = Session::start();
$app = new App(Request::fromGlobals(), $_SESSION);
if ($expired) {
    $app->flash->info('You were signed out after a period of inactivity. Please sign in again.');
}

try {
    $router = require APP_ROOT . '/src/routes.php';
    $router->dispatch($app);
} catch (HttpException $ex) {
    renderError($app, $ex->status, $ex->getMessage());
} catch (PDOException $ex) {
    Logger::exception($ex);
    renderError($app, 500, 'The database could not be reached or returned an error. '
        . 'Please try again in a minute. If this keeps happening, ask the administrator to check the health page and the logs.');
} catch (Throwable $ex) {
    Logger::exception($ex);
    renderError($app, 500, 'Something went wrong on our side. The details have been logged. '
        . 'Please go back and try again.');
}

/** Friendly error page that does not need the database. */
function renderError(App $app, int $status, string $message): void
{
    http_response_code($status);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $titles = [400 => 'Form expired', 401 => 'Please sign in', 403 => 'Not allowed',
        404 => 'Page not found', 405 => 'Not allowed', 500 => 'Something went wrong'];
    echo $app->view->render('errors/error', [
        'title' => $titles[$status] ?? 'Error',
        'status' => $status,
        'message' => $message,
        'csrf' => $app->csrf,
        'flashes' => [],
    ], 'layout/plain');
}
