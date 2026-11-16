<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\FeasibilityService;
use App\Services\SessionBuilder;

/**
 * Check readiness (the feasibility pre-check): finds problems that make a
 * clash-free timetable impossible, each with a link to fix it.
 *
 * Opening the page first brings the class sessions up to date with the
 * courses, and says so (BUILD_SPEC 10.2).
 */
final class FeasibilityController extends Controller
{
    public function index(): void
    {
        $semester = $this->activeSemester();
        $service = new FeasibilityService($this->app->db(), $this->school());
        if ($semester) {
            $this->rebuildSessionsIfNeeded((int) $semester['id']);
        }
        $this->render('feasibility/index', [
            'title' => 'Check readiness',
            'semester' => $semester,
            'last' => $semester ? $service->latest((int) $semester['id']) : null,
            'sessions' => $semester ? (new SessionBuilder($this->app->db(), $this->school()))->sessionCount((int) $semester['id']) : 0,
        ]);
    }

    public function run(): void
    {
        $semester = $this->activeSemester();
        if (!$semester) {
            $this->app->flash->error('There is no active semester. An administrator must create one under Manage, Semesters.');
            $this->redirect('feasibility');
        }
        $result = (new FeasibilityService($this->app->db(), $this->school()))->run((int) $semester['id'], $this->app->auth()->id());
        if ($result['rebuilt']) {
            $this->app->flash->info("Updated {$result['rebuilt']['created']} class sessions from your courses.");
        }
        $this->app->log('feasibility.run', 'semester', (int) $semester['id'], ['ok' => $result['ok'], 'failures' => count($result['failures'])]);
        if ($result['ok']) {
            $this->app->flash->success('Ready. Nothing in your data makes a clash-free timetable impossible. Next: generate your timetable.');
        } else {
            $this->app->flash->error('Check readiness found ' . count($result['failures']) . ' problem(s). Each one below says how to fix it.');
        }
        $this->redirect('feasibility');
    }

    private function rebuildSessionsIfNeeded(int $semesterId): void
    {
        try {
            $rebuilt = (new SessionBuilder($this->app->db(), $this->school()))->ensureCurrent($semesterId);
        } catch (\RuntimeException) {
            return;   // a run is active; sessions are updated after it ends
        }
        if ($rebuilt) {
            $this->app->log('sessions.build', 'semester', $semesterId, $rebuilt + ['automatic' => true]);
            $this->app->flash->info("Updated {$rebuilt['created']} class sessions from your courses.");
        }
    }
}
