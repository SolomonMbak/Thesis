<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\Validator;
use App\Data\SemesterRepository;

/**
 * Administration: semesters. One semester is active at a time; courses
 * belong to a semester and can be copied forward from an earlier one.
 */
final class SemesterController extends Controller
{
    public function index(array $errors = [], array $old = []): void
    {
        $semesters = $this->app->db()->query(
            'SELECT s.*, (SELECT COUNT(*) FROM courses c WHERE c.semester_id = s.id) AS course_count
             FROM semesters s ORDER BY s.session_label DESC, s.semester_no DESC'
        )->fetchAll();
        $this->render('semesters/index', [
            'title' => 'Semesters',
            'semesters' => $semesters,
            'errors' => $errors,
            'old' => $old + ['session_label' => '', 'semester_no' => '1'],
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
            $errors['session_label'] = 'Session must look like 2025/2026.';
        }
        if (!$errors) {
            $stmt = $this->app->db()->prepare('SELECT COUNT(*) FROM semesters WHERE session_label = ? AND semester_no = ?');
            $stmt->execute([$in['session_label'], $in['semester_no']]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors['session_label'] = 'That semester already exists.';
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->index($errors, $in);
            return;
        }
        $pdo = $this->app->db();
        $hasActive = (new SemesterRepository($pdo))->active() !== null;
        $pdo->prepare('INSERT INTO semesters (session_label, semester_no, is_active) VALUES (?, ?, ?)')
            ->execute([$in['session_label'], (int) $in['semester_no'], $hasActive ? 0 : 1]);
        $id = (int) $pdo->lastInsertId();
        $this->app->log('semester.create', 'semester', $id, $in);
        $this->app->flash->success($hasActive
            ? 'Semester created. Set it as active when you are ready to work on it.'
            : 'Semester created and set as active.');
        $this->redirect('semesters');
    }

    public function activate(): void
    {
        $id = (int) $this->app->request->input('id', 0);
        $pdo = $this->app->db();
        $stmt = $pdo->prepare('SELECT * FROM semesters WHERE id = ?');
        $stmt->execute([$id]);
        $semester = $stmt->fetch();
        if (!$semester) {
            $this->app->flash->error('That semester no longer exists.');
            $this->redirect('semesters');
        }
        $pdo->beginTransaction();
        $pdo->exec('UPDATE semesters SET is_active = 0');
        $pdo->prepare('UPDATE semesters SET is_active = 1 WHERE id = ?')->execute([$id]);
        $pdo->commit();
        $this->app->log('semester.activate', 'semester', $id);
        $this->app->flash->success(SemesterRepository::label($semester) . ' is now the active semester.');

        $back = (string) $this->app->request->input('return', 'semesters');
        $this->redirect(preg_match('#^[a-z0-9_/-]{1,60}$#', $back) ? $back : 'semesters');
    }

    /** Copy courses from another semester into this one, skipping codes it already has. */
    public function copy(): void
    {
        $to = (int) $this->app->request->input('id', 0);
        $from = (int) $this->app->request->input('from_id', 0);
        if ($to === $from || $to <= 0 || $from <= 0) {
            $this->app->flash->error('Choose a different semester to copy from.');
            $this->redirect('semesters');
        }
        $stmt = $this->app->db()->prepare(
            'INSERT INTO courses (semester_id, code, title, credit_units, hours_per_week, needs_lab,
                                  allow_two_hour_block, class_size_override, group_id, lecturer_id)
             SELECT ?, c.code, c.title, c.credit_units, c.hours_per_week, c.needs_lab,
                    c.allow_two_hour_block, c.class_size_override, c.group_id, c.lecturer_id
             FROM courses c
             WHERE c.semester_id = ?
               AND NOT EXISTS (SELECT 1 FROM courses x WHERE x.semester_id = ? AND x.code = c.code)'
        );
        $stmt->execute([$to, $from, $to]);
        $count = $stmt->rowCount();
        $this->app->log('semester.copy_courses', 'semester', $to, ['from' => $from, 'copied' => $count]);
        $this->app->flash->success("{$count} course(s) copied. Courses that already existed were left unchanged. "
            . 'Build class sessions again for this semester.');
        $this->redirect('semesters');
    }
}
