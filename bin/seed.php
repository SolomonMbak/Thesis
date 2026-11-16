<?php
/**
 * Demo data for development (BUILD_SPEC increment 2). Uses a fixed seed, so
 * it produces the same data every time. Not for the case-study experiments,
 * which use real department data.
 *
 * Creates two schools so tenant isolation can be checked by eye:
 *   demo          "Demo University, Department of Computer Science", about 40 courses
 *   test-college  "Test College", a small school whose codes overlap with demo (100L, LR1, CS/001, CSC 101)
 *
 *   php bin/seed.php                     refuses if either school already exists
 *   php bin/seed.php --force             deletes those two schools (and only them) first
 *   php bin/seed.php --owner=you@x.edu   owner of the demo school (default: the first platform administrator)
 */
declare(strict_types=1);

use App\Core\Db;
use App\Core\SchoolContext;
use App\Data\SchoolRepository;
use App\Data\UserRepository;
use App\Engine\Rng;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

const DEMO_SEED = 20250101;
const TEST_OWNER = ['Test College Owner', 'owner@testcollege.example.edu', 'testcollege123'];
const DEMO_OWNER = ['Demo Owner', 'owner@demo.example.edu', 'demopassword1'];

$options = getopt('', ['force', 'owner:']);
$pdo = Db::pdo();
$schools = new SchoolRepository($pdo);
$users = new UserRepository($pdo);

foreach (['demo', 'test-college'] as $slug) {
    $existing = $schools->findBySlug($slug);
    if ($existing && !isset($options['force'])) {
        fwrite(STDERR, "The school '{$slug}' already exists. Run with --force to delete the two demo schools and reseed them.\n");
        exit(1);
    }
    if ($existing) {
        $schools->delete((int) $existing['id']);
    }
}

$demoOwner = demoOwner($pdo, $users, $options['owner'] ?? null);
$testOwner = ensureUser($users, ...TEST_OWNER);

$rng = new Rng(DEMO_SEED);
$demo = SchoolContext::system($schools->find($schools->create([
    'name' => 'Demo University', 'slug' => 'demo', 'department_label' => 'Department of Computer Science',
], $demoOwner['id'])));
$schools->updateProfile($demo->id, ['name' => 'Demo University', 'school_type' => 'university',
    'department_label' => 'Department of Computer Science', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos']);
(new App\Data\SchoolSettingsRepository($pdo, $demo))->set('profile_saved_at', date('Y-m-d H:i:s'));
$semesterId = (int) (new App\Data\SemesterRepository($pdo, $demo))->active()['id'];
$holidays = seedCalendar($pdo, $demo, $semesterId);

$groups = seedGroups($pdo, $demo, ['100L' => 120, '200L' => 110, '300L' => 90, '400L' => 80]);
$rooms = seedRooms($pdo, $demo, [
    ['LR1', 'Lecture Room 1', 60, 'lecture'], ['LR2', 'Lecture Room 2', 80, 'lecture'],
    ['LR3', 'Lecture Room 3', 100, 'lecture'], ['LT1', 'Lecture Theatre 1', 120, 'lecture'],
    ['LT2', 'Lecture Theatre 2', 150, 'lecture'], ['AUD', 'Faculty Auditorium', 200, 'lecture'],
    ['LAB1', 'Computer Laboratory 1', 60, 'lab'], ['LAB2', 'Computer Laboratory 2', 60, 'lab'],
]);
$lecturers = seedLecturers($pdo, $demo, [
    ['Dr.', 'Adaeze Okafor', 'A. Okafor'], ['Prof.', 'Musa Bello', 'M. Bello'], ['Dr.', 'Chinedu Eze', 'C. Eze'],
    ['Mrs.', 'Funke Adeyemi', 'F. Adeyemi'], ['Dr.', 'Ibrahim Sani', 'I. Sani'], ['Mr.', 'Kelechi Nwosu', 'K. Nwosu'],
    ['Dr.', 'Ngozi Obi', 'N. Obi'], ['Prof.', 'Olumide Bakare', 'O. Bakare'], ['Dr.', 'Rukayat Lawal', 'R. Lawal'],
    ['Mr.', 'Samuel Etim', 'S. Etim'], ['Dr.', 'Tunde Ajayi', 'T. Ajayi'], ['Mrs.', 'Uchenna Okeke', 'U. Okeke'],
    ['Dr.', 'Yusuf Abubakar', 'Y. Abubakar'], ['Dr.', 'Zainab Garba', 'Z. Garba'],
], 'demo.example.edu');
[$unavailable, $preferred] = seedAvailability($pdo, $demo, $lecturers, $rng);
$courses = seedDemoCourses($pdo, $demo, $rng, $semesterId, $groups, $lecturers);

$test = SchoolContext::system($schools->find($schools->create(['name' => 'Test College', 'slug' => 'test-college', 'school_type' => 'college'], $testOwner['id'])));
$testSemester = (int) (new App\Data\SemesterRepository($pdo, $test))->active()['id'];
$testGroups = seedGroups($pdo, $test, ['100L' => 40, '200L' => 35]);
seedRooms($pdo, $test, [['LR1', 'Main Hall', 50, 'lecture'], ['LAB1', 'Science Lab', 40, 'lab']]);
$testLecturers = seedLecturers($pdo, $test, [['Mr.', 'Peter Ado', 'P. Ado'], ['Mrs.', 'Joy Uche', 'J. Uche']], 'testcollege.example.edu');
$insert = $pdo->prepare('INSERT INTO courses (school_id, semester_id, code, title, credit_units, hours_per_week, needs_lab, group_id, lecturer_id)
                         VALUES (?, ?, ?, ?, 2, ?, ?, ?, ?)');
foreach ([['CSC 101', 'Basic Computing', 2, 0, '100L', 0], ['CSC 102', 'Computing Practical', 2, 1, '100L', 1],
             ['CSC 201', 'Office Applications', 2, 0, '200L', 1], ['MTH 201', 'Business Mathematics', 3, 0, '200L', 0]] as [$code, $title, $hours, $lab, $level, $l]) {
    $insert->execute([$test->id, $testSemester, $code, $title, $hours, $lab, $testGroups[$level], $testLecturers[$l]]);
}

echo "Demo data created (seed " . DEMO_SEED . "):\n";
echo "  School demo (Demo University, Department of Computer Science), owner {$demoOwner['email']}\n";
echo '    Levels:     ' . count($groups) . " (100L 120, 200L 110, 300L 90, 400L 80)\n";
echo '    Rooms:      ' . count($rooms) . " (6 lecture rooms, 2 labs)\n";
echo '    Lecturers:  ' . count($lecturers) . " ({$unavailable} unavailable and {$preferred} preferred periods in total)\n";
echo "    Courses:    {$courses['count']} ({$courses['practicals']} practicals, {$courses['hours']} teaching hours per week)\n";
echo "    Calendar:   {$holidays['from']} to {$holidays['to']}, {$holidays['closed']} weekdays without teaching (month view)\n";
echo "  School test-college (Test College), owner " . TEST_OWNER[1] . ' / password ' . TEST_OWNER[2] . "\n";
echo "    2 levels, 2 rooms, 2 lecturers, 4 courses; codes overlap with demo on purpose.\n";
if ($demoOwner['created']) {
    echo '  Demo owner account created: ' . DEMO_OWNER[1] . ' / password ' . DEMO_OWNER[2] . "\n";
}
echo "Public pages: /s/demo and /s/test-college (after publishing). Next: sign in and follow the Setup Guide.\n";

/**
 * Demo semester dates for the month view: 13 teaching weeks from the second
 * Monday of September of the session's first year, with Independence Day
 * (1 October, when it is a weekday) and a mid-semester break in week 7.
 */
function seedCalendar(PDO $pdo, SchoolContext $school, int $semesterId): array
{
    $semester = (new App\Data\SemesterRepository($pdo, $school))->find($semesterId);
    $year = (int) substr((string) $semester['session_label'], 0, 4);
    $start = new DateTimeImmutable("second monday of september {$year}");
    $end = $start->modify('+12 weeks +4 days');
    $calendar = new App\Data\CalendarRepository($pdo, $school);
    $calendar->setDates($semesterId, $start->format('Y-m-d'), $end->format('Y-m-d'));
    $closed = 0;
    $independence = new DateTimeImmutable("{$year}-10-01");
    if ((int) $independence->format('N') <= 5) {
        $calendar->addClosure($semesterId, $independence->format('Y-m-d'), 'Independence Day');
        $closed++;
    }
    $break = $start->modify('+6 weeks');
    for ($d = 0; $d < 5; $d++) {
        $calendar->addClosure($semesterId, $break->modify("+{$d} days")->format('Y-m-d'), 'Mid-semester break');
        $closed++;
    }
    return ['from' => $start->format('j M Y'), 'to' => $end->format('j M Y'), 'closed' => $closed];
}

/** The demo school's owner: --owner, else the first platform admin, else a demo account. */
function demoOwner(PDO $pdo, UserRepository $users, ?string $email): array
{
    if ($email !== null) {
        $user = $users->findByEmail($email);
        if ($user === null) {
            fwrite(STDERR, "No account uses {$email}.\n");
            exit(1);
        }
        return ['id' => (int) $user['id'], 'email' => $user['email'], 'created' => false];
    }
    $admin = $pdo->query('SELECT id, email FROM users WHERE is_platform_admin = 1 ORDER BY id LIMIT 1')->fetch();
    if ($admin) {
        return ['id' => (int) $admin['id'], 'email' => $admin['email'], 'created' => false];
    }
    return ensureUser($users, ...DEMO_OWNER);
}

function ensureUser(UserRepository $users, string $name, string $email, string $password): array
{
    $user = $users->findByEmail($email);
    if ($user) {
        return ['id' => (int) $user['id'], 'email' => $email, 'created' => false];
    }
    return ['id' => $users->create($name, $email, $password, true), 'email' => $email, 'created' => true];
}

/** @return array<string,int> code => id */
function seedGroups(PDO $pdo, SchoolContext $school, array $levels): array
{
    $ids = [];
    $stmt = $pdo->prepare('INSERT INTO student_groups (school_id, code, name, size) VALUES (?, ?, ?, ?)');
    foreach ($levels as $code => $size) {
        $stmt->execute([$school->id, $code, substr($code, 0, 3) . ' Level Computer Science', $size]);
        $ids[$code] = (int) $pdo->lastInsertId();
    }
    return $ids;
}

function seedRooms(PDO $pdo, SchoolContext $school, array $rooms): array
{
    $stmt = $pdo->prepare('INSERT INTO rooms (school_id, code, name, capacity, room_type) VALUES (?, ?, ?, ?, ?)');
    foreach ($rooms as $room) {
        $stmt->execute([$school->id, ...$room]);
    }
    return $rooms;
}

/** @return int[] lecturer ids */
function seedLecturers(PDO $pdo, SchoolContext $school, array $people, string $domain): array
{
    $stmt = $pdo->prepare('INSERT INTO lecturers (school_id, staff_no, title, full_name, short_name, email) VALUES (?, ?, ?, ?, ?, ?)');
    $ids = [];
    foreach ($people as $i => [$title, $name, $short]) {
        $email = strtolower(str_replace(' ', '.', $name)) . '@' . $domain;
        $stmt->execute([$school->id, sprintf('CS/%03d', $i + 1), $title, $name, $short, $email]);
        $ids[] = (int) $pdo->lastInsertId();
    }
    return $ids;
}

/** Each lecturer: 3 to 8 unavailable periods and 4 to 6 preferred periods. */
function seedAvailability(PDO $pdo, SchoolContext $school, array $lecturers, Rng $rng): array
{
    $stmt = $pdo->prepare('SELECT id FROM timeslots WHERE school_id = ? AND is_blocked = 0 ORDER BY day_of_week, period_no');
    $stmt->execute([$school->id]);
    $open = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $insert = $pdo->prepare('INSERT INTO lecturer_availability (school_id, lecturer_id, timeslot_id, status) VALUES (?, ?, ?, ?)');
    $totals = [0, 0];
    foreach ($lecturers as $lecturerId) {
        $slots = $rng->shuffle($open);
        $u = $rng->int(3, 8);
        $p = $rng->int(4, 6);
        foreach (array_slice($slots, 0, $u) as $slot) {
            $insert->execute([$school->id, $lecturerId, $slot, 'unavailable']);
        }
        foreach (array_slice($slots, $u, $p) as $slot) {
            $insert->execute([$school->id, $lecturerId, $slot, 'preferred']);
        }
        $totals[0] += $u;
        $totals[1] += $p;
    }
    return $totals;
}

/** 9 to 11 courses per level, 2 or 3 hours, 6 practicals in total (taught in lab groups of 60). */
function seedDemoCourses(PDO $pdo, SchoolContext $school, Rng $rng, int $semesterId, array $groups, array $lecturers): array
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
        'INSERT INTO courses (school_id, semester_id, code, title, credit_units, hours_per_week, needs_lab, allow_two_hour_block,
                              class_size_override, group_id, lecturer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            $stmt->execute([$school->id, $semesterId, $code, $title, $hours, $hours, $practical ? 1 : 0, $twoHour ? 1 : 0,
                $practical ? 60 : null, $groups[$level], $teachers[$next++ % count($teachers)]]);
            $summary['count']++;
            $summary['practicals'] += $practical ? 1 : 0;
            $summary['hours'] += $hours;
        }
    }
    return $summary;
}
