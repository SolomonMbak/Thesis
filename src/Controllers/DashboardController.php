<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\SemesterRepository;

/**
 * Landing page after sign-in: counts, latest results and warnings.
 * Later increments add the feasibility result, job status and worker checks.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $pdo = $this->app->db();
        $semester = (new SemesterRepository($pdo))->active();
        $semesterId = $semester ? (int) $semester['id'] : 0;

        $counts = [
            'Lecturers' => $this->count('SELECT COUNT(*) FROM lecturers WHERE is_active = 1'),
            'Student groups' => $this->count('SELECT COUNT(*) FROM student_groups'),
            'Rooms' => $this->count('SELECT COUNT(*) FROM rooms WHERE is_active = 1'),
            'Courses' => $this->count('SELECT COUNT(*) FROM courses WHERE semester_id = ?', [$semesterId]),
            'Class sessions' => $this->count(
                'SELECT COUNT(*) FROM class_sessions cs JOIN courses c ON c.id = cs.course_id
                 WHERE c.semester_id = ? AND cs.archived = 0',
                [$semesterId]
            ),
            'Open periods' => $this->count('SELECT COUNT(*) FROM timeslots WHERE is_blocked = 0'),
        ];

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'counts' => $counts,
            'warnings' => $this->warnings($semester, $counts),
        ]);
    }

    /** Plain English warnings, each saying what to do next. */
    private function warnings(?array $semester, array $counts): array
    {
        $warnings = [];
        if ($semester === null) {
            $warnings[] = 'No semester is active yet. An administrator should create one and set it as active.';
        }
        if ($counts['Open periods'] === 0) {
            $warnings[] = 'No teaching periods exist yet. Generate them from the period settings.';
        }
        if ($semester !== null && $counts['Courses'] > 0 && $counts['Class sessions'] === 0) {
            $warnings[] = 'Courses exist but class sessions have not been built. Build sessions before generating a timetable.';
        }
        return $warnings;
    }

    private function count(string $sql, array $params = []): int
    {
        $stmt = $this->app->db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
