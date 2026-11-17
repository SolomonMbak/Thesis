<?php
/**
 * Route table: ?r=<route> (or a pretty path such as /s/demo) => controller
 * method, with who may use it. School levels are checked against the user's
 * role in the current school: viewer < officer < admin < owner.
 * POST routes are CSRF-checked by the router.
 */
declare(strict_types=1);

use App\Controllers as C;
use App\Core\Router;

// The bare address opens the home page (signed-in users go on to their dashboard).
$router = new Router('home');
$P = Router::PUBLIC;
$G = Router::GUEST;
$AUTH = Router::AUTH;
$V = Router::VIEWER;
$O = Router::OFFICER;
$A = Router::ADMIN;
$OWN = Router::OWNER;
$PF = Router::PLATFORM;

// Accounts
$router->get('auth/login', C\AuthController::class, 'showLogin', $G);
$router->post('auth/login', C\AuthController::class, 'login', $G);
$router->post('auth/logout', C\AuthController::class, 'logout', $AUTH);
$router->get('auth/two-step', C\AuthController::class, 'showTwoStep', $G);
$router->post('auth/two-step', C\AuthController::class, 'twoStep', $G);
$router->get('auth/new-password', C\AuthController::class, 'showNewPassword', $AUTH);
$router->post('auth/new-password', C\AuthController::class, 'newPassword', $AUTH);
$router->get('auth/signup', C\AuthController::class, 'showSignup', $G);
$router->post('auth/signup', C\AuthController::class, 'signup', $G);
$router->get('auth/forgot', C\AuthController::class, 'showForgot', $G);
$router->post('auth/forgot', C\AuthController::class, 'forgot', $G);
$router->get('auth/reset', C\AuthController::class, 'showReset', $G);
$router->post('auth/reset', C\AuthController::class, 'reset', $G);
$router->get('auth/verify', C\AuthController::class, 'verify', $P);
$router->get('auth/verify-required', C\AuthController::class, 'verifyRequired', $AUTH);
$router->post('auth/resend-verification', C\AuthController::class, 'resendVerification', $AUTH);
$router->get('invite/accept', C\InviteController::class, 'show', $P);
$router->post('invite/accept', C\InviteController::class, 'accept', $P);
$router->get('account/profile', C\AccountController::class, 'profile', $AUTH);
$router->post('account/profile', C\AccountController::class, 'saveProfile', $AUTH);
$router->post('account/password', C\AccountController::class, 'savePassword', $AUTH);
$router->post('account/sign-out-everywhere', C\AccountController::class, 'signOutEverywhere', $AUTH);
$router->get('account/two-step', C\AccountController::class, 'twoStep', $AUTH);
$router->post('account/two-step/enable', C\AccountController::class, 'enableTwoStep', $AUTH);
$router->post('account/two-step/disable', C\AccountController::class, 'disableTwoStep', $AUTH);
$router->post('account/two-step/new-codes', C\AccountController::class, 'newRecoveryCodes', $AUTH);
$router->get('account/two-step/codes', C\AccountController::class, 'showRecoveryCodes', $AUTH);
$router->get('schools/new', C\SchoolController::class, 'showNew', $AUTH);
$router->post('schools/create', C\SchoolController::class, 'create', $AUTH);
$router->post('schools/switch', C\SchoolController::class, 'switch', $AUTH);

// Help (open to everyone) and public timetables
$router->get('help', C\HelpController::class, 'index', $P);
$router->get('home', C\HomeController::class, 'index', $P);
$router->get('about', C\HomeController::class, 'about', $P);
$router->get('public/school', C\PublicController::class, 'page', $P);
$router->get('public/timetable', C\PublicController::class, 'legacy', $P);
$router->get('school/logo', C\SchoolController::class, 'logo', $P);

// Get started
$router->get('setup', C\SetupController::class, 'index', $V);
$router->get('dashboard', C\DashboardController::class, 'index', $V);

// School data
$router->get('school/profile', C\SchoolController::class, 'profile', $O);
$router->post('school/profile', C\SchoolController::class, 'saveProfile', $A);
$router->post('school/logo-upload', C\SchoolController::class, 'uploadLogo', $A);
$router->post('school/logo-remove', C\SchoolController::class, 'removeLogo', $A);
$router->get('periods', C\PeriodController::class, 'index', $O);
$router->post('periods/toggle', C\PeriodController::class, 'toggle', $O);
foreach ([
    'rooms' => C\RoomController::class,
    'groups' => C\GroupController::class,
    'lecturers' => C\LecturerController::class,
    'courses' => C\CourseController::class,
] as $prefix => $controller) {
    $router->get($prefix, $controller, 'index', $O);
    $router->get("{$prefix}/create", $controller, 'create', $O);
    $router->post("{$prefix}/store", $controller, 'store', $O);
    $router->get("{$prefix}/edit", $controller, 'edit', $O);
    $router->post("{$prefix}/update", $controller, 'update', $O);
    $router->post("{$prefix}/delete", $controller, 'delete', $O);
}
$router->get('availability', C\AvailabilityController::class, 'index', $O);
$router->post('availability/toggle', C\AvailabilityController::class, 'toggle', $O);
$router->post('availability/bulk', C\AvailabilityController::class, 'bulk', $O);
$router->post('availability/skip', C\AvailabilityController::class, 'skip', $O);
$router->get('import', C\ImportController::class, 'index', $O);
$router->post('import/upload', C\ImportController::class, 'upload', $O);
$router->get('import/template', C\ImportController::class, 'template', $O);
$router->get('sessions', C\SessionController::class, 'index', $O);
$router->post('sessions/build', C\SessionController::class, 'build', $O);

// Timetables
$router->get('feasibility', C\FeasibilityController::class, 'index', $O);
$router->post('feasibility/run', C\FeasibilityController::class, 'run', $O);
$router->get('generate', C\GenerateController::class, 'index', $O);
$router->post('generate/start', C\GenerateController::class, 'start', $O);
$router->get('generate/job', C\GenerateController::class, 'job', $V);
$router->post('generate/stop', C\GenerateController::class, 'stop', $O);
$T = C\TimetableController::class;
$router->get('timetables', $T, 'index', $V);
$router->get('timetables/view', $T, 'view', $V);
$router->get('timetables/report', $T, 'report', $V);
$router->get('timetables/compare', $T, 'compare', $V);
$router->get('timetables/export', $T, 'export', $V);
$router->get('timetables/export-dates', $T, 'exportDates', $V);
$router->get('timetables/print', $T, 'printView', $V);
$router->get('published', $T, 'published', $V);
$router->post('timetables/publish', $T, 'publish', $O);
$router->post('timetables/unpublish', $T, 'unpublish', $O);
$router->get('timetables/manual', $T, 'manualForm', $O);
$router->post('timetables/manual-upload', $T, 'manualUpload', $O);
$router->get('timetables/manual-template', $T, 'manualTemplate', $O);

// Manage
$router->get('members', C\MemberController::class, 'index', $A);
$router->post('members/invite', C\MemberController::class, 'invite', $A);
$router->post('members/add', C\MemberController::class, 'add', $A);
$router->post('members/role', C\MemberController::class, 'changeRole', $A);
$router->post('members/remove', C\MemberController::class, 'remove', $A);
$router->post('members/revoke', C\MemberController::class, 'revoke', $A);
$router->get('rules', C\RulesController::class, 'index', $A);
$router->post('rules', C\RulesController::class, 'save', $A);
$router->get('semesters', C\SemesterController::class, 'index', $A);
$router->post('semesters/store', C\SemesterController::class, 'store', $A);
$router->post('semesters/activate', C\SemesterController::class, 'activate', $A);
$router->post('semesters/copy', C\SemesterController::class, 'copy', $A);
$router->get('semesters/calendar', C\SemesterController::class, 'calendar', $A);
$router->post('semesters/dates', C\SemesterController::class, 'saveDates', $A);
$router->post('semesters/closure-add', C\SemesterController::class, 'addClosure', $A);
$router->post('semesters/closure-remove', C\SemesterController::class, 'removeClosure', $A);
$router->get('audit', C\AuditController::class, 'index', $A);
$router->get('school/settings', C\SchoolController::class, 'settings', $A);
$router->post('school/ga', C\SchoolController::class, 'saveGa', $A);
$router->post('school/slug', C\SchoolController::class, 'saveSlug', $A);
$router->post('school/delete-old-jobs', C\SchoolController::class, 'deleteOldJobs', $A);
$router->post('school/transfer', C\SchoolController::class, 'transfer', $OWN);
$router->post('school/delete', C\SchoolController::class, 'delete', $OWN);

// Platform
$router->get('platform/schools', C\PlatformController::class, 'schools', $PF);
$router->post('platform/schools/status', C\PlatformController::class, 'setStatus', $PF);
$router->get('platform/settings', C\PlatformController::class, 'settings', $PF);
$router->post('platform/settings', C\PlatformController::class, 'saveSettings', $PF);
$router->post('platform/test-email', C\PlatformController::class, 'testEmail', $PF);
$router->get('health', C\HealthController::class, 'index', $PF);
$router->post('health/migrate', C\HealthController::class, 'migrate', $PF);
$router->get('backups', C\BackupController::class, 'index', $PF);
$router->post('backups/create', C\BackupController::class, 'create', $PF);
$router->get('backups/download', C\BackupController::class, 'download', $PF);

return $router;
