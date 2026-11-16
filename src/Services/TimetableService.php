<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use App\Data\TimeslotRepository;
use App\Engine\Chromosome;
use App\Engine\FitnessEvaluator;
use PDO;

/**
 * Reads a saved timetable (one job's entries) and arranges it for display:
 * grids by level, lecturer or room, re-scoring and the quality report.
 */
final class TimetableService
{
    public const VIEWS = ['level' => 'Level', 'lecturer' => 'Lecturer', 'room' => 'Room'];

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** All entries of a job with everything a grid cell needs. */
    public function entries(int $jobId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT te.session_id, te.timeslot_id, te.room_id,
                    cs.session_no, cs.duration,
                    c.id AS course_id, c.code AS course_code, c.title AS course_title, c.needs_lab,
                    g.id AS group_id, g.code AS group_code, g.name AS group_name,
                    l.id AS lecturer_id, l.short_name AS lecturer_short, CONCAT(l.title, ' ', l.full_name) AS lecturer_name,
                    r.code AS room_code, r.name AS room_name,
                    t.day_of_week AS day, t.period_no, TIME_FORMAT(t.start_time, '%H:%i') AS start_time
             FROM timetable_entries te
             JOIN class_sessions cs ON cs.id = te.session_id
             JOIN courses c ON c.id = cs.course_id
             JOIN student_groups g ON g.id = c.group_id
             JOIN lecturers l ON l.id = c.lecturer_id
             JOIN rooms r ON r.id = te.room_id
             JOIN timeslots t ON t.id = te.timeslot_id
             WHERE te.job_id = ? AND te.school_id = ?
             ORDER BY t.day_of_week, t.period_no, c.code"
        );
        $stmt->execute([$jobId, $this->school->id]);
        return $stmt->fetchAll();
    }

    /** Choices for the view selector: view => [id => name], taken from the entries. */
    public static function options(array $entries): array
    {
        $options = ['level' => [], 'lecturer' => [], 'room' => []];
        foreach ($entries as $e) {
            $options['level'][$e['group_id']] = $e['group_code'];
            $options['lecturer'][$e['lecturer_id']] = trim($e['lecturer_name']);
            $options['room'][$e['room_id']] = $e['room_code'] . ' (' . $e['room_name'] . ')';
        }
        foreach ($options as &$list) {
            asort($list, SORT_NATURAL);
        }
        return $options;
    }

    /**
     * One grid: rows are period numbers, columns days. Each cell lists the
     * entries covering it; 'start' marks the cell where an entry begins.
     */
    public function grid(array $entries, string $view, int $id): array
    {
        $key = ['level' => 'group_id', 'lecturer' => 'lecturer_id', 'room' => 'room_id'][$view];
        $periods = (new TimeslotRepository($this->pdo, $this->school))->grid();
        $cells = [];
        foreach ($entries as $e) {
            if ((int) $e[$key] !== $id) {
                continue;
            }
            for ($k = 0; $k < (int) $e['duration']; $k++) {
                $no = (int) $e['period_no'] + $k;
                if (!isset($periods['cells'][$no][(int) $e['day']])) {
                    break;   // runs past the end of the day: shown only where it exists
                }
                $cells[$no][(int) $e['day']][] = ['entry' => $e, 'start' => $k === 0];
            }
        }
        return ['rows' => $periods['rows'], 'slots' => $periods['cells'], 'cells' => $cells];
    }

    /**
     * Turn a grid into render instructions with rowspans: a multi-hour
     * session alone in its cells becomes one tall cell; anything else
     * (including clashes) is shown period by period.
     * @return array<int, array<int, array{skip:bool, rowspan:int, items:array, clash:bool, blocked:bool}>>
     */
    public static function layout(array $grid): array
    {
        $layout = [];
        $skip = [];
        foreach (array_keys($grid['rows']) as $no) {
            foreach (array_keys(PeriodGenerator::DAYS) as $day) {
                if (!empty($skip[$no][$day])) {
                    $layout[$no][$day] = ['skip' => true];
                    continue;
                }
                $items = $grid['cells'][$no][$day] ?? [];
                $rowspan = 1;
                if (count($items) === 1 && $items[0]['start'] && (int) $items[0]['entry']['duration'] > 1) {
                    $sessionId = $items[0]['entry']['session_id'];
                    for ($k = 1; $k < (int) $items[0]['entry']['duration']; $k++) {
                        $next = $grid['cells'][$no + $k][$day] ?? [];
                        if (count($next) !== 1 || $next[0]['entry']['session_id'] !== $sessionId) {
                            break;
                        }
                        $skip[$no + $k][$day] = true;
                        $rowspan++;
                    }
                }
                $layout[$no][$day] = [
                    'skip' => false,
                    'rowspan' => $rowspan,
                    'items' => $items,
                    'clash' => count($items) > 1,
                    'blocked' => (bool) ($grid['slots'][$no][$day]['is_blocked'] ?? false),
                ];
            }
        }
        return $layout;
    }

    /** The job's timetable as a chromosome over a context loaded for exactly its sessions. */
    public function chromosome(int $jobId, ProblemContext $ctx): Chromosome
    {
        $stmt = $this->pdo->prepare('SELECT session_id, timeslot_id, room_id FROM timetable_entries WHERE job_id = ? AND school_id = ?');
        $stmt->execute([$jobId, $this->school->id]);
        $bySession = [];
        foreach ($stmt->fetchAll() as $row) {
            $bySession[(int) $row['session_id']] = $row;
        }
        $start = $room = [];
        foreach ($ctx->sessionIds as $s => $sessionId) {
            $row = $bySession[$sessionId];
            $start[$s] = $ctx->periodIndexById((int) $row['timeslot_id']);
            $room[$s] = $ctx->roomIndexById((int) $row['room_id']);
        }
        return new Chromosome($start, $room);
    }

    /** Load the job's problem with the weights it was run with. */
    public function context(array $job): ProblemContext
    {
        $weights = $job['weights_json'] ? json_decode((string) $job['weights_json'], true) : null;
        return (new ProblemLoader($this->pdo, $this->school))->load((int) $job['semester_id'], $weights, (int) $job['id']);
    }

    /**
     * Quality report: per-constraint counts and weighted totals, plus every
     * violation in plain English.
     */
    public function qualityReport(array $job): array
    {
        $ctx = $this->context($job);
        $chromosome = $this->chromosome((int) $job['id'], $ctx);
        $evaluator = new FitnessEvaluator($ctx->data);
        $evaluation = $evaluator->evaluate($chromosome);
        $weights = ['H' => $ctx->data->wH] + array_combine(['S1', 'S2', 'S3', 'S4', 'S5'], array_values($ctx->data->wS));

        $summary = [];
        foreach ($evaluation->breakdown as $code => $count) {
            $weight = str_starts_with($code, 'H') ? $weights['H'] : $weights[$code];
            $summary[$code] = ['count' => $count, 'weight' => $weight, 'weighted' => $count * $weight];
        }
        $items = array_map(fn ($item) => $item + ['text' => $this->describe($item, $ctx), 'target' => $this->target($item, $ctx)],
            $evaluator->explain($chromosome));
        usort($items, fn ($a, $b) => [$a['code'][0] === 'S', $a['code'], $a['day'] ?? 0] <=> [$b['code'][0] === 'S', $b['code'], $b['day'] ?? 0]);
        return ['evaluation' => $evaluation, 'summary' => $summary, 'items' => $items];
    }

    /**
     * The result in plain words first (BUILD_SPEC 10.3 item 7): a headline,
     * and for a timetable with clashes, what causes each one.
     * @return array{clash_free:bool, headline:string, detail:string, causes:array}
     */
    public static function verdict(array $report): array
    {
        $hard = $report['evaluation']->hard;
        $softIssues = 0;
        foreach ($report['summary'] as $code => $row) {
            $softIssues += $code[0] === 'S' ? $row['count'] : 0;
        }
        $issues = $softIssues === 1 ? '1 small preference issue' : "{$softIssues} small preference issues";
        if ($hard === 0) {
            return [
                'clash_free' => true,
                'headline' => $softIssues === 0 ? 'No clashes and no preference issues.' : "No clashes. {$issues}.",
                'detail' => 'Every lecturer, room and level is in one place at a time, every room is big enough and no class is in a blocked period.'
                    . ($softIssues > 0 ? ' The preference issues (soft rules) are listed in the quality report; they are wishes, not errors.' : ''),
                'causes' => [],
            ];
        }
        $causes = array_values(array_filter($report['items'], fn ($item) => $item['code'][0] === 'H'));
        return [
            'clash_free' => false,
            'headline' => $hard === 1 ? '1 clash remains. Here is what is causing it.' : "{$hard} clashes remain. Here is what is causing them.",
            'detail' => 'Do not publish this timetable as it is. Fix the data behind each problem below (for example more availability, '
                . 'a bigger room or fewer hours), then run Check readiness and generate again.',
            'causes' => $causes,
        ];
    }

    /** Where to look for a violation: the lecturer, room or level grid it affects. */
    private function target(array $item, ProblemContext $ctx): ?array
    {
        if ($item['lecturer'] !== null) {
            return ['view' => 'lecturer', 'vid' => $ctx->lecturerIds[$item['lecturer']], 'name' => $ctx->lecturerNames[$item['lecturer']]];
        }
        if ($item['room'] !== null) {
            return ['view' => 'room', 'vid' => $ctx->roomIds[$item['room']], 'name' => 'Room ' . $ctx->rooms[$item['room']]['code']];
        }
        $group = $item['group'] ?? ($item['sessions'] ? $ctx->data->sGroup[$item['sessions'][0]] : null);
        if ($group !== null) {
            return ['view' => 'level', 'vid' => $ctx->groupIds[$group], 'name' => $ctx->groupCodes[$group]];
        }
        return null;
    }

    /** One violation as a sentence with names. */
    private function describe(array $item, ProblemContext $ctx): string
    {
        $sessions = implode(', ', array_map(fn ($s) => $ctx->sessionLabel($s), $item['sessions']));
        $at = $item['period'] !== null ? $ctx->periodLabel($item['period']) : null;
        $day = $item['day'] !== null ? PeriodGenerator::DAYS[$ctx->periods[$ctx->data->dayFirst[$item['day']]]['day']] : '';
        return match ($item['code']) {
            'H1' => "{$ctx->lecturerNames[$item['lecturer']]} has " . count($item['sessions']) . " classes at {$at}: {$sessions}.",
            'H2' => "Room {$ctx->rooms[$item['room']]['code']} has " . count($item['sessions']) . " classes at {$at}: {$sessions}.",
            'H3' => "Level {$ctx->groupCodes[$item['group']]} has " . count($item['sessions']) . " classes at {$at}: {$sessions}.",
            'H4' => "{$sessions} has {$ctx->data->sSize[$item['sessions'][0]]} students but room {$ctx->rooms[$item['room']]['code']} seats only {$ctx->rooms[$item['room']]['capacity']}.",
            'H5' => "{$sessions} is a practical but room {$ctx->rooms[$item['room']]['code']} is not a lab.",
            'H6' => $item['reason'] === 'runs past the end of the day'
                ? "{$sessions} runs past the end of {$day} by {$item['count']} period(s)."
                : "{$sessions} is at {$at}, " . ($item['reason'] === 'blocked period' ? 'which is a blocked period.' : "when {$ctx->lecturerNames[$item['lecturer']]} is unavailable."),
            'S1' => "{$sessions} at {$at} is not a preferred period for {$ctx->lecturerNames[$item['lecturer']]}.",
            'S2' => "Level {$ctx->groupCodes[$item['group']]} has {$item['count']} idle period(s) between classes on {$day}.",
            'S3' => "{$ctx->courseCodes[$item['course']]} is taught " . count($item['sessions']) . " times on {$day}.",
            'S4' => "Level {$ctx->groupCodes[$item['group']]} has {$item['count']} period(s) beyond three in a row on {$day}.",
            'S5' => "{$sessions} uses the last period of {$day}.",
            default => $item['reason'],
        };
    }
}
