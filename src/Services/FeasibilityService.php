<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use PDO;

/**
 * Runs the feasibility checker on a semester and stores the result.
 * Used by Check readiness, the Setup Guide and the job launcher.
 *
 * Each stored check carries a fingerprint of the data it looked at, so the
 * Setup Guide can tell whether anything changed since the last pass.
 */
final class FeasibilityService
{
    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** @return array{ok:bool, failures:array, rebuilt:?array} */
    public function run(int $semesterId, ?int $userId): array
    {
        // Sessions follow the courses automatically (BUILD_SPEC 10.2).
        $rebuilt = (new SessionBuilder($this->pdo, $this->school))->ensureCurrent($semesterId);
        $ctx = (new ProblemLoader($this->pdo, $this->school))->load($semesterId);
        $failures = (new FeasibilityChecker())->check($ctx);
        $ok = $failures === [];
        $this->pdo->prepare(
            'INSERT INTO feasibility_checks (school_id, semester_id, ok, failures_json, data_fingerprint, checked_by) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$this->school->id, $semesterId, $ok ? 1 : 0, json_encode($failures, JSON_UNESCAPED_UNICODE),
            $this->fingerprint($semesterId), $userId]);
        return ['ok' => $ok, 'failures' => $failures, 'rebuilt' => $rebuilt];
    }

    public function latest(int $semesterId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM feasibility_checks WHERE school_id = ? AND semester_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$this->school->id, $semesterId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['failures'] = json_decode((string) $row['failures_json'], true) ?: [];
        $row['current'] = $row['data_fingerprint'] !== null && $row['data_fingerprint'] === $this->fingerprint($semesterId);
        return $row;
    }

    /** Latest check passed and nothing it depends on has changed since (Setup Guide step 7). */
    public function passedAndCurrent(int $semesterId): bool
    {
        $latest = $this->latest($semesterId);
        return $latest !== null && (int) $latest['ok'] === 1 && $latest['current'];
    }

    /**
     * Order-independent summary of every table the check reads. Changing a
     * room, period, lecturer, availability cell, level or course changes it.
     */
    public function fingerprint(int $semesterId): string
    {
        $parts = [];
        $queries = [
            "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS(',', id, capacity, room_type, is_active))), 0) FROM rooms WHERE school_id = ?",
            "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS(',', id, start_time, end_time, is_blocked))), 0) FROM timeslots WHERE school_id = ?",
            "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS(',', id, is_active))), 0) FROM lecturers WHERE school_id = ?",
            "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS(',', lecturer_id, timeslot_id, status))), 0) FROM lecturer_availability WHERE school_id = ?",
            "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS(',', id, size))), 0) FROM student_groups WHERE school_id = ?",
        ];
        foreach ($queries as $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->school->id]);
            $parts[] = implode(':', $stmt->fetch(PDO::FETCH_NUM));
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS(',', id, hours_per_week, needs_lab, allow_two_hour_block,
                    COALESCE(class_size_override, 0), group_id, lecturer_id))), 0)
             FROM courses WHERE school_id = ? AND semester_id = ?"
        );
        $stmt->execute([$this->school->id, $semesterId]);
        $parts[] = implode(':', $stmt->fetch(PDO::FETCH_NUM));
        return md5(implode('|', $parts));
    }
}
