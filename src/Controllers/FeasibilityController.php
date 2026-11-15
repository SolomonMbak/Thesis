<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\SemesterRepository;
use App\Services\FeasibilityService;

/**
 * Menu area 3: run the feasibility pre-check and show the latest result.
 */
final class FeasibilityController extends Controller
{
    public function index(): void
    {
        $semester = (new SemesterRepository($this->app->db()))->active();
        $this->render('feasibility/index', [
            'title' => 'Feasibility check',
            'semester' => $semester,
            'last' => $semester ? (new FeasibilityService($this->app->db()))->latest((int) $semester['id']) : null,
        ]);
    }

    public function run(): void
    {
        $semester = (new SemesterRepository($this->app->db()))->active();
        if (!$semester) {
            $this->app->flash->error('There is no active semester.');
            $this->redirect('feasibility');
        }
        $result = (new FeasibilityService($this->app->db()))->run((int) $semester['id'], $this->app->auth()->id());
        $this->app->log('feasibility.run', 'semester', (int) $semester['id'], ['ok' => $result['ok'], 'failures' => count($result['failures'])]);
        if ($result['ok']) {
            $this->app->flash->success('The feasibility check passed. You can now generate a timetable.');
        } else {
            $this->app->flash->error('The feasibility check found ' . count($result['failures']) . ' problem(s). Fix them and run it again.');
        }
        $this->redirect('feasibility');
    }
}
