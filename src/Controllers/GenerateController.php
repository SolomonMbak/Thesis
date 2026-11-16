<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Data\JobQueue;
use App\Data\JobRepository;
use App\Data\SchoolSettingsRepository;
use App\Data\SettingsRepository;
use App\Services\GaDefaults;
use App\Services\JobLauncher;
use App\Services\SessionBuilder;

/**
 * Generate timetable: start a run, watch it live and stop it. The search
 * itself always runs in a background process (BUILD_SPEC section 9).
 */
final class GenerateController extends Controller
{
    public function index(array $errors = [], array $old = []): void
    {
        $pdo = $this->app->db();
        $school = $this->school();
        $semester = $this->activeSemester();
        (new JobQueue($pdo))->recoverStale();
        JobLauncher::kick($pdo);
        $jobs = new JobRepository($pdo, $school);
        if ($semester && !$errors) {
            $rebuilt = (new SessionBuilder($pdo, $school))->ensureCurrent((int) $semester['id']);
            if ($rebuilt) {
                $this->app->flash->info("Updated {$rebuilt['created']} class sessions from your courses.");
            }
        }
        $this->render('generate/index', [
            'title' => 'Generate timetable',
            'semester' => $semester,
            'active' => $jobs->active(),
            'recent' => $semester ? $jobs->list(['semester_id' => $semester['id'], 'job_type' => 'ga'], 5) : [],
            'values' => $old ?: GaDefaults::load(new SchoolSettingsRepository($pdo, $school)) + ['random_seed' => '', 'label' => ''],
            'errors' => $errors,
            'launchMode' => (new SettingsRepository($pdo))->get('launch_mode', 'queue'),
        ]);
    }

    public function start(): void
    {
        $semester = $this->activeSemester();
        if (!$semester) {
            $this->app->flash->error('There is no active semester. An administrator must create one under Manage, Semesters.');
            $this->redirect('generate');
        }
        $input = array_map(fn ($v) => is_string($v) ? trim($v) : '', $this->app->request->allInput());
        $result = (new JobLauncher($this->app->db(), $this->school()))->start((int) $semester['id'], $input, $this->app->auth()->id());

        if (!$result['ok']) {
            if (!empty($result['errors'])) {
                http_response_code(422);
                $this->index($result['errors'], $input);
                return;
            }
            $this->app->flash->error($result['message']);
            $this->redirect(isset($result['job_id']) ? 'generate/job' : 'feasibility', isset($result['job_id']) ? ['id' => $result['job_id']] : []);
        }

        $this->app->log('job.start', 'job', $result['job_id'], ['mode' => $result['mode'], 'repair' => $input['repair_enabled'] ?? '0']);
        if (!empty($result['rebuilt'])) {
            $this->app->flash->info("Updated {$result['rebuilt']['created']} class sessions from your courses.");
        }
        if (!empty($result['message'])) {
            $this->app->flash->info($result['message']);
        }
        $this->app->flash->success('Run started. You can close this page: the run continues on the server.');
        $this->redirect('generate/job', ['id' => $result['job_id']]);
    }

    public function job(): void
    {
        $job = $this->findJob((int) $this->app->request->query('id', 0));
        $pdo = $this->app->db();
        if ($job['status'] === 'queued') {
            JobLauncher::kick($pdo);
        }
        $queue = new JobQueue($pdo);
        $this->render('generate/job', [
            'title' => 'Run #' . $job['id'],
            'job' => $job,
            'launchMode' => (new SettingsRepository($pdo))->get('launch_mode', 'queue'),
            'queuePosition' => $job['status'] === 'queued' ? $queue->position((int) $job['id']) : null,
            'canStop' => $this->school()->can('officer'),
        ]);
    }

    public function stop(): void
    {
        $job = $this->findJob((int) $this->app->request->input('id', 0));
        if (!in_array($job['status'], JobRepository::ACTIVE_STATUSES, true)) {
            $this->app->flash->info('This run has already ended.');
        } else {
            (new JobRepository($this->app->db(), $this->school()))->requestStop((int) $job['id']);
            $this->app->log('job.stop', 'job', (int) $job['id']);
            $this->app->flash->success('Stop requested. The best timetable found so far will be saved within a few seconds.');
        }
        $this->redirect('generate/job', ['id' => $job['id']]);
    }

    private function findJob(int $id): array
    {
        $job = (new JobRepository($this->app->db(), $this->school()))->find($id);
        if (!$job) {
            throw new HttpException(404, 'That run does not exist. It may have been deleted.');
        }
        return $job;
    }
}
