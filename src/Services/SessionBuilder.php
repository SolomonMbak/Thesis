<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Turns courses into class sessions, the units the GA schedules
 * (BUILD_SPEC section 5). One session = one gene.
 */
final class SessionBuilder
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Session durations for one course.
     *  - Practical: blocks of up to 3 hours (4 hours gives 3 + 1).
     *  - Lecture: 1-hour sessions, except one 2-hour block first when allowed and hours >= 2.
     * @return int[]
     */
    public static function split(int $hours, bool $needsLab, bool $allowTwoHourBlock): array
    {
        if ($hours <= 0) {
            return [];
        }
        $durations = [];
        if ($needsLab) {
            while ($hours > 0) {
                $block = min($hours, 3);
                $durations[] = $block;
                $hours -= $block;
            }
            return $durations;
        }
        if ($allowTwoHourBlock && $hours >= 2) {
            $durations[] = 2;
            $hours -= 2;
        }
        return array_merge($durations, array_fill(0, $hours, 1));
    }

    /** Courses of the semester with their planned sessions, for the preview page. */
    public function preview(int $semesterId): array
    {
        $courses = $this->courses($semesterId);
        foreach ($courses as &$course) {
            $course['durations'] = self::split(
                (int) $course['hours_per_week'],
                (bool) $course['needs_lab'],
                (bool) $course['allow_two_hour_block']
            );
        }
        return $courses;
    }

    /** Existing (not archived) sessions and whether saved timetables use them. */
    public function currentState(int $semesterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM class_sessions cs JOIN courses c ON c.id = cs.course_id
             WHERE c.semester_id = ? AND cs.archived = 0'
        );
        $stmt->execute([$semesterId]);
        $count = (int) $stmt->fetchColumn();
        return ['sessions' => $count, 'used_by_jobs' => $this->usedByJobs($semesterId)];
    }

    /**
     * Rebuild the semester's sessions. Sessions used by saved timetables are
     * archived (old results stay readable); otherwise they are deleted.
     * @return array{created:int, archived:int, deleted:int}
     */
    public function build(int $semesterId): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ga_jobs WHERE semester_id = ? AND status IN ('queued','running')");
        $stmt->execute([$semesterId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('A timetable run is queued or running for this semester. Wait for it to finish, or stop it, before rebuilding sessions.');
        }

        $result = ['created' => 0, 'archived' => 0, 'deleted' => 0];
        $this->pdo->beginTransaction();
        try {
            $courseIds = 'SELECT id FROM courses WHERE semester_id = ?';
            if ($this->usedByJobs($semesterId)) {
                $s = $this->pdo->prepare("UPDATE class_sessions SET archived = 1 WHERE archived = 0 AND course_id IN ({$courseIds})");
                $s->execute([$semesterId]);
                $result['archived'] = $s->rowCount();
            } else {
                // Only unused sessions can reach here, so nothing references them.
                $s = $this->pdo->prepare("DELETE FROM class_sessions WHERE course_id IN ({$courseIds})");
                $s->execute([$semesterId]);
                $result['deleted'] = $s->rowCount();
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO class_sessions (course_id, session_no, duration, gene_index) VALUES (?, ?, ?, ?)'
            );
            $gene = 0;
            foreach ($this->preview($semesterId) as $course) {
                foreach ($course['durations'] as $i => $duration) {
                    $insert->execute([$course['id'], $i + 1, $duration, $gene++]);
                }
            }
            $result['created'] = $gene;
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        return $result;
    }

    private function usedByJobs(int $semesterId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM timetable_entries te
             JOIN class_sessions cs ON cs.id = te.session_id
             JOIN courses c ON c.id = cs.course_id
             WHERE c.semester_id = ? AND cs.archived = 0'
        );
        $stmt->execute([$semesterId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Courses ordered by id, which fixes the gene order. */
    private function courses(int $semesterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, g.code AS group_code FROM courses c JOIN student_groups g ON g.id = c.group_id
             WHERE c.semester_id = ? ORDER BY c.id'
        );
        $stmt->execute([$semesterId]);
        return $stmt->fetchAll();
    }
}
