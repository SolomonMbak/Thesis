<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\TableRepository;
use App\Data\HelpProgressRepository;
use App\Data\TimeslotRepository;
use App\Services\AvailabilityService;
use App\Services\SetupGuide;

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
        $school = $this->school();
        $lecturers = (new TableRepository($pdo, $school, 'lecturers'))
            ->list('SELECT t.id, t.title, t.full_name, t.staff_no FROM lecturers t', ['t.is_active = 1'], [], 't.full_name');
        $selectedId = (int) $this->app->request->query('lecturer', $lecturers[0]['id'] ?? 0);
        $selected = null;
        foreach ($lecturers as $lecturer) {
            if ((int) $lecturer['id'] === $selectedId) {
                $selected = $lecturer;
            }
        }
        $service = new AvailabilityService($pdo, $school);
        $this->render('availability/index', [
            'title' => 'Lecturer availability',
            'lecturers' => $lecturers,
            'selected' => $selected,
            'statuses' => $selected ? $service->forLecturer((int) $selected['id']) : [],
            'grid' => (new TimeslotRepository($pdo, $school))->grid(),
            'summary' => $service->summary(),
            'skipped' => HelpProgressRepository::anyoneDid($pdo, $school, SetupGuide::SKIP_AVAILABILITY),
        ]);
    }

    public function toggle(): void
    {
        $lecturerId = (int) $this->app->request->input('lecturer', 0);
        $timeslotId = (int) $this->app->request->input('timeslot', 0);
        try {
            $status = (new AvailabilityService($this->app->db(), $this->school()))->cycle($lecturerId, $timeslotId);
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
            $this->app->flash->error('Choose a day or a period, and a status.');
            $this->redirect('availability');
        }
        $count = (new AvailabilityService($this->app->db(), $this->school()))->bulk($scope, $value, $status);
        $this->app->log('availability.bulk', null, null, ['scope' => $scope, 'value' => $value, 'status' => $status]);
        $this->app->flash->success("{$count} cells set to {$status} for all active lecturers.");
        $this->redirect('availability', ['lecturer' => (int) $req->input('lecturer', 0)]);
    }

    /** "Skip for now" on Setup Guide step 6. */
    public function skip(): void
    {
        (new HelpProgressRepository($this->app->db(), $this->school(), (int) $this->app->auth()->id()))->mark(SetupGuide::SKIP_AVAILABILITY);
        $this->app->flash->info('Availability skipped for now. Every lecturer counts as available at every open period. You can come back to it at any time.');
        $this->redirect('setup');
    }
}
