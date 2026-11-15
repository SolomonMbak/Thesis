<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\SemesterRepository;
use App\Services\SessionBuilder;

/**
 * Build class sessions from the active semester's courses: preview first,
 * then confirm.
 */
final class SessionController extends Controller
{
    public function index(): void
    {
        $semester = (new SemesterRepository($this->app->db()))->active();
        $builder = new SessionBuilder($this->app->db());
        $preview = $semester ? $builder->preview((int) $semester['id']) : [];
        $planned = array_sum(array_map(fn ($c) => count($c['durations']), $preview));
        $hours = array_sum(array_map(fn ($c) => array_sum($c['durations']), $preview));
        $this->render('sessions/index', [
            'title' => 'Build sessions',
            'semester' => $semester,
            'preview' => $preview,
            'planned' => $planned,
            'hours' => $hours,
            'state' => $semester ? $builder->currentState((int) $semester['id']) : null,
        ]);
    }

    public function build(): void
    {
        $semester = (new SemesterRepository($this->app->db()))->active();
        if (!$semester) {
            $this->app->flash->error('There is no active semester.');
            $this->redirect('sessions');
        }
        if ($this->app->request->input('confirm') !== '1') {
            $this->app->flash->error('Tick the confirmation box to rebuild the sessions.');
            $this->redirect('sessions');
        }
        try {
            $result = (new SessionBuilder($this->app->db()))->build((int) $semester['id']);
        } catch (\RuntimeException $ex) {
            $this->app->flash->error($ex->getMessage());
            $this->redirect('sessions');
        }
        $this->app->log('sessions.build', 'semester', (int) $semester['id'], $result);
        $message = "{$result['created']} class sessions built.";
        if ($result['archived']) {
            $message .= " {$result['archived']} old sessions were archived so saved timetables stay readable.";
        }
        $this->app->flash->success($message . ' Next, run the feasibility check.');
        $this->redirect('feasibility');
    }
}
