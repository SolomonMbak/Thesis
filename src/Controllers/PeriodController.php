<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\SettingsRepository;
use App\Data\TimeslotRepository;
use App\Services\PeriodGenerator;

/**
 * Teaching periods: generate the weekly grid from the settings and block
 * or unblock individual periods.
 */
final class PeriodController extends Controller
{
    public function index(): void
    {
        $settings = new SettingsRepository($this->app->db());
        $this->render('periods/index', [
            'title' => 'Periods',
            'grid' => (new TimeslotRepository($this->app->db()))->grid(),
            'dayStart' => $settings->get('day_start', '08:00'),
            'dayEnd' => $settings->get('day_end', '18:00'),
            'minutes' => $settings->get('period_minutes', '60'),
        ]);
    }

    public function generate(): void
    {
        $settings = new SettingsRepository($this->app->db());
        try {
            $result = (new PeriodGenerator($this->app->db()))->generate(
                (string) $settings->get('day_start', '08:00'),
                (string) $settings->get('day_end', '18:00'),
                (int) $settings->get('period_minutes', '60')
            );
        } catch (\RuntimeException | \InvalidArgumentException $ex) {
            $this->app->flash->error($ex->getMessage());
            $this->redirect('periods');
        }
        $this->app->log('periods.generate', null, null, $result);
        $this->app->flash->success("Periods generated: {$result['created']} created, {$result['updated']} updated, {$result['removed']} removed.");
        $this->redirect('periods');
    }

    public function toggle(): void
    {
        $id = (int) $this->app->request->input('id', 0);
        $stmt = $this->app->db()->prepare('UPDATE timeslots SET is_blocked = 1 - is_blocked WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            $this->app->flash->error('That period no longer exists.');
        } else {
            $this->app->log('periods.toggle_blocked', 'timeslot', $id);
            $this->app->flash->success('Period updated. Run the feasibility check again before generating.');
        }
        $this->redirect('periods');
    }
}
