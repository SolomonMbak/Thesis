<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\JobQueue;
use App\Data\JobRepository;
use App\Data\SettingsRepository;
use App\Data\TableRepository;
use App\Data\TimeslotRepository;
use App\Services\FeasibilityService;
use App\Services\SessionBuilder;
use App\Services\SetupGuide;

/**
 * Landing page after sign-in: the Setup Guide banner until setup is done,
 * counts, the last readiness check and run, and warnings that say what to do.
 */
final class DashboardController extends Controller
{
    private const WORKER_SILENT_MINUTES = 5;

    public function index(): void
    {
        $pdo = $this->app->db();
        $school = $this->school();
        $stale = (new JobQueue($pdo))->recoverStale();
        $semester = $this->activeSemester();
        $semesterId = $semester ? (int) $semester['id'] : 0;
        $jobs = new JobRepository($pdo, $school);
        $guide = new SetupGuide($pdo, $school);

        $counts = [
            'Rooms' => [(new TableRepository($pdo, $school, 'rooms'))->count('t.is_active = 1'), 'rooms'],
            'Levels' => [(new TableRepository($pdo, $school, 'student_groups'))->count(), 'groups'],
            'Lecturers' => [(new TableRepository($pdo, $school, 'lecturers'))->count('t.is_active = 1'), 'lecturers'],
            'Courses' => [$semesterId ? (new TableRepository($pdo, $school, 'courses'))->count('t.semester_id = ?', [$semesterId]) : 0, 'courses'],
            'Class sessions' => [$semesterId ? (new SessionBuilder($pdo, $school))->sessionCount($semesterId) : 0, 'sessions'],
            'Open periods' => [(new TimeslotRepository($pdo, $school))->openCount(), 'school/profile'],
        ];

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'counts' => $counts,
            'feasibility' => $semesterId ? (new FeasibilityService($pdo, $school))->latest($semesterId) : null,
            'lastJob' => $semesterId ? $jobs->latest($semesterId) : null,
            'published' => $semesterId ? $jobs->published($semesterId) : null,
            'publicLink' => absolute_url(public_url($school->slug())),
            'warnings' => $this->warnings($semester, $counts, $stale),
            'guide' => ['done' => $guide->doneCount(), 'total' => count($guide->steps()), 'complete' => $guide->isComplete(), 'next' => $guide->next()],
        ]);
    }

    /** Plain English warnings, each saying what to do next. */
    private function warnings(?array $semester, array $counts, int $stale): array
    {
        $warnings = [];
        if ($semester === null) {
            $warnings[] = 'No semester is active. An administrator should create one under Manage, Semesters.';
        }
        if ($counts['Open periods'][0] === 0) {
            $warnings[] = 'Your teaching week has no open periods. Open School profile and periods and unblock some.';
        }
        if ($stale > 0) {
            $warnings[] = "{$stale} run(s) stopped responding for over 10 minutes and were marked as failed. Start the run again; "
                . 'if it keeps happening, ask the platform administrator to check the Health page.';
        }
        $settings = new SettingsRepository($this->app->db());
        if ($settings->get('launch_mode') === 'queue') {
            $heartbeat = (string) $settings->get('worker_heartbeat', '');
            $age = $heartbeat !== '' ? time() - strtotime($heartbeat) : null;
            if ($age === null || $age > self::WORKER_SILENT_MINUTES * 60) {
                $warnings[] = 'Timetable runs are waiting for the background worker, which has not checked in for over '
                    . self::WORKER_SILENT_MINUTES . ' minutes, so new runs will not start yet. Ask the platform administrator to check the cron job (Health page).';
            }
        }
        return $warnings;
    }
}
