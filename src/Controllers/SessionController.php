<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SessionBuilder;

/**
 * "How your courses become classes": a read-only view of the class sessions
 * built from the courses. Sessions are rebuilt automatically when Check
 * readiness or Generate opens (BUILD_SPEC 10.2), so users never need this
 * page; it is here to explain what the system schedules.
 */
final class SessionController extends Controller
{
    public function index(): void
    {
        $semester = $this->activeSemester();
        $builder = new SessionBuilder($this->app->db(), $this->school());
        $preview = $semester ? $builder->preview((int) $semester['id']) : [];
        $this->render('sessions/index', [
            'title' => 'How courses become classes',
            'semester' => $semester,
            'preview' => $preview,
            'planned' => array_sum(array_map(fn ($c) => count($c['durations']), $preview)),
            'hours' => array_sum(array_map(fn ($c) => array_sum($c['durations']), $preview)),
            'state' => $semester ? $builder->currentState((int) $semester['id']) : null,
        ]);
    }

    /** Update now (normally automatic). */
    public function build(): void
    {
        $semester = $this->activeSemester();
        if (!$semester) {
            $this->app->flash->error('There is no active semester.');
            $this->redirect('sessions');
        }
        try {
            $result = (new SessionBuilder($this->app->db(), $this->school()))->build((int) $semester['id']);
        } catch (\RuntimeException $ex) {
            $this->app->flash->error($ex->getMessage());
            $this->redirect('sessions');
        }
        $this->app->log('sessions.build', 'semester', (int) $semester['id'], $result);
        $this->app->flash->success("Updated {$result['created']} class sessions from your courses.");
        $this->redirect('sessions');
    }
}
