<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\TimeslotRepository;
use App\Services\AvailabilityService;
use PDO;

/**
 * Lecturer availability grid. Clicking a cell cycles available, preferred,
 * unavailable. With JavaScript the click is saved through api/availability.php
 * without reloading; without it, the form posts here.
 */
final class AvailabilityController extends Controller
{
    public function index(): void
    {
        $pdo = $this->app->db();
        $lecturers = $pdo->query('SELECT id, title, full_name, staff_no FROM lecturers WHERE is_active = 1 ORDER BY full_name')->fetchAll();
        $selectedId = (int) $this->app->request->query('lecturer', $lecturers[0]['id'] ?? 0);
        $selected = null;
        foreach ($lecturers as $lecturer) {
            if ((int) $lecturer['id'] === $selectedId) {
                $selected = $lecturer;
            }
        }
        $grid = (new TimeslotRepository($pdo))->grid();
        $this->render('availability/index', [
            'title' => 'Lecturer availability',
            'lecturers' => $lecturers,
            'selected' => $selected,
            'statuses' => $selected ? (new AvailabilityService($pdo))->forLecturer((int) $selected['id']) : [],
            'grid' => $grid,
            'summary' => $this->summary(),
        ]);
    }

    public function toggle(): void
    {
        $lecturerId = (int) $this->app->request->input('lecturer', 0);
        $timeslotId = (int) $this->app->request->input('timeslot', 0);
        try {
            $status = (new AvailabilityService($this->app->db()))->cycle($lecturerId, $timeslotId);
        } catch (\InvalidArgumentException $ex) {
            $this->app->flash->error($ex->getMessage());
            $this->redirect('availability');
        }
        $this->app->log('availability.set', 'lecturer', $lecturerId, ['timeslot' => $timeslotId, 'status' => $status]);
        $this->redirect('availability', ['lecturer' => $lecturerId]);
    }

    public function bulk(): void
    {
        $req = $this->app->request;
        $scope = $req->input('scope') === 'period' ? 'period' : 'day';
        $value = (int) ($scope === 'day' ? $req->input('day', 0) : $req->input('period_no', 0));
        $status = (string) $req->input('status', '');
        if (!in_array($status, AvailabilityService::STATUSES, true) || $value <= 0) {
            $this->app->flash->error('Choose a day or period and a status.');
            $this->redirect('availability');
        }
        $count = (new AvailabilityService($this->app->db()))->bulk($scope, $value, $status);
        $this->app->log('availability.bulk', null, null, ['scope' => $scope, 'value' => $value, 'status' => $status]);
        $this->app->flash->success("{$count} cells set to {$status} for all active lecturers.");
        $this->redirect('availability', ['lecturer' => (int) $req->input('lecturer', 0)]);
    }

    /** Count of preferred and unavailable periods per lecturer. */
    private function summary(): array
    {
        return $this->app->db()->query(
            "SELECT lecturer_id, SUM(status = 'preferred') AS preferred, SUM(status = 'unavailable') AS unavailable
             FROM lecturer_availability GROUP BY lecturer_id"
        )->fetchAll(PDO::FETCH_UNIQUE);
    }
}
