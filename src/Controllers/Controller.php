<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\HttpException;
use App\Core\Response;
use App\Data\SettingsRepository;
use App\Data\SemesterRepository;

/**
 * Base for all controllers: rendering with the shared layout, redirects
 * and role checks.
 */
abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    /** Render a page inside the main layout (top bar and menu). */
    protected function render(string $template, array $data = []): void
    {
        $settings = new SettingsRepository($this->app->db());
        echo $this->app->view->render($template, $data + [
            'title' => '',
            'user' => $this->app->auth()->user(),
            'isAdmin' => $this->app->auth()->isAdmin(),
            'csrf' => $this->app->csrf,
            'flashes' => $this->app->flash->take(),
            'institution' => $settings->get('institution_name', 'Your Institution'),
            'department' => $settings->get('department_name', 'Your Department'),
            'activeSemester' => (new SemesterRepository($this->app->db()))->active(),
            'currentRoute' => $this->app->request->route() ?: 'dashboard',
            'menu' => require APP_ROOT . '/src/menu.php',
            'allSemesters' => $this->app->auth()->isAdmin()
                ? $this->app->db()->query('SELECT * FROM semesters ORDER BY session_label DESC, semester_no DESC')->fetchAll()
                : [],
        ]);
    }

    /** Render a page in the plain layout (sign-in, public view, errors). */
    protected function renderPlain(string $template, array $data = []): void
    {
        echo $this->app->view->render($template, $data + [
            'title' => '',
            'csrf' => $this->app->csrf,
            'flashes' => $this->app->flash->take(),
        ], 'layout/plain');
    }

    protected function redirect(string $route, array $params = []): never
    {
        Response::redirect(url($route, $params));
    }

    /** Extra check inside an action when one route serves several roles. */
    protected function requireAdmin(): void
    {
        if (!$this->app->auth()->isAdmin()) {
            throw new HttpException(403, 'Only administrators can do this.');
        }
    }
}
