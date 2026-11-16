<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use App\Data\HelpProgressRepository;
use App\Data\JobRepository;
use App\Data\SchoolRepository;
use App\Data\SchoolSettingsRepository;
use App\Data\SemesterRepository;
use App\Data\TableRepository;
use App\Data\TimeslotRepository;
use PDO;

/**
 * The 9-step Setup Guide (BUILD_SPEC 10.2). Each step's status is worked out
 * from the school's data every time, never from a ticked checkbox, so the
 * guide is always true: deleting the last room makes step 2 "To do" again.
 */
final class SetupGuide
{
    public const SKIP_AVAILABILITY = 'skip_availability';
    private const OPTIONAL = [6];

    /** Pages that belong to a step, for the "Next step" banner. */
    private const STEP_ROUTES = [
        'school/profile' => 1, 'periods' => 1, 'rooms' => 2, 'groups' => 3, 'lecturers' => 4, 'courses' => 5,
        'availability' => 6, 'import' => 5, 'feasibility' => 7, 'sessions' => 7, 'generate' => 8, 'timetables' => 9, 'published' => 9,
    ];

    private ?array $steps = null;

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /**
     * @return array<int, array{no:int, title:string, why:string, status:string, done:bool, optional:bool,
     *                          count:?string, locked_reason:?string, route:string, button:string}>
     */
    public function steps(): array
    {
        if ($this->steps !== null) {
            return $this->steps;
        }
        $f = $this->facts();
        $done = [
            1 => $f['profile_saved'] && $f['open_periods'] > 0,
            2 => $f['rooms'] > 0,
            3 => $f['levels'] > 0,
            4 => $f['lecturers'] > 0,
            5 => $f['courses'] > 0,
            6 => $f['skipped_availability'] || ($f['lecturers'] > 0 && $f['with_availability'] * 2 >= $f['lecturers']),
            7 => $f['ready'],
            8 => $f['clash_free_run'],
            9 => $f['published'],
        ];
        $needs = [1 => [], 2 => [1], 3 => [1], 4 => [1], 5 => [3, 4], 6 => [4], 7 => [2, 5], 8 => [7], 9 => [8]];
        $defs = $this->definitions($f);
        $steps = [];
        foreach ($defs as $no => $def) {
            $missing = array_values(array_filter($needs[$no], fn ($n) => !$done[$n]));
            $optional = in_array($no, self::OPTIONAL, true);
            $status = $done[$no] ? 'done' : ($missing ? 'locked' : ($optional ? 'optional' : 'todo'));
            $steps[$no] = $def + [
                'no' => $no,
                'done' => $done[$no],
                'optional' => $optional,
                'status' => $status,
                'locked_reason' => $missing && !$done[$no]
                    ? 'Finish ' . (count($missing) === 1 ? 'step ' : 'steps ') . implode(' and ', $missing) . ' first.' : null,
            ];
        }
        return $this->steps = $steps;
    }

    public function doneCount(): int
    {
        return count(array_filter($this->steps(), fn ($s) => $s['done']));
    }

    /** All required steps done (step 6 is optional). Records the date once. */
    public function isComplete(): bool
    {
        foreach ($this->steps() as $step) {
            if (!$step['done'] && !$step['optional']) {
                return false;
            }
        }
        if (empty($this->school->school['setup_completed_at'])) {
            (new SchoolRepository($this->pdo))->markSetupComplete($this->school->id);
        }
        return true;
    }

    /** The first required step not done yet (or the optional one if it is all that is left). */
    public function next(): ?array
    {
        foreach ($this->steps() as $step) {
            if (!$step['done'] && $step['status'] !== 'locked' && !$step['optional']) {
                return $step;
            }
        }
        foreach ($this->steps() as $step) {
            if (!$step['done'] && $step['status'] !== 'locked') {
                return $step;
            }
        }
        return null;
    }

    /** Step number a page belongs to, or null. */
    public static function stepForRoute(string $route): ?int
    {
        $base = implode('/', array_slice(explode('/', $route), 0, 2));
        return self::STEP_ROUTES[$base] ?? self::STEP_ROUTES[explode('/', $route)[0]] ?? null;
    }

    private function facts(): array
    {
        $semester = (new SemesterRepository($this->pdo, $this->school))->active();
        $semesterId = $semester ? (int) $semester['id'] : 0;
        $rooms = new TableRepository($this->pdo, $this->school, 'rooms');
        $lecturers = new TableRepository($this->pdo, $this->school, 'lecturers');
        $jobs = new JobRepository($this->pdo, $this->school);
        return [
            'semester' => $semester,
            'profile_saved' => (new SchoolSettingsRepository($this->pdo, $this->school))->get('profile_saved_at', '') !== '',
            'open_periods' => (new TimeslotRepository($this->pdo, $this->school))->openCount(),
            'rooms' => $rooms->count('t.is_active = 1'),
            'labs' => $rooms->count("t.is_active = 1 AND t.room_type = 'lab'"),
            'levels' => (new TableRepository($this->pdo, $this->school, 'student_groups'))->count(),
            'lecturers' => $lecturers->count('t.is_active = 1'),
            'courses' => $semesterId ? (new TableRepository($this->pdo, $this->school, 'courses'))->count('t.semester_id = ?', [$semesterId]) : 0,
            'with_availability' => (new AvailabilityService($this->pdo, $this->school))->lecturersWithAvailability(),
            'skipped_availability' => HelpProgressRepository::anyoneDid($this->pdo, $this->school, self::SKIP_AVAILABILITY),
            'ready' => $semesterId > 0 && (new FeasibilityService($this->pdo, $this->school))->passedAndCurrent($semesterId),
            'clash_free_run' => $semesterId > 0 && $jobs->hasClashFree($semesterId),
            'published' => $semesterId > 0 && $jobs->published($semesterId) !== null,
        ];
    }

    private function definitions(array $f): array
    {
        $plural = fn (int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
        return [
            1 => ['title' => 'School profile and teaching week', 'why' => 'Sets your days, hours and blocked periods (for example Friday prayers).',
                'count' => $f['open_periods'] > 0 ? $plural($f['open_periods'], 'open period', 'open periods') . ' a week' : null,
                'route' => 'school/profile', 'button' => 'Open school profile'],
            2 => ['title' => 'Add rooms', 'why' => 'The timetable can only use rooms you list.',
                'count' => $f['rooms'] > 0 ? $plural($f['rooms'], 'room', 'rooms') . ' added, ' . $plural($f['labs'], 'lab', 'labs') : null,
                'route' => 'rooms', 'button' => 'Add rooms'],
            3 => ['title' => 'Add levels', 'why' => 'Each level must never have two classes at once.',
                'count' => $f['levels'] > 0 ? $plural($f['levels'], 'level', 'levels') . ' added' : null,
                'route' => 'groups', 'button' => 'Add levels'],
            4 => ['title' => 'Add lecturers', 'why' => 'Each lecturer must never be in two places at once.',
                'count' => $f['lecturers'] > 0 ? $plural($f['lecturers'], 'lecturer', 'lecturers') . ' added' : null,
                'route' => 'lecturers', 'button' => 'Add lecturers'],
            5 => ['title' => 'Add courses', 'why' => 'Links each course to a level, a lecturer and its weekly hours.',
                'count' => $f['courses'] > 0 ? $plural($f['courses'], 'course', 'courses') . ' this semester' : null,
                'route' => 'courses', 'button' => 'Add courses'],
            6 => ['title' => 'Set lecturer availability (optional but recommended)',
                'why' => 'Keeps classes out of times lecturers cannot teach and favours their preferred times.',
                'count' => $f['lecturers'] > 0 ? $f['with_availability'] . ' of ' . $f['lecturers'] . ' lecturers done'
                    . ($f['skipped_availability'] ? ' (skipped for now)' : '') : null,
                'route' => 'availability', 'button' => 'Set availability'],
            7 => ['title' => 'Check readiness', 'why' => 'Finds problems that make a clash-free timetable impossible, before you wait for a run.',
                'count' => null, 'route' => 'feasibility', 'button' => 'Check readiness'],
            8 => ['title' => 'Generate your timetable', 'why' => 'Runs the search for the best clash-free timetable.',
                'count' => null, 'route' => 'generate', 'button' => 'Generate'],
            9 => ['title' => 'Review and publish', 'why' => 'Lets lecturers and students see the final timetable.',
                'count' => $f['published'] ? 'Published' : null, 'route' => 'timetables', 'button' => 'Review runs'],
        ];
    }
}
