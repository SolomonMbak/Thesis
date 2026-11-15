<?php
/**
 * Demo data for development (BUILD_SPEC increment 2). Uses a fixed seed, so
 * it produces the same data every time. Not for the case-study experiments,
 * which use real department data.
 *
 *   php bin/seed.php           refuses if master data already exists
 *   php bin/seed.php --force   deletes ALL master data, sessions and timetables first
 */
declare(strict_types=1);

use App\Core\Db;
use App\Data\SettingsRepository;
use App\Engine\Rng;
use App\Services\PeriodGenerator;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

const DEMO_SEED = 20250101;

$pdo = Db::pdo();
$force = in_array('--force', $argv, true);
$existing = (int) $pdo->query('SELECT (SELECT COUNT(*) FROM lecturers) + (SELECT COUNT(*) FROM rooms)
                                    + (SELECT COUNT(*) FROM student_groups) + (SELECT COUNT(*) FROM courses)')->fetchColumn();
if ($existing > 0 && !$force) {
    fwrite(STDERR, "Master data already exists. Run with --force to delete it (and all timetables) and reseed.\n");
    exit(1);
}

$rng = new Rng(DEMO_SEED);
$pdo->beginTransaction();
try {
    if ($force) {
        foreach (['timetable_entries', 'ga_progress', 'ga_jobs', 'feasibility_checks', 'class_sessions', 'courses',
                     'lecturer_availability', 'lecturers', 'student_groups', 'rooms'] as $table) {
            $pdo->exec("DELETE FROM {$table}");
        }
    }

    $semesterId = ensureSemester($pdo);
    $groups = seedGroups($pdo);
    $rooms = seedRooms($pdo);
    $lecturers = seedLecturers($pdo);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seeding failed: ' . $ex->getMessage() . "\n");
    exit(1);
}

ensurePeriods($pdo);
[$unavailable, $preferred] = seedAvailability($pdo, $lecturers, $rng);
$courses = seedCourses($pdo, $rng, $semesterId, $groups, $lecturers);

echo "Demo data created (seed " . DEMO_SEED . "):\n";
echo '  Student groups: ' . count($groups) . " (100L 120, 200L 110, 300L 90, 400L 80)\n";
echo '  Rooms:          ' . count($rooms) . " (6 lecture rooms, 2 labs)\n";
echo '  Lecturers:      ' . count($lecturers) . " ({$unavailable} unavailable and {$preferred} preferred periods in total)\n";
echo "  Courses:        {$courses['count']} ({$courses['practicals']} practicals, {$courses['hours']} teaching hours per week)\n";
echo "Next: open Build sessions, build them, then run the feasibility check.\n";

function ensureSemester(PDO $pdo): int
{
    $id = $pdo->query('SELECT id FROM semesters WHERE is_active = 1 LIMIT 1')->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $pdo->exec("INSERT INTO semesters (session_label, semester_no, is_active) VALUES ('2025/2026', 1, 1)
                ON DUPLICATE KEY UPDATE is_active = 1");
    return (int) $pdo->query("SELECT id FROM semesters WHERE session_label = '2025/2026' AND semester_no = 1")->fetchColumn();
}

function ensurePeriods(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM timeslots')->fetchColumn() > 0) {
        return;
    }
    $s = new SettingsRepository($pdo);
    (new PeriodGenerator($pdo))->generate($s->get('day_start', '08:00'), $s->get('day_end', '18:00'), (int) $s->get('period_minutes', '60'));
}

/** @return array<string,int> code => id */
function seedGroups(PDO $pdo): array
{
    $ids = [];
    $stmt = $pdo->prepare('INSERT INTO student_groups (code, name, size) VALUES (?, ?, ?)');
    foreach (['100L' => 120, '200L' => 110, '300L' => 90, '400L' => 80] as $code => $size) {
        $stmt->execute([$code, substr($code, 0, 3) . ' Level Computer Science', $size]);
        $ids[$code] = (int) $pdo->lastInsertId();
    }
    return $ids;
}

function seedRooms(PDO $pdo): array
{
    $rooms = [
        ['LR1', 'Lecture Room 1', 60, 'lecture'], ['LR2', 'Lecture Room 2', 80, 'lecture'],
        ['LR3', 'Lecture Room 3', 100, 'lecture'], ['LT1', 'Lecture Theatre 1', 120, 'lecture'],
        ['LT2', 'Lecture Theatre 2', 150, 'lecture'], ['AUD', 'Faculty Auditorium', 200, 'lecture'],
        ['LAB1', 'Computer Laboratory 1', 60, 'lab'], ['LAB2', 'Computer Laboratory 2', 60, 'lab'],
    ];
    $stmt = $pdo->prepare('INSERT INTO rooms (code, name, capacity, room_type) VALUES (?, ?, ?, ?)');
    foreach ($rooms as $room) {
        $stmt->execute($room);
    }
    return $rooms;
}

/** @return int[] lecturer ids */
function seedLecturers(PDO $pdo): array
{
    $people = [
        ['Dr.', 'Adaeze Okafor', 'A. Okafor'], ['Prof.', 'Musa Bello', 'M. Bello'], ['Dr.', 'Chinedu Eze', 'C. Eze'],
        ['Mrs.', 'Funke Adeyemi', 'F. Adeyemi'], ['Dr.', 'Ibrahim Sani', 'I. Sani'], ['Mr.', 'Kelechi Nwosu', 'K. Nwosu'],
        ['Dr.', 'Ngozi Obi', 'N. Obi'], ['Prof.', 'Olumide Bakare', 'O. Bakare'], ['Dr.', 'Rukayat Lawal', 'R. Lawal'],
        ['Mr.', 'Samuel Etim', 'S. Etim'], ['Dr.', 'Tunde Ajayi', 'T. Ajayi'], ['Mrs.', 'Uchenna Okeke', 'U. Okeke'],
        ['Dr.', 'Yusuf Abubakar', 'Y. Abubakar'], ['Dr.', 'Zainab Garba', 'Z. Garba'],
    ];
    $stmt = $pdo->prepare('INSERT INTO lecturers (staff_no, title, full_name, short_name, email) VALUES (?, ?, ?, ?, ?)');
    $ids = [];
    foreach ($people as $i => [$title, $name, $short]) {
        $email = strtolower(str_replace(' ', '.', $name)) . '@demo.example.edu';
        $stmt->execute([sprintf('CS/%03d', $i + 1), $title, $name, $short, $email]);
        $ids[] = (int) $pdo->lastInsertId();
    }
    return $ids;
}

/** Each lecturer: 3 to 8 unavailable periods and 4 to 6 preferred periods. */
function seedAvailability(PDO $pdo, array $lecturers, Rng $rng): array
{
    $open = array_map('intval', $pdo->query('SELECT id FROM timeslots WHERE is_blocked = 0 ORDER BY day_of_week, period_no')->fetchAll(PDO::FETCH_COLUMN));
    $stmt = $pdo->prepare('INSERT INTO lecturer_availability (lecturer_id, timeslot_id, status) VALUES (?, ?, ?)');
    $totals = [0, 0];
    foreach ($lecturers as $lecturerId) {
        $slots = $rng->shuffle($open);
        $u = $rng->int(3, 8);
        $p = $rng->int(4, 6);
        foreach (array_slice($slots, 0, $u) as $slot) {
            $stmt->execute([$lecturerId, $slot, 'unavailable']);
        }
        foreach (array_slice($slots, $u, $p) as $slot) {
            $stmt->execute([$lecturerId, $slot, 'preferred']);
        }
        $totals[0] += $u;
        $totals[1] += $p;
    }
    return $totals;
}

/** 9 to 11 courses per level, 2 or 3 hours, 6 practicals in total (taught in lab groups of 60). */
function seedCourses(PDO $pdo, Rng $rng, int $semesterId, array $groups, array $lecturers): array
{
    $titles = [
        '100L' => ['Introduction to Computer Science', 'Introduction to Programming', 'Elementary Mathematics I',
            'Elementary Mathematics II', 'General Physics I', 'Use of English', 'Logic and Linear Algebra',
            'Introduction to Problem Solving', 'Computer Applications', 'Digital Literacy', 'Introductory Statistics'],
        '200L' => ['Data Structures', 'Object Oriented Programming', 'Discrete Mathematics', 'Computer Architecture',
            'Digital Logic Design', 'Numerical Methods', 'Introduction to Databases', 'Systems Analysis',
            'Web Programming', 'Probability for Computing', 'Entrepreneurship Studies'],
        '300L' => ['Operating Systems', 'Design and Analysis of Algorithms', 'Database Management Systems',
            'Software Engineering', 'Computer Networks', 'Theory of Computation', 'Compiler Construction',
            'Human Computer Interaction', 'Information Security', 'Research Methods', 'Mobile Application Development'],
        '400L' => ['Artificial Intelligence', 'Distributed Systems', 'Computer Graphics', 'Project Management',
            'Machine Learning', 'Cloud Computing', 'Formal Methods', 'Professional Practice in Computing',
            'Data Mining', 'Modelling and Simulation', 'Seminar'],
    ];
    $practicalsPerLevel = ['100L' => 2, '200L' => 2, '300L' => 1, '400L' => 1];
    $teachers = $rng->shuffle($lecturers);
    $next = 0;
    $stmt = $pdo->prepare(
        'INSERT INTO courses (semester_id, code, title, credit_units, hours_per_week, needs_lab, allow_two_hour_block,
                              class_size_override, group_id, lecturer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $summary = ['count' => 0, 'practicals' => 0, 'hours' => 0];
    foreach ($titles as $level => $list) {
        $count = $rng->int(9, 11);
        $practicalSlots = array_slice($rng->shuffle(range(0, $count - 1)), 0, $practicalsPerLevel[$level]);
        for ($i = 0; $i < $count; $i++) {
            $practical = in_array($i, $practicalSlots, true);
            $hours = $practical ? 3 : $rng->int(2, 3);
            $twoHour = !$practical && $hours === 3 && $rng->chance(0.5);
            $code = sprintf('CSC %d%02d', (int) $level[0], $i + 1);
            $title = $list[$i] . ($practical ? ' Practical' : '');
            $stmt->execute([$semesterId, $code, $title, $hours, $hours, $practical ? 1 : 0, $twoHour ? 1 : 0,
                $practical ? 60 : null, $groups[$level], $teachers[$next++ % count($teachers)]]);
            $summary['count']++;
            $summary['practicals'] += $practical ? 1 : 0;
            $summary['hours'] += $hours;
        }
    }
    return $summary;
}
