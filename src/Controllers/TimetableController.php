<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Data\CalendarRepository;
use App\Data\JobRepository;
use App\Data\SemesterRepository;
use App\Data\TimeslotRepository;
use App\Services\CalendarService;
use App\Services\CsvImporter;
use App\Services\ManualTimetableImporter;
use App\Services\PeriodGenerator;
use App\Services\TimetableService;

/**
 * Runs and results: saved timetables, grids, quality report, comparison,
 * publishing and the share link, CSV export, print view and manual
 * timetable import. Every job is looked up in the current school only, so
 * another school's run answers "not found".
 */
final class TimetableController extends Controller
{
    private const READY = JobRepository::READY_STATUSES;

    public function index(): void
    {
        $semester = $this->activeSemester();
        $filters = [
            'semester_id' => $semester['id'] ?? 0,
            'job_type' => (string) $this->app->request->query('type', ''),
            'status' => (string) $this->app->request->query('status', ''),
            'label' => trim((string) $this->app->request->query('label', '')),
        ];
        $filters['job_type'] = in_array($filters['job_type'], ['ga', 'manual'], true) ? $filters['job_type'] : '';
        $filters['status'] = in_array($filters['status'], ['queued', 'running', 'finished', 'failed', 'stopped'], true) ? $filters['status'] : '';
        $this->render('timetables/index', [
            'title' => 'Runs and results',
            'semester' => $semester,
            'jobs' => $semester ? $this->jobs()->list($filters) : [],
            'filters' => $filters,
            'canPublish' => $this->school()->can('officer'),
        ]);
    }

    public function view(): void
    {
        $job = $this->findJob((int) $this->app->request->query('id', 0));
        $service = $this->timetables();
        $entries = $service->entries((int) $job['id']);
        [$view, $id, $options] = $this->selection($entries);
        $grid = $id ? $service->grid($entries, $view, $id) : null;
        $this->render('timetables/view', [
            'title' => 'Timetable #' . $job['id'],
            'job' => $job,
            'breakdown' => $job['breakdown_json'] ? json_decode((string) $job['breakdown_json'], true) : [],
            'options' => $options,
            'view' => $view,
            'selectedId' => $id,
            'layout' => $grid ? TimetableService::layout($grid) : [],
            'rows' => $grid['rows'] ?? [],
            'entryCount' => count($entries),
            'mode' => $this->app->request->query('mode') === 'month' ? 'month' : 'week',
            'month' => $grid ? $this->monthFor($job, $entries, $view, $id, $grid['rows']) : null,
            'verdict' => in_array($job['status'], self::READY, true) && $entries ? TimetableService::verdict($service->qualityReport($job)) : null,
            'canPublish' => $this->school()->can('officer'),
            'publicLink' => absolute_url(public_url($this->school()->slug())),
        ]);
    }

    public function report(): void
    {
        $job = $this->findJob((int) $this->app->request->query('id', 0));
        $this->requireResult($job);
        $report = $this->timetables()->qualityReport($job);
        $this->render('timetables/report', ['title' => 'Quality report #' . $job['id'], 'job' => $job,
            'verdict' => TimetableService::verdict($report)] + $report);
    }

    public function compare(): void
    {
        $ids = array_slice(array_unique(array_map('intval', (array) $this->app->request->query('ids', []))), 0, 6);
        $jobs = [];
        foreach ($ids as $id) {
            $job = $this->jobs()->find($id);
            if ($job && in_array($job['status'], self::READY, true)) {
                $job['breakdown'] = json_decode((string) $job['breakdown_json'], true) ?: [];
                $jobs[] = $job;
            }
        }
        if (count($jobs) < 2) {
            $this->app->flash->error('Tick at least two finished or stopped runs to compare.');
            $this->redirect('timetables');
        }
        $this->render('timetables/compare', ['title' => 'Compare runs', 'jobs' => $jobs]);
    }

    /** Published timetable and share link of the active semester. */
    public function published(): void
    {
        $semester = $this->activeSemester();
        $this->render('timetables/published', [
            'title' => 'Published timetable and share link',
            'semester' => $semester,
            'job' => $semester ? $this->jobs()->published((int) $semester['id']) : null,
            'publicLink' => absolute_url(public_url($this->school()->slug())),
            'canPublish' => $this->school()->can('officer'),
        ]);
    }

    public function publish(): void
    {
        $job = $this->findJob((int) $this->app->request->input('id', 0));
        $this->requireResult($job);
        $this->jobs()->publish($job);
        $this->app->log('job.publish', 'job', (int) $job['id']);
        $message = 'Timetable #' . $job['id'] . ' is now published. Share the public link below with students and staff.';
        if ((int) $job['hard_violations'] > 0) {
            $message .= ' Careful: it still has ' . $job['hard_violations'] . ' clash(es).';
        }
        $this->app->flash->success($message);
        $this->redirect('published');
    }

    public function unpublish(): void
    {
        $job = $this->findJob((int) $this->app->request->input('id', 0));
        $this->jobs()->unpublish((int) $job['id']);
        $this->app->log('job.unpublish', 'job', (int) $job['id']);
        $this->app->flash->success('Timetable unpublished. The public page now shows no timetable for this semester.');
        $this->redirect('timetables/view', ['id' => $job['id']]);
    }

    /** CSV with one row per class session. */
    public function export(): void
    {
        $job = $this->findJob((int) $this->app->request->query('id', 0));
        $this->requireResult($job);
        $entries = $this->timetables()->entries((int) $job['id']);
        $periods = (new TimeslotRepository($this->app->db(), $this->school()))->grid()['rows'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="timetable_' . (int) $job['id'] . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel opens it as UTF-8
        fputcsv($out, ['day', 'start_time', 'end_time', 'course_code', 'course_title', 'session_no', 'hours',
            'level', 'lecturer', 'room'], ',', '"', '');
        foreach ($entries as $e) {
            $lastNo = (int) $e['period_no'] + (int) $e['duration'] - 1;
            fputcsv($out, [
                PeriodGenerator::SHORT_DAYS[(int) $e['day']], $e['start_time'], $periods[$lastNo]['end'] ?? '',
                csv_text($e['course_code']), csv_text($e['course_title']), $e['session_no'], $e['duration'],
                csv_text($e['group_code']), csv_text(trim($e['lecturer_name'])), csv_text($e['room_code']),
            ], ',', '"', '');
        }
        fclose($out);
        $this->app->log('job.export', 'job', (int) $job['id']);
    }

    /** Print view: every level (or lecturer, or room), one per page, A4 landscape, always light. */
    public function printView(): void
    {
        $job = $this->findJob((int) $this->app->request->query('id', 0));
        $this->requireResult($job);
        $service = $this->timetables();
        $entries = $service->entries((int) $job['id']);
        $view = (string) $this->app->request->query('view', 'level');
        $view = isset(TimetableService::VIEWS[$view]) ? $view : 'level';
        $pages = [];
        foreach (TimetableService::options($entries)[$view] as $id => $name) {
            $grid = $service->grid($entries, $view, (int) $id);
            $pages[] = ['name' => $name, 'rows' => $grid['rows'], 'layout' => TimetableService::layout($grid)];
        }
        $this->renderPlain('timetables/print', [
            'title' => 'Print timetable #' . $job['id'],
            'job' => $job,
            'view' => $view,
            'pages' => $pages,
            'heading' => [$this->school()->name(), (string) ($this->school()->school['department_label'] ?? '')],
        ]);
    }

    public function manualForm(array $errors = []): void
    {
        $this->render('timetables/manual', [
            'title' => 'Import a manual timetable',
            'semester' => $this->activeSemester(),
            'errors' => $errors,
            'columns' => ManualTimetableImporter::COLUMNS,
        ]);
    }

    public function manualUpload(): void
    {
        $semester = $this->activeSemester();
        if (!$semester) {
            $this->app->flash->error('There is no active semester. An administrator must create one under Manage, Semesters.');
            $this->redirect('timetables/manual');
        }
        $file = $this->app->request->file('file');
        $problem = CsvImporter::checkUpload($file);
        if ($problem !== null) {
            http_response_code(422);
            $this->manualForm([$problem]);
            return;
        }
        $label = mb_substr(trim((string) $this->app->request->input('label', '')), 0, 100);
        $result = (new ManualTimetableImporter($this->app->db(), $this->school()))
            ->import($file['tmp_name'], (int) $semester['id'], $label, $this->app->auth()->id());
        if (!$result['ok']) {
            http_response_code(422);
            $this->manualForm($result['errors']);
            return;
        }
        $this->app->log('job.import_manual', 'job', $result['job_id'], ['hard' => $result['hard'], 'penalty' => $result['penalty']]);
        $this->app->flash->success("Manual timetable imported and scored with the same rules: {$result['hard']} clash(es), penalty {$result['penalty']}.");
        $this->redirect('timetables/view', ['id' => $result['job_id']]);
    }

    public function manualTemplate(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="manual_timetable.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ManualTimetableImporter::COLUMNS, ',', '"', '');
        fputcsv($out, ['CSC 101', '1', 'Mon', '08:00', 'LT1'], ',', '"', '');
        fclose($out);
    }

    /** [view, selected id, options] from the query string, defaulting to the first level. */
    private function selection(array $entries): array
    {
        $options = TimetableService::options($entries);
        $view = (string) $this->app->request->query('view', 'level');
        $view = isset(TimetableService::VIEWS[$view]) ? $view : 'level';
        $id = (int) $this->app->request->query('vid', 0);
        if (!isset($options[$view][$id])) {
            $id = (int) (array_key_first($options[$view]) ?? 0);
        }
        return [$view, $id, $options];
    }

    /**
     * Export with dates: every class on its real date across the semester,
     * holidays left out (BUILD_SPEC 10.7).
     */
    public function exportDates(): void
    {
        $job = $this->findJob((int) $this->app->request->query('id', 0));
        $this->requireResult($job);
        $semester = (new SemesterRepository($this->app->db(), $this->school()))->find((int) $job['semester_id']);
        if (empty($semester['start_date']) || empty($semester['end_date'])) {
            $this->app->flash->error('Set the first and last day of teaching first (Manage, Semesters, Dates and holidays).');
            $this->redirect('timetables/view', ['id' => $job['id']]);
        }
        $periods = (new TimeslotRepository($this->app->db(), $this->school()))->grid()['rows'];
        $closures = (new CalendarRepository($this->app->db(), $this->school()))->closureMap((int) $semester['id']);
        $rows = CalendarService::occurrences($this->timetables()->entries((int) $job['id']), $periods, $semester, $closures);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="timetable_' . (int) $job['id'] . '_dates.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['date', 'day', 'start_time', 'end_time', 'course_code', 'course_title', 'level', 'lecturer', 'room'], ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($out, [$r['date'], PeriodGenerator::SHORT_DAYS[(int) $r['day']], $r['start_time'], $r['end_time'],
                csv_text($r['course_code']), csv_text($r['course_title']), csv_text($r['group_code']),
                csv_text(trim($r['lecturer_name'])), csv_text($r['room_code'])], ',', '"', '');
        }
        fclose($out);
        $this->app->log('job.export_dates', 'job', (int) $job['id']);
    }

    /** The month view of one level, lecturer or room: the repeating week laid onto real dates. */
    private function monthFor(array $job, array $entries, string $view, int $id, array $periods): array
    {
        $semester = (new SemesterRepository($this->app->db(), $this->school()))->find((int) $job['semester_id']);
        [$year, $month] = CalendarService::pickMonth($this->app->request->query('month'), $semester);
        $key = ['level' => 'group_id', 'lecturer' => 'lecturer_id', 'room' => 'room_id'][$view];
        $mine = array_values(array_filter($entries, fn ($e) => (int) $e[$key] === $id));
        $closures = (new CalendarRepository($this->app->db(), $this->school()))->closureMap((int) $semester['id']);
        return CalendarService::month($year, $month, $mine, $periods, $semester, $closures);
    }

    private function jobs(): JobRepository
    {
        return new JobRepository($this->app->db(), $this->school());
    }

    private function timetables(): TimetableService
    {
        return new TimetableService($this->app->db(), $this->school());
    }

    private function findJob(int $id): array
    {
        $job = $this->jobs()->find($id);
        if (!$job) {
            throw new HttpException(404, 'That run does not exist. It may have been deleted.');
        }
        return $job;
    }

    private function requireResult(array $job): void
    {
        if (!in_array($job['status'], self::READY, true)) {
            throw new HttpException(404, 'This run has no saved timetable (status: ' . $job['status'] . ').');
        }
    }
}
