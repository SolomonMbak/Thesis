<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use App\Data\SchoolSettingsRepository;
use PDO;

/**
 * Turns courses into class sessions, the units the GA schedules
 * (BUILD_SPEC section 5). One session = one gene.
 *
 * Users never have to build sessions by hand (BUILD_SPEC 10.2): ensureCurrent()
 * rebuilds them when the courses changed since the last build. "Changed" is
 * detected with a fingerprint of the course fields that decide the sessions,
 * so no page has to remember to flag a change.
 */
final class SessionBuilder
{
    public function __construct(private PDO $pdo, private SchoolContext $school)
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
        return ['sessions' => $this->sessionCount($semesterId), 'used_by_jobs' => $this->usedByJobs($semesterId),
            'up_to_date' => !$this->needsRebuild($semesterId)];
    }

    public function sessionCount(int $semesterId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM class_sessions cs JOIN courses c ON c.id = cs.course_id
             WHERE cs.school_id = ? AND c.semester_id = ? AND cs.archived = 0'
        );
        $stmt->execute([$this->school->id, $semesterId]);
        return (int) $stmt->fetchColumn();
    }

    /** True when the sessions no longer match the courses (or were never built). */
    public function needsRebuild(int $semesterId): bool
    {
        $stored = (new SchoolSettingsRepository($this->pdo, $this->school))->get($this->fingerprintKey($semesterId), '');
        if ($stored !== $this->fingerprint($semesterId)) {
            return true;
        }
        return false;
    }

    /**
     * Rebuild the sessions if the courses changed. Returns the build result,
     * or null if nothing needed doing. Never rebuilds under a queued or
     * running job (it would change the sessions the run is using).
     */
    public function ensureCurrent(int $semesterId): ?array
    {
        if (!$this->needsRebuild($semesterId) || $this->hasActiveJob()) {
            return null;
        }
        return $this->build($semesterId);
    }

    /**
     * Rebuild the semester's sessions. Sessions used by saved timetables are
     * archived (old results stay readable); otherwise they are deleted.
     * @return array{created:int, archived:int, deleted:int}
     */
    public function build(int $semesterId): array
    {
        if ($this->hasActiveJob()) {
            throw new \RuntimeException('A timetable run is waiting or running. Wait for it to finish, or stop it, before the class sessions can be updated.');
        }

        $result = ['created' => 0, 'archived' => 0, 'deleted' => 0];
        $this->pdo->beginTransaction();
        try {
            $courseIds = 'SELECT id FROM courses WHERE school_id = ? AND semester_id = ?';
            if ($this->usedByJobs($semesterId)) {
                $s = $this->pdo->prepare("UPDATE class_sessions SET archived = 1 WHERE school_id = ? AND archived = 0 AND course_id IN ({$courseIds})");
                $s->execute([$this->school->id, $this->school->id, $semesterId]);
                $result['archived'] = $s->rowCount();
            } else {
                // Only unused sessions can reach here, so nothing references them.
                $s = $this->pdo->prepare("DELETE FROM class_sessions WHERE school_id = ? AND course_id IN ({$courseIds})");
                $s->execute([$this->school->id, $this->school->id, $semesterId]);
                $result['deleted'] = $s->rowCount();
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO class_sessions (school_id, course_id, session_no, duration, gene_index) VALUES (?, ?, ?, ?, ?)'
            );
            $gene = 0;
            foreach ($this->preview($semesterId) as $course) {
                foreach ($course['durations'] as $i => $duration) {
                    $insert->execute([$this->school->id, $course['id'], $i + 1, $duration, $gene++]);
                }
            }
            $result['created'] = $gene;
            (new SchoolSettingsRepository($this->pdo, $this->school))->set($this->fingerprintKey($semesterId), $this->fingerprint($semesterId));
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        return $result;
    }

    /**
     * Order-independent summary of the course fields that decide the sessions.
     * SUM of CRC32 avoids GROUP_CONCAT length limits; COUNT catches deletions.
     */
    public function fingerprint(int $semesterId): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS(',', id, hours_per_week, needs_lab, allow_two_hour_block))), 0)
             FROM courses WHERE school_id = ? AND semester_id = ?"
        );
        $stmt->execute([$this->school->id, $semesterId]);
        [$count, $sum] = $stmt->fetch(PDO::FETCH_NUM);
        return md5($count . ':' . $sum);
    }

    private function fingerprintKey(int $semesterId): string
    {
        return 'sessions_fingerprint_' . $semesterId;
    }

    private function hasActiveJob(): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ga_jobs WHERE school_id = ? AND status IN ('queued','running')");
        $stmt->execute([$this->school->id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function usedByJobs(int $semesterId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM timetable_entries te
             JOIN class_sessions cs ON cs.id = te.session_id
             JOIN courses c ON c.id = cs.course_id
             WHERE te.school_id = ? AND c.semester_id = ? AND cs.archived = 0'
        );
        $stmt->execute([$this->school->id, $semesterId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Courses ordered by id, which fixes the gene order. */
    private function courses(int $semesterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, g.code AS group_code FROM courses c JOIN student_groups g ON g.id = c.group_id
             WHERE c.school_id = ? AND c.semester_id = ? ORDER BY c.id'
        );
        $stmt->execute([$this->school->id, $semesterId]);
        return $stmt->fetchAll();
    }
}
