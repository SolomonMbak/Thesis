<?php
/**
 * First-run installer. Locks itself (storage/installed.lock) after success
 * and refuses to run again.
 */
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Installer;

require dirname(__DIR__) . '/src/bootstrap.php';

Response::sendSecurityHeaders();
header('Cache-Control: no-store');
Session::start();

$request = Request::fromGlobals();
$csrf = new Csrf($_SESSION);
$view = new View(APP_ROOT . '/views');
$base = ['csrf' => $csrf, 'flashes' => [], 'title' => 'Install'];

if (Installer::isInstalled()) {
    http_response_code(403);
    echo $view->render('install/locked', $base, 'layout/plain');
    exit;
}

$input = [
    'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'app_url' => '', 'institution_name' => '', 'department_name' => '',
    'admin_name' => '', 'admin_email' => '',
];
$errors = [];
$requirements = Installer::requirements();
$ready = Installer::requirementsMet();

if ($request->isPost() && $ready) {
    if (!$csrf->checkRequest($request)) {
        $errors['form'] = 'The form expired. Please fill it in again.';
    } else {
        $posted = array_map(fn ($v) => is_string($v) ? $v : '', $request->allInput());
        $input = array_merge($input, array_intersect_key($posted, $input));
        $errors = Installer::validate($posted);
        if (!$errors) {
            try {
                (new Installer())->install($posted);
                echo $view->render('install/done', $base + ['email' => $posted['admin_email']], 'layout/plain');
                exit;
            } catch (\Throwable $ex) {
                Logger::exception($ex);
                $errors['form'] = $ex->getMessage();
            }
        }
    }
}

echo $view->render('install/form', $base + [
    'requirements' => $requirements,
    'ready' => $ready,
    'input' => $input,
    'errors' => $errors,
], 'layout/plain');
