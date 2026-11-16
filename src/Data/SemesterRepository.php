<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Semesters of one school. Exactly one is active per school.
 */
final class SemesterRepository
{
    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    public function active(): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM semesters WHERE school_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1');
        $stmt->execute([$this->school->id]);
        return $stmt->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM semesters WHERE id = ? AND school_id = ?');
        $stmt->execute([$id, $this->school->id]);
        return $stmt->fetch() ?: null;
    }

    /** Newest first, with the number of courses in each. */
    public function all(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, (SELECT COUNT(*) FROM courses c WHERE c.semester_id = s.id AND c.school_id = s.school_id) AS course_count
             FROM semesters s WHERE s.school_id = ? ORDER BY s.session_label DESC, s.semester_no DESC'
        );
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll();
    }

    public function exists(string $sessionLabel, int $semesterNo): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM semesters WHERE school_id = ? AND session_label = ? AND semester_no = ?');
        $stmt->execute([$this->school->id, $sessionLabel, $semesterNo]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Create a semester; the school's first one becomes active. Returns its id. */
    public function create(string $sessionLabel, int $semesterNo): int
    {
        $active = $this->active() === null ? 1 : 0;
        $this->pdo->prepare('INSERT INTO semesters (school_id, session_label, semester_no, is_active) VALUES (?, ?, ?, ?)')
            ->execute([$this->school->id, $sessionLabel, $semesterNo, $active]);
        return (int) $this->pdo->lastInsertId();
    }

    public function activate(int $id): bool
    {
        if ($this->find($id) === null) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE semesters SET is_active = 0 WHERE school_id = ?')->execute([$this->school->id]);
            $this->pdo->prepare('UPDATE semesters SET is_active = 1 WHERE id = ? AND school_id = ?')->execute([$id, $this->school->id]);
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        return true;
    }

    /**
     * Copy courses from another semester of the same school, skipping codes
     * the target already has. Returns the number copied.
     */
    public function copyCourses(int $fromId, int $toId): int
    {
        if ($this->find($fromId) === null || $this->find($toId) === null) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO courses (school_id, semester_id, code, title, credit_units, hours_per_week, needs_lab,
                                  allow_two_hour_block, class_size_override, group_id, lecturer_id)
             SELECT c.school_id, ?, c.code, c.title, c.credit_units, c.hours_per_week, c.needs_lab,
                    c.allow_two_hour_block, c.class_size_override, c.group_id, c.lecturer_id
             FROM courses c
             WHERE c.school_id = ? AND c.semester_id = ?
               AND NOT EXISTS (SELECT 1 FROM courses x WHERE x.school_id = c.school_id AND x.semester_id = ? AND x.code = c.code)'
        );
        $stmt->execute([$toId, $this->school->id, $fromId, $toId]);
        return $stmt->rowCount();
    }

    public static function label(array $semester): string
    {
        $names = [1 => 'First', 2 => 'Second', 3 => 'Third'];
        $no = (int) $semester['semester_no'];
        return $semester['session_label'] . ' ' . ($names[$no] ?? (string) $no) . ' Semester';
    }

    /** The academic session a new school starts in, e.g. "2026/2027" from September. */
    public static function currentSessionLabel(?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        $year = (int) date('Y', $t);
        $start = (int) date('n', $t) >= 9 ? $year : $year - 1;
        return $start . '/' . ($start + 1);
    }
}
