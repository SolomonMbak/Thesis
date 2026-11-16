<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\TimeslotRepository;

/**
 * Blocking and unblocking single periods of the school's teaching week.
 * The grid itself is shown on School profile and periods; the week is
 * rebuilt there when the day times change.
 */
final class PeriodController extends Controller
{
    /** Old address of the period page. */
    public function index(): void
    {
        $this->redirect('school/profile');
    }

    public function toggle(): void
    {
        $id = (int) $this->app->request->input('id', 0);
        if (!(new TimeslotRepository($this->app->db(), $this->school()))->toggleBlocked($id)) {
            $this->app->flash->error('That period does not exist.');
        } else {
            $this->app->log('periods.toggle_blocked', 'timeslot', $id);
            $this->app->flash->success('Period updated. Check readiness again before generating.');
        }
        $this->redirect('school/profile', [], 'periods');
    }

    /** Redirect with an optional #anchor, so the page opens at the grid. */
    protected function redirect(string $route, array $params = [], string $anchor = ''): never
    {
        \App\Core\Response::redirect(url($route, $params) . ($anchor !== '' ? '#' . $anchor : ''));
    }
}
