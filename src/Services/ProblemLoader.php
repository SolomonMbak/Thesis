<?php
declare(strict_types=1);

namespace App\Services;

use App\Engine\ProblemData;
use PDO;

/**
 * Reads one semester's data from the database and builds the engine's
 * ProblemData. This and bin/run_ga.php are the only bridges between the
 * database and the engine.
 */
final class ProblemLoader
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Current weights from constraint_weights, as ['H' => 1000, 'S1' => 5, ...]. */
    public function currentWeights(): array
    {
        $rows = $this->pdo->query('SELECT code, weight FROM constraint_weights')->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map('intval', $rows);
    }

    /**
     * @param array|null $weights snapshot to use (e.g. from ga_jobs.weights_json); null = current weights
     */
    public function load(int $semesterId, ?array $weights = null): ProblemContext
    {
        $weights ??= $this->currentWeights();

        $periodRows = $this->pdo->query(
            "SELECT id, day_of_week, period_no, TIME_FORMAT(start_time, '%H:%i') AS start_time,
                    TIME_FORMAT(end_time, '%H:%i') AS end_time, is_blocked
             FROM timeslots ORDER BY day_of_week, period_no"
        )->fetchAll();
        $roomRows = $this->pdo->query('SELECT * FROM rooms ORDER BY code')->fetchAll();

        $stmt = $this->pdo->prepare(
            'SELECT cs.id, cs.session_no, cs.duration, cs.gene_index,
                    c.id AS course_id, c.code, c.title, c.needs_lab, c.class_size_override,
                    g.id AS group_id, g.code AS group_code, g.size AS group_size,
                    l.id AS lecturer_id, l.title AS lecturer_title, l.full_name, l.short_name
             FROM class_sessions cs
             JOIN courses c ON c.id = cs.course_id
             JOIN student_groups g ON g.id = c.group_id
             JOIN lecturers l ON l.id = c.lecturer_id
             WHERE c.semester_id = ? AND cs.archived = 0
             ORDER BY cs.gene_index'
        );
        $stmt->execute([$semesterId]);
        $sessionRows = $stmt->fetchAll();

        // Index maps
        $periodIndex = [];
        $periods = [];
        foreach ($periodRows as $p => $row) {
            $periodIndex[(int) $row['id']] = $p;
            $periods[] = [
                'day' => (int) $row['day_of_week'], 'period_no' => (int) $row['period_no'],
                'start' => $row['start_time'], 'end' => $row['end_time'], 'blocked' => (bool) $row['is_blocked'],
            ];
        }
        $rooms = [];
        foreach ($roomRows as $row) {
            $rooms[] = [
                'code' => $row['code'], 'name' => $row['name'], 'capacity' => (int) $row['capacity'],
                'lab' => $row['room_type'] === 'lab', 'active' => (bool) $row['is_active'],
            ];
        }

        $lecIndex = $groupIndex = $courseIndex = [];
        $lecturerNames = $groupCodes = $courseCodes = [];
        $sessions = $sessionInput = $sessionIds = [];
        foreach ($sessionRows as $row) {
            $l = $lecIndex[(int) $row['lecturer_id']] ??= count($lecIndex);
            $lecturerNames[$l] = trim($row['lecturer_title'] . ' ' . $row['full_name']);
            $g = $groupIndex[(int) $row['group_id']] ??= count($groupIndex);
            $groupCodes[$g] = $row['group_code'];
            $c = $courseIndex[(int) $row['course_id']] ??= count($courseIndex);
            $courseCodes[$c] = $row['code'];

            $size = $row['class_size_override'] !== null ? (int) $row['class_size_override'] : (int) $row['group_size'];
            $sessionIds[] = (int) $row['id'];
            $sessionInput[] = [
                'lecturer' => $l, 'group' => $g, 'course' => $c, 'size' => $size,
                'lab' => (bool) $row['needs_lab'], 'duration' => (int) $row['duration'],
            ];
            $sessions[] = [
                'course_id' => (int) $row['course_id'], 'code' => $row['code'], 'title' => $row['title'],
                'session_no' => (int) $row['session_no'], 'duration' => (int) $row['duration'],
                'lab' => (bool) $row['needs_lab'], 'size' => $size,
                'group' => $row['group_code'], 'lecturer' => $row['short_name'],
            ];
        }

        $lecturers = $this->loadAvailability(array_keys($lecIndex), $periodIndex);

        $data = ProblemData::fromArrays([
            'periods' => array_map(fn ($p) => ['day' => $p['day'], 'blocked' => $p['blocked']], $periods),
            'rooms' => array_map(fn ($r) => ['capacity' => $r['capacity'], 'lab' => $r['lab'], 'active' => $r['active']], $rooms),
            'lecturers' => $lecturers,
            'groups' => count($groupIndex),
            'courses' => count($courseIndex),
            'sessions' => $sessionInput,
            'weights' => $weights,
        ]);

        return new ProblemContext(
            $data,
            $sessionIds,
            $sessions,
            array_map('intval', array_column($periodRows, 'id')),
            $periods,
            array_map('intval', array_column($roomRows, 'id')),
            $rooms,
            array_keys($lecIndex),
            $lecturerNames,
            array_keys($groupIndex),
            $groupCodes,
            array_keys($courseIndex),
            $courseCodes,
            $weights
        );
    }

    /** Unavailable and preferred period indexes for each lecturer, in index order. */
    private function loadAvailability(array $lecturerIds, array $periodIndex): array
    {
        $result = array_fill(0, count($lecturerIds), ['unavailable' => [], 'preferred' => []]);
        if (!$lecturerIds) {
            return [];
        }
        $byId = array_flip($lecturerIds);
        $placeholders = implode(',', array_fill(0, count($lecturerIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT lecturer_id, timeslot_id, status FROM lecturer_availability
             WHERE lecturer_id IN ({$placeholders}) AND status <> 'available'"
        );
        $stmt->execute($lecturerIds);
        foreach ($stmt->fetchAll() as $row) {
            $l = $byId[(int) $row['lecturer_id']];
            $p = $periodIndex[(int) $row['timeslot_id']] ?? null;
            if ($p !== null) {
                $result[$l][$row['status']][] = $p;
            }
        }
        return $result;
    }
}
