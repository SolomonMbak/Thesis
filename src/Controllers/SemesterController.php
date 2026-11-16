<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Validator;
use App\Data\CalendarRepository;
use App\Data\SemesterRepository;

/**
 * Semesters of the school. One is active at a time; courses belong to a
 * semester and can be copied forward from an earlier one.
 */
final class SemesterController extends Controller
{
    public function index(array $errors = [], array $old = []): void
    {
        $this->render('semesters/index', [
            'title' => 'Semesters',
            'semesters' => $this->semesters()->all(),
            'errors' => $errors,
            'old' => $old + ['session_label' => SemesterRepository::currentSessionLabel(), 'semester_no' => '1'],
        ]);
    }

    public function store(): void
    {
        $in = [
            'session_label' => trim((string) $this->app->request->input('session_label', '')),
            'semester_no' => trim((string) $this->app->request->input('semester_no', '')),
        ];
        $errors = Validator::validate($in, [
            'session_label' => 'required|maxlen:20',
            'semester_no' => 'required|in:1,2,3',
        ], ['session_label' => 'Session', 'semester_no' => 'Semester']);
        if (!isset($errors['session_label']) && !preg_match('#^\d{4}/\d{4}$#', $in['session_label'])) {
            $errors['session_label'] = 'Write the session as two years with a slash, for example 2025/2026.';
        }
        if (!$errors && $this->semesters()->exists($in['session_label'], (int) $in['semester_no'])) {
            $errors['session_label'] = 'That semester already exists. Set it as active in the list below instead.';
        }
        if ($errors) {
            http_response_code(422);
            $this->index($errors, $in);
            return;
        }
        $hadActive = $this->semesters()->active() !== null;
        $id = $this->semesters()->create($in['session_label'], (int) $in['semester_no']);
        $this->app->log('semester.create', 'semester', $id, $in);
        $this->app->flash->success($hadActive
            ? 'Semester created. Set it as active when you are ready to work on it, and copy last semester\'s courses into it if they repeat.'
            : 'Semester created and set as active.');
        $this->redirect('semesters');
    }

    public function activate(): void
    {
        $id = (int) $this->app->request->input('id', 0);
        if (!$this->semesters()->activate($id)) {
            $this->app->flash->error('That semester does not exist.');
            $this->redirect('semesters');
        }
        $this->app->log('semester.activate', 'semester', $id);
        $this->app->flash->success(SemesterRepository::label($this->semesters()->find($id)) . ' is now the active semester.');
        $back = (string) $this->app->request->input('return', 'semesters');
        $this->redirect(preg_match('#^[a-z0-9_/-]{1,60}$#', $back) ? $back : 'semesters');
    }

    /** Copy courses from another semester of this school, skipping codes it already has. */
    public function copy(): void
    {
        $to = (int) $this->app->request->input('id', 0);
        $from = (int) $this->app->request->input('from_id', 0);
        if ($to === $from || $to <= 0 || $from <= 0) {
            $this->app->flash->error('Choose a different semester to copy from.');
            $this->redirect('semesters');
        }
        $count = $this->semesters()->copyCourses($from, $to);
        $this->app->log('semester.copy_courses', 'semester', $to, ['from' => $from, 'copied' => $count]);
        $this->app->flash->success("{$count} course(s) copied. Courses that already existed were left unchanged.");
        $this->redirect('semesters');
    }

    /** Dates and holidays of one semester, for the monthly calendar view (BUILD_SPEC 10.7). */
    public function calendar(array $errors = [], array $old = []): void
    {
        $semester = $this->findSemester((int) ($this->app->request->query('id') ?? $this->app->request->input('id', 0)));
        $this->render('semesters/calendar', [
            'title' => 'Dates and holidays',
            'semester' => $semester,
            'closures' => (new CalendarRepository($this->app->db(), $this->school()))->closures((int) $semester['id']),
            'errors' => $errors,
            'old' => $old + ['start_date' => (string) $semester['start_date'], 'end_date' => (string) $semester['end_date'],
                'closed_on' => '', 'closed_to' => '', 'reason' => ''],
        ]);
    }

    public function saveDates(): void
    {
        $semester = $this->findSemester((int) $this->app->request->input('id', 0));
        $in = ['start_date' => trim((string) $this->app->request->input('start_date', '')),
            'end_date' => trim((string) $this->app->request->input('end_date', ''))];
        $errors = [];
        foreach ($in as $key => $value) {
            if ($value !== '' && !self::isDate($value)) {
                $errors[$key] = 'Enter a date such as 2026-01-12.';
            }
        }
        if (!$errors && ($in['start_date'] === '') !== ($in['end_date'] === '')) {
            $errors['end_date'] = 'Give both the first and the last day of teaching, or leave both empty.';
        }
        if (!$errors && $in['start_date'] !== '' && $in['end_date'] < $in['start_date']) {
            $errors['end_date'] = 'The last day of teaching must be on or after the first day.';
        }
        if ($errors) {
            http_response_code(422);
            $this->calendar($errors, $in);
            return;
        }
        (new CalendarRepository($this->app->db(), $this->school()))
            ->setDates((int) $semester['id'], $in['start_date'] ?: null, $in['end_date'] ?: null);
        $this->app->log('semester.dates', 'semester', (int) $semester['id'], $in);
        $this->app->flash->success('Semester dates saved. The month view of every timetable now shows these dates.');
        $this->redirect('semesters/calendar', ['id' => $semester['id']]);
    }

    /** Add one day without teaching, or every weekday of a range (for example a mid-semester break). */
    public function addClosure(): void
    {
        $semester = $this->findSemester((int) $this->app->request->input('id', 0));
        $in = ['closed_on' => trim((string) $this->app->request->input('closed_on', '')),
            'closed_to' => trim((string) $this->app->request->input('closed_to', '')),
            'reason' => trim((string) $this->app->request->input('reason', ''))];
        $errors = Validator::validate($in, ['reason' => 'required|maxlen:100'], ['reason' => 'Reason']);
        if (!self::isDate($in['closed_on'])) {
            $errors['closed_on'] = 'Enter a date such as 2026-03-20.';
        }
        if ($in['closed_to'] !== '' && (!self::isDate($in['closed_to']) || $in['closed_to'] < $in['closed_on'])) {
            $errors['closed_to'] = 'Leave empty for one day, or enter a later date to close a whole period.';
        }
        if (!$errors && $in['closed_to'] !== '' && (strtotime($in['closed_to']) - strtotime($in['closed_on'])) > 120 * 86400) {
            $errors['closed_to'] = 'A closed period can be at most 120 days. Change the semester dates for longer breaks.';
        }
        if ($errors) {
            http_response_code(422);
            $this->calendar($errors, $in);
            return;
        }
        $calendar = new CalendarRepository($this->app->db(), $this->school());
        $day = new \DateTimeImmutable($in['closed_on']);
        $to = new \DateTimeImmutable($in['closed_to'] !== '' ? $in['closed_to'] : $in['closed_on']);
        $count = 0;
        for (; $day <= $to; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') <= 5) {
                $calendar->addClosure((int) $semester['id'], $day->format('Y-m-d'), $in['reason']);
                $count++;
            }
        }
        $this->app->log('semester.closure_add', 'semester', (int) $semester['id'], $in);
        $this->app->flash->success($count === 1 ? 'Day without teaching added.' : "{$count} weekdays without teaching added.");
        $this->redirect('semesters/calendar', ['id' => $semester['id']]);
    }

    public function removeClosure(): void
    {
        $semester = $this->findSemester((int) $this->app->request->input('id', 0));
        (new CalendarRepository($this->app->db(), $this->school()))->removeClosure((int) $this->app->request->input('closure_id', 0));
        $this->app->log('semester.closure_remove', 'semester', (int) $semester['id']);
        $this->app->flash->success('Day removed. Classes take place on it again.');
        $this->redirect('semesters/calendar', ['id' => $semester['id']]);
    }

    private function findSemester(int $id): array
    {
        return $this->semesters()->find($id) ?? throw new \App\Core\HttpException(404, 'That semester does not exist.');
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function semesters(): SemesterRepository
    {
        return new SemesterRepository($this->app->db(), $this->school());
    }
}
