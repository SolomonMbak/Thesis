<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\SchoolContext;
use App\Data\HelpProgressRepository;
use App\Data\SemesterRepository;
use App\Data\SettingsRepository;
use App\Data\TwoFactorRepository;
use App\Services\SetupGuide;

/**
 * Base for all controllers: rendering with the shared layout, redirects
 * and access to the current school.
 *
 * render() gathers what every page shows around its content: the menu for
 * the user's role, breadcrumbs, the help panel and first-visit tip for the
 * page, Setup Guide progress and the theme.
 */
abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    /** The current school. Routes for school roles are only reached with one (the router checks). */
    protected function school(): SchoolContext
    {
        return $this->app->school() ?? throw new HttpException(403, 'Choose or create a school first.');
    }

    /** Render a page inside the main layout (top bar and menu). */
    protected function render(string $template, array $data = []): void
    {
        echo $this->app->view->render($template, $data + $this->layoutData($data['title'] ?? ''));
    }

    /** Render a page in the plain layout (sign-in, sign-up, errors). */
    protected function renderPlain(string $template, array $data = []): void
    {
        $auth = $this->app->auth();
        echo $this->app->view->render($template, $data + [
            'title' => '',
            'csrf' => $this->app->csrf,
            'flashes' => $this->app->flash->take(),
            'platformName' => (new SettingsRepository($this->app->db()))->get('platform_name', 'Timetable'),
            'themePref' => $auth->check() ? (string) ($auth->user()['theme'] ?? 'system') : null,
        ], 'layout/plain');
    }

    protected function redirect(string $route, array $params = []): never
    {
        Response::redirect(url($route, $params));
    }

    /** Extra check inside an action when one route serves several roles. */
    protected function requireRole(string $role): void
    {
        if (!$this->school()->can($role)) {
            throw new HttpException(403, 'Your role in this school (' . $this->school()->roleLabel() . ') cannot do this.');
        }
    }

    /** The active semester of the current school, or null. */
    protected function activeSemester(): ?array
    {
        return (new SemesterRepository($this->app->db(), $this->school()))->active();
    }

    private function layoutData(string $title): array
    {
        $auth = $this->app->auth();
        $school = $this->app->school();
        $route = $this->app->request->route() ?: 'dashboard';
        $menu = $this->menuFor($school, $auth->isPlatformAdmin());
        $data = [
            'title' => $title,
            'user' => $auth->user(),
            'isPlatformAdmin' => $auth->isPlatformAdmin(),
            'school' => $school,
            'memberships' => $this->app->memberships(),
            'csrf' => $this->app->csrf,
            'flashes' => $this->app->flash->take(),
            'currentRoute' => $route,
            'menu' => $menu,
            'crumbs' => $this->crumbs($menu, $route, $title, $school),
            'helpKey' => self::helpKey($route),
            'themePref' => (string) ($auth->user()['theme'] ?? 'system'),
            'verifyDaysLeft' => $auth->verifyDaysLeft(),
            'platformName' => (new SettingsRepository($this->app->db()))->get('platform_name', 'Timetable'),
            'activeSemester' => null, 'allSemesters' => [], 'setup' => null, 'tip' => null, 'tourAutostart' => false,
        ];
        if ($school !== null && $school->isActive()) {
            $semesters = new SemesterRepository($this->app->db(), $school);
            $data['activeSemester'] = $semesters->active();
            $data['allSemesters'] = $school->can('admin') ? $semesters->all() : [];
            $data['setup'] = $this->setupSummary($school, $route);
            $help = new HelpProgressRepository($this->app->db(), $school, (int) $auth->id());
            $done = $help->done();
            $tips = require APP_ROOT . '/src/tips.php';
            $key = $data['helpKey'];
            if ($key !== null && isset($tips[$key]) && !isset($done['tip_' . $key])) {
                $data['tip'] = ['key' => $key] + $tips[$key];
            }
            // Two-step sign-in is optional but encouraged (BUILD_SPEC 13.3): owners and administrators
            // without it see this on the dashboard, once its own tip is gone, until they hide it.
            if ($data['tip'] === null && $route === 'dashboard' && $school->can('admin')
                && !TwoFactorRepository::isOn($auth->user()) && !isset($done['tip_two_step'])) {
                $data['tip'] = ['key' => 'two_step', 'route' => 'account/two-step', 'action' => 'Set up two-step sign-in',
                    'text' => 'Protect your school with two-step sign-in: after your password, a code from your phone. '
                        . 'Someone who learns your password still cannot get in. It is optional and takes two minutes.'];
            }
            $data['tourAutostart'] = !isset($done['tour_done']) || $this->app->request->query('tour') === '1';
        }
        return $data;
    }

    /** Setup Guide progress for the sidebar ring and the "Next step" banner. */
    private function setupSummary(SchoolContext $school, string $route): array
    {
        $guide = new SetupGuide($this->app->db(), $school);
        $complete = $guide->isComplete();
        $step = SetupGuide::stepForRoute($route);
        return [
            'done' => $guide->doneCount(),
            'total' => count($guide->steps()),
            'complete' => $complete,
            'next' => $guide->next(),
            'banner' => !$complete && $step !== null && $school->can('officer'),
        ];
    }

    /** Menu groups and items the user may see. */
    private function menuFor(?SchoolContext $school, bool $platformAdmin): array
    {
        $groups = [];
        foreach (require APP_ROOT . '/src/menu.php' as $group) {
            $items = array_values(array_filter($group['items'], fn ($item) => $item[2] === 'platform'
                ? $platformAdmin
                : $school !== null && $school->isActive() && $school->can($item[2])));
            if ($items) {
                $groups[] = ['items' => $items] + $group;
            }
        }
        return $groups;
    }

    /** School > Group > Page (> detail), from the menu entry that matches the route. */
    private function crumbs(array $menu, string $route, string $title, ?SchoolContext $school): array
    {
        $crumbs = [[$school ? $school->name() : 'Home', 'dashboard']];
        foreach ($menu as $group) {
            foreach ($group['items'] as [$label, $itemRoute]) {
                if ($route === $itemRoute || str_starts_with($route, $itemRoute . '/')) {
                    $crumbs[] = [$group['label'], null];
                    $crumbs[] = [$label, $itemRoute];
                    if ($title !== '' && $title !== $label) {
                        $crumbs[] = [$title, null];
                    }
                    return $crumbs;
                }
            }
        }
        if ($title !== '') {
            $crumbs[] = [$title, null];
        }
        return $crumbs;
    }

    /**
     * Which views/help/{key}.php explains this route: the full route with
     * slashes as underscores if such a file exists, else its first part.
     */
    public static function helpKey(string $route): ?string
    {
        $aliases = ['timetables/view' => 'timetable_view', 'timetables/report' => 'timetable_report',
            'timetables/compare' => 'timetable_compare', 'timetables/manual' => 'timetable_manual',
            'generate/job' => 'generate_job', 'account/two-step/codes' => 'account_two_step', 'rooms/create' => 'rooms_form', 'rooms/edit' => 'rooms_form',
            'groups/create' => 'groups_form', 'groups/edit' => 'groups_form', 'lecturers/create' => 'lecturers_form',
            'lecturers/edit' => 'lecturers_form', 'courses/create' => 'courses_form', 'courses/edit' => 'courses_form'];
        $candidates = [$aliases[$route] ?? null, str_replace('/', '_', $route), explode('/', $route)[0]];
        foreach ($candidates as $key) {
            if ($key !== null && preg_match('/^[a-z0-9_]+$/', $key) && is_file(APP_ROOT . '/views/help/' . $key . '.php')) {
                return $key;
            }
        }
        return null;
    }
}
