<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use App\Engine\FitnessEvaluator;
use PDO;

/**
 * Imports an existing (hand-made) timetable from CSV and scores it with the
 * same FitnessEvaluator as the GA (BUILD_SPEC section 11), so the MAN
 * treatment in the experiments is measured exactly like SGA and RGA.
 *
 * Columns: course_code, session_no, day, start_time, room_code.
 * Every row must match a class session of the active semester, and every
 * session must appear exactly once; anything unmatched is reported and
 * nothing is saved.
 */
final class ManualTimetableImporter
{
    public const COLUMNS = ['course_code', 'session_no', 'day', 'start_time', 'room_code'];
    private const DAY_NAMES = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5,
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5];

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** @return array{ok:bool, errors:string[], job_id?:int, hard?:int, penalty?:int} */
    public function import(string $path, int $semesterId, string $label, ?int $userId): array
    {
        $read = CsvImporter::read($path, self::COLUMNS);
        if ($read['errors']) {
            return ['ok' => false, 'errors' => $read['errors']];
        }
        // Session numbers in the file follow the current courses.
        (new SessionBuilder($this->pdo, $this->school))->ensureCurrent($semesterId);
        [$entries, $errors] = $this->match($read['rows'], $semesterId);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                "INSERT INTO ga_jobs (school_id, semester_id, job_type, label, status, created_by, started_at, finished_at, generations_run)
                 VALUES (?, ?, 'manual', ?, 'finished', ?, NOW(), NOW(), 0)"
            )->execute([$this->school->id, $semesterId, $label === '' ? 'Manual timetable' : $label, $userId]);
            $jobId = (int) $this->pdo->lastInsertId();
            $insert = $this->pdo->prepare('INSERT INTO timetable_entries (school_id, job_id, session_id, timeslot_id, room_id) VALUES (?, ?, ?, ?, ?)');
            foreach ($entries as $e) {
                $insert->execute([$this->school->id, $jobId, $e['session_id'], $e['timeslot_id'], $e['room_id']]);
            }
            $score = $this->score($jobId, $semesterId);
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        return ['ok' => true, 'errors' => [], 'job_id' => $jobId] + $score;
    }

    /** Match rows to sessions, periods and rooms. @return array{0: array, 1: string[]} */
    private function match(array $rows, int $semesterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cs.id, UPPER(c.code) AS code, cs.session_no FROM class_sessions cs
             JOIN courses c ON c.id = cs.course_id WHERE cs.school_id = ? AND c.semester_id = ? AND cs.archived = 0'
        );
        $stmt->execute([$this->school->id, $semesterId]);
        $sessions = [];
        foreach ($stmt->fetchAll() as $s) {
            $sessions[self::norm($s['code']) . '#' . $s['session_no']] = (int) $s['id'];
        }
        if (!$sessions) {
            return [[], ['This semester has no courses yet, so there are no classes to place. Add the courses first, then import the manual timetable.']];
        }
        $slots = $this->pairs("SELECT CONCAT(day_of_week, ' ', TIME_FORMAT(start_time, '%H:%i')), id FROM timeslots WHERE school_id = ?");
        $rooms = array_change_key_case($this->pairs('SELECT code, id FROM rooms WHERE school_id = ?'), CASE_UPPER);

        $entries = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $line => $r) {
            $problems = [];
            $key = self::norm($r['course_code']) . '#' . (int) $r['session_no'];
            $sessionId = $sessions[$key] ?? null;
            if ($sessionId === null) {
                $problems[] = "no session {$r['session_no']} of course '{$r['course_code']}' in this semester";
            } elseif (isset($seen[$sessionId])) {
                $problems[] = "session already given on row {$seen[$sessionId]}";
            }
            $day = self::DAY_NAMES[strtolower($r['day'])] ?? null;
            $time = preg_match('/^(\d{1,2}):(\d{2})/', $r['start_time'], $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
            $slotId = $day && $time ? ($slots["{$day} {$time}"] ?? null) : null;
            if ($day === null) {
                $problems[] = 'day must be Mon to Fri';
            } elseif ($slotId === null) {
                $problems[] = "no period starts at '{$r['start_time']}'";
            }
            $roomId = $rooms[strtoupper($r['room_code'])] ?? null;
            if ($roomId === null) {
                $problems[] = "room '{$r['room_code']}' does not exist";
            }
            if ($problems) {
                $errors[] = "Row {$line}: " . implode('; ', $problems) . '.';
                continue;
            }
            $seen[$sessionId] = $line;
            $entries[] = ['session_id' => $sessionId, 'timeslot_id' => (int) $slotId, 'room_id' => (int) $roomId];
        }
        $matchedIds = array_flip(array_column($entries, 'session_id'));
        foreach ($sessions as $key => $id) {
            if (!isset($matchedIds[$id]) && !$this->sessionNamedInBadRow($key, $rows)) {
                [$code, $no] = explode('#', $key);
                $errors[] = "Session {$no} of {$code} has no row in the file. Every class session must be placed once.";
            }
        }
        return [$entries, $errors];
    }

    /** Score the imported job with the evaluator and store the result. */
    private function score(int $jobId, int $semesterId): array
    {
        $loader = new ProblemLoader($this->pdo, $this->school);
        $weights = $loader->currentWeights();
        $ctx = $loader->load($semesterId, $weights, $jobId);
        $chromosome = (new TimetableService($this->pdo, $this->school))->chromosome($jobId, $ctx);
        $e = (new FitnessEvaluator($ctx->data))->evaluate($chromosome);
        $this->pdo->prepare(
            'UPDATE ga_jobs SET weights_json = ?, best_fitness = ?, best_penalty = ?, hard_violations = ?,
                    soft_penalty = ?, breakdown_json = ?, runtime_ms = 0 WHERE id = ? AND school_id = ?'
        )->execute([json_encode($weights), $e->fitness, $e->penalty, $e->hard, $e->soft, json_encode($e->breakdown), $jobId, $this->school->id]);
        return ['hard' => $e->hard, 'penalty' => $e->penalty];
    }

    /** key => id pairs from a query with one school_id placeholder. */
    private function pairs(string $sql): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** True if some row names this session (so its own row error already explains it). */
    private function sessionNamedInBadRow(string $key, array $rows): bool
    {
        foreach ($rows as $r) {
            if (self::norm($r['course_code']) . '#' . (int) $r['session_no'] === $key) {
                return true;
            }
        }
        return false;
    }

    /** Course codes compared without case or spaces ("csc101" = "CSC 101"). */
    private static function norm(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', $code));
    }
}
