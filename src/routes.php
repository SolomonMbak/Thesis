<?php
/**
 * Route table: ?r=<route> => controller method, with who may use it.
 * Every route declares its access level; POST routes are CSRF-checked
 * by the router.
 */
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\SemesterController;
use App\Controllers\SettingsController;
use App\Core\Router;

$router = new Router('dashboard');
$U = Router::USER;
$A = Router::ADMIN;

$router->get('auth/login', AuthController::class, 'showLogin', Router::GUEST);
$router->post('auth/login', AuthController::class, 'login', Router::GUEST);
$router->post('auth/logout', AuthController::class, 'logout', $U);

$router->get('dashboard', DashboardController::class, 'index', $U);

// 1. Master data: the four simple tables share the CRUD actions
foreach ([
    'lecturers' => App\Controllers\LecturerController::class,
    'groups' => App\Controllers\GroupController::class,
    'rooms' => App\Controllers\RoomController::class,
    'courses' => App\Controllers\CourseController::class,
] as $prefix => $controller) {
    $router->get($prefix, $controller, 'index', $U);
    $router->get("{$prefix}/create", $controller, 'create', $U);
    $router->post("{$prefix}/store", $controller, 'store', $U);
    $router->get("{$prefix}/edit", $controller, 'edit', $U);
    $router->post("{$prefix}/update", $controller, 'update', $U);
    $router->post("{$prefix}/delete", $controller, 'delete', $U);
}
$router->get('periods', App\Controllers\PeriodController::class, 'index', $U);
$router->post('periods/generate', App\Controllers\PeriodController::class, 'generate', $U);
$router->post('periods/toggle', App\Controllers\PeriodController::class, 'toggle', $U);
$router->get('availability', App\Controllers\AvailabilityController::class, 'index', $U);
$router->post('availability/toggle', App\Controllers\AvailabilityController::class, 'toggle', $U);
$router->post('availability/bulk', App\Controllers\AvailabilityController::class, 'bulk', $U);
$router->get('sessions', App\Controllers\SessionController::class, 'index', $U);
$router->post('sessions/build', App\Controllers\SessionController::class, 'build', $U);
$router->get('import', App\Controllers\ImportController::class, 'index', $U);
$router->post('import/upload', App\Controllers\ImportController::class, 'upload', $U);
$router->get('import/template', App\Controllers\ImportController::class, 'template', $U);

// 3. Feasibility
$router->get('feasibility', App\Controllers\FeasibilityController::class, 'index', $U);
$router->post('feasibility/run', App\Controllers\FeasibilityController::class, 'run', $U);

// 2. Constraints and settings (general settings are admin only, checked again in the action)
$router->get('settings', SettingsController::class, 'index', $U);
$router->post('settings/weights', SettingsController::class, 'saveWeights', $U);
$router->post('settings/ga', SettingsController::class, 'saveGa', $U);
$router->post('settings/general', SettingsController::class, 'saveGeneral', $A);

// 6. Administration
$router->get('semesters', SemesterController::class, 'index', $A);
$router->post('semesters/store', SemesterController::class, 'store', $A);
$router->post('semesters/activate', SemesterController::class, 'activate', $A);
$router->post('semesters/copy', SemesterController::class, 'copy', $A);

return $router;
