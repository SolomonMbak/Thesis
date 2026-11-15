<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Data\JobRepository;
use App\Data\SemesterRepository;
use App\Data\SettingsRepository;
use App\Services\GaDefaults;
use App\Services\JobLauncher;

/**
 * Menu area 4: start a timetable run, watch it live and stop it.
 */
final class GenerateController extends Controller
{
    public function index(array $errors = [], array $old = []): void
    {
        $pdo = $this->app->db();
        $semester = (new SemesterRepository($pdo))->active();
        $jobs = new JobRepository($pdo);
        $jobs->recoverStale();
        $settings = new SettingsRepository($pdo);
        $this->render('generate/index', [
            'title' => 'Generate a timetable',
            'semester' => $semester,
            'active' => $semester ? $jobs->activeForSemester((int) $semester['id']) : null,
            'recent' => $semester ? $jobs->list(['semester_id' => $semester['id'], 'job_type' => 'ga'], 5) : [],
            'values' => $old ?: GaDefaults::load($settings) + ['random_seed' => '', 'label' => ''],
            'errors' => $errors,
            'launchMode' => $settings->get('launch_mode', 'queue'),
        ]);
    }

    public function start(): void
    {
        $semester = (new SemesterRepository($this->app->db()))->active();
        if (!$semester) {
            $this->app->flash->error('There is no active semester.');
            $this->redirect('generate');
        }
        $input = array_map(fn ($v) => is_string($v) ? trim($v) : '', $this->app->request->allInput());
        $result = (new JobLauncher($this->app->db()))->start((int) $semester['id'], $input, $this->app->auth()->id());

        if (!$result['ok']) {
            if (!empty($result['errors'])) {
                http_response_code(422);
                $this->index($result['errors'], $input);
                return;
            }
            $this->app->flash->error($result['message']);
            $this->redirect(isset($result['job_id']) ? 'generate/job' : 'generate', isset($result['job_id']) ? ['id' => $result['job_id']] : []);
        }

        $this->app->log('job.start', 'job', $result['job_id'], ['mode' => $result['mode'], 'repair' => $input['repair_enabled'] ?? '0']);
        if (!empty($result['message'])) {
            $this->app->flash->info($result['message']);
        }
        $this->app->flash->success('Run started. You can close this page; the run continues on the server.');
        $this->redirect('generate/job', ['id' => $result['job_id']]);
    }

    public function job(): void
    {
        $job = $this->findJob((int) $this->app->request->query('id', 0));
        $settings = new SettingsRepository($this->app->db());
        $this->render('generate/job', [
            'title' => 'Run #' . $job['id'],
            'job' => $job,
            'launchMode' => $settings->get('launch_mode', 'queue'),
        ]);
    }

    public function stop(): void
    {
        $job = $this->findJob((int) $this->app->request->input('id', 0));
        if (!in_array($job['status'], JobRepository::ACTIVE_STATUSES, true)) {
            $this->app->flash->info('This run has already ended.');
        } else {
            (new JobRepository($this->app->db()))->requestStop((int) $job['id']);
            $this->app->log('job.stop', 'job', (int) $job['id']);
            $this->app->flash->success('Stop requested. The best timetable so far will be saved within a few seconds.');
        }
        $this->redirect('generate/job', ['id' => $job['id']]);
    }

    private function findJob(int $id): array
    {
        $job = (new JobRepository($this->app->db()))->find($id);
        if (!$job) {
            throw new HttpException(404, 'That run does not exist. It may have been deleted.');
        }
        return $job;
    }
}
