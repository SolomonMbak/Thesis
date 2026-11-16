<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Audit;
use App\Core\SchoolContext;
use App\Data\AuditRepository;
use App\Data\CalendarRepository;
use App\Data\InvitationRepository;
use App\Data\JobQueue;
use App\Data\JobRepository;
use App\Data\MemberRepository;
use App\Data\SchoolRepository;
use App\Data\SchoolSettingsRepository;
use App\Data\SemesterRepository;
use App\Data\TableRepository;
use App\Data\TimeslotRepository;
use App\Data\UserRepository;
use App\Data\WeightRepository;
use App\Services\AvailabilityService;
use App\Services\CsvImporter;
use App\Services\FeasibilityService;
use App\Services\JobRunner;
use App\Services\ProblemLoader;
use App\Services\SessionBuilder;
use App\Services\TimetableService;
use PDO;

require_once __DIR__ . '/HttpClient.php';

/**
 * BUILD_SPEC 13.1: two schools with overlapping codes (both have room R1,
 * level 100L, lecturer S1, course C1). Proves that nothing of school B is
 * ever returned to school A: not by any repository or service, list page,
 * edit page, API endpoint, export or public page, and not by guessing ids.
 *
 * School B's records carry names containing "BRAVO", so any leak shows up
 * as that word in school A's output.
 */
final class TenantIsolationTest extends TestCase
{
    private const PASSWORD = 'isolation-pass-1';

    /** Two fully set-up schools, each with a published run. */
    private function twoSchools(): array
    {
        $pdo = $this->migratedTestDb();
        $users = new UserRepository($pdo);
        $schools = new SchoolRepository($pdo);
        $out = ['pdo' => $pdo];
        foreach (['a' => 'ALPHA', 'b' => 'BRAVO'] as $slug => $marker) {
            $owner = $users->create("Owner {$marker}", "owner-{$slug}@test.example", self::PASSWORD, true);
            $id = $schools->create(['name' => "School {$marker}", 'slug' => "iso-{$slug}"], $owner);
            $ctx = SchoolContext::system($schools->find($id));
            $semester = (int) (new SemesterRepository($pdo, $ctx))->active()['id'];
            $ids = $this->fill($pdo, $ctx, $marker, $semester);
            $job = $this->publishedRun($pdo, $ctx, $semester);
            $calendar = new CalendarRepository($pdo, $ctx);
            $calendar->setDates($semester, '2026-01-12', '2026-04-24');
            $calendar->addClosure($semester, '2026-03-20', "Holiday {$marker}");
            $closure = (int) $calendar->closures($semester)[0]['id'];
            (new Audit($pdo))->log($owner, "test.{$slug}", null, null, ['marker' => $marker], '127.0.0.1', $ctx->id);
            $invite = (new InvitationRepository($pdo))->create($ctx, "invitee-{$marker}@test.example", 'officer', $owner);
            $out[$slug] = $ids + ['ctx' => $ctx, 'owner' => $owner, 'semester' => $semester, 'job' => $job, 'invite' => $invite, 'closure' => $closure];
        }
        return $out;
    }

    /** Same codes in both schools; names carry the marker. */
    private function fill(PDO $pdo, SchoolContext $ctx, string $marker, int $semester): array
    {
        $rooms = new TableRepository($pdo, $ctx, 'rooms');
        $ids = [
            'room' => $rooms->insert(['code' => 'R1', 'name' => "Room {$marker}", 'capacity' => 100, 'room_type' => 'lecture']),
            'lab' => $rooms->insert(['code' => 'LAB1', 'name' => "Lab {$marker}", 'capacity' => 60, 'room_type' => 'lab']),
            'group' => (new TableRepository($pdo, $ctx, 'student_groups'))->insert(['code' => '100L', 'name' => "Level {$marker}", 'size' => 50]),
            'lecturer' => (new TableRepository($pdo, $ctx, 'lecturers'))->insert(['staff_no' => 'S1', 'full_name' => "Lecturer {$marker}", 'short_name' => "L.{$marker}"]),
        ];
        $courses = new TableRepository($pdo, $ctx, 'courses');
        $ids['course'] = $courses->insert(['semester_id' => $semester, 'code' => 'C1', 'title' => "Course {$marker}", 'hours_per_week' => 2,
            'group_id' => $ids['group'], 'lecturer_id' => $ids['lecturer']]);
        $courses->insert(['semester_id' => $semester, 'code' => 'C2', 'title' => "Practical {$marker}", 'hours_per_week' => 2, 'needs_lab' => 1,
            'group_id' => $ids['group'], 'lecturer_id' => $ids['lecturer']]);
        $ids['slot'] = (int) (new TimeslotRepository($pdo, $ctx))->all()[3]['id'];
        (new AvailabilityService($pdo, $ctx))->set($ids['lecturer'], $ids['slot'], 'preferred');
        return $ids;
    }

    private function publishedRun(PDO $pdo, SchoolContext $ctx, int $semester): int
    {
        (new SessionBuilder($pdo, $ctx))->build($semester);
        (new FeasibilityService($pdo, $ctx))->run($semester, null);
        $jobs = new JobRepository($pdo, $ctx);
        $id = $jobs->createGaJob($semester, ['population_size' => 10, 'max_generations' => 5, 'stall_generations' => 5,
            'crossover_rate' => 0.8, 'mutation_rate' => 0.05, 'tournament_size' => 2, 'elite_count' => 1, 'greedy_share' => 0.5,
            'max_repair_moves' => 10, 'repair_enabled' => 1, 'random_seed' => 7, 'log_every' => 1], 'iso', null);
        $this->assertSame('finished', (new JobRunner($pdo, null, false))->claimAndRun($id, false));
        $jobs->publish($jobs->find($id));
        return $id;
    }

    public function testRepositoriesAndServicesNeverCrossSchools(): void
    {
        $t = $this->twoSchools();
        $pdo = $t['pdo'];
        $a = $t['a']['ctx'];
        $b = $t['b'];

        // Master data: lists, finds, updates and deletes by B's ids from A.
        foreach (['rooms' => 'room', 'student_groups' => 'group', 'lecturers' => 'lecturer', 'courses' => 'course'] as $table => $key) {
            $repo = new TableRepository($pdo, $a, $table);
            $rows = $repo->list("SELECT t.* FROM {$table} t", [], [], 't.id');
            $this->assertFalse(in_array($b[$key], array_map('intval', array_column($rows, 'id')), true), "{$table}: A's list holds B's row.");
            $this->assertSame(null, $repo->find($b[$key]), "{$table}: A found B's row by id.");
            $field = ['courses' => 'title', 'lecturers' => 'staff_no'][$table] ?? 'code';
            $this->assertFalse($repo->update($b[$key], [$field => 'HACK']), "{$table}: A updated B's row.");
            $this->assertFalse($repo->delete($b[$key]), "{$table}: A deleted B's row.");
            $this->assertTrue((new TableRepository($pdo, $b['ctx'], $table))->find($b[$key]) !== null, "{$table}: B's row must be untouched.");
            $this->assertFalse($repo->exists('id', (string) $b[$key], null), "{$table}: exists() saw B's row.");
        }
        $pairs = (new TableRepository($pdo, $a, 'lecturers'))->pairs('t.full_name', 't.id');
        $this->assertSame(['Lecturer ALPHA'], array_values($pairs));

        // Semesters, periods, weights, settings
        $semesters = new SemesterRepository($pdo, $a);
        $this->assertSame(null, $semesters->find($b['semester']));
        $this->assertFalse($semesters->activate($b['semester']));
        $this->assertSame(0, $semesters->copyCourses($b['semester'], (int) $semesters->active()['id']), 'Courses copied from another school.');
        $this->assertFalse((new TimeslotRepository($pdo, $a))->toggleBlocked($b['slot']));
        (new WeightRepository($pdo, $a))->set('S1', 77);
        $this->assertSame(5, (new WeightRepository($pdo, $b['ctx']))->map()['S1'], "A's weight change reached B.");
        (new SchoolSettingsRepository($pdo, $a))->set('ga_population', '33');
        $this->assertSame('100', (new SchoolSettingsRepository($pdo, $b['ctx']))->get('ga_population'));

        // Semester dates and holidays (monthly calendar view)
        $calendar = new CalendarRepository($pdo, $a);
        $this->assertSame([], $calendar->closures($b['semester']), "A read B's holidays.");
        $this->assertFalse($calendar->removeClosure($b['closure']), "A removed B's holiday.");
        $this->assertFalse($calendar->setDates($b['semester'], '2030-01-01', '2030-02-01'));
        $this->assertSame('2026-01-12', (string) $pdo->query("SELECT start_date FROM semesters WHERE id = {$b['semester']}")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM school_closures WHERE id = {$b['closure']}")->fetchColumn());

        // Availability: B's lecturer and B's period cannot be touched from A.
        $availability = new AvailabilityService($pdo, $a);
        $this->assertThrows(\InvalidArgumentException::class, fn () => $availability->cycle($b['lecturer'], $b['slot']));
        $this->assertSame([], $availability->forLecturer($b['lecturer']));
        $this->assertFalse(isset($availability->summary()[$b['lecturer']]));

        // Runs, progress, entries, the problem loader
        $jobs = new JobRepository($pdo, $a);
        $this->assertSame(null, $jobs->find($b['job']));
        $this->assertSame([], $jobs->progressAfter($b['job'], -1));
        $this->assertFalse(in_array($b['job'], array_map('intval', array_column($jobs->list(), 'id')), true));
        $jobs->requestStop($b['job']);
        $jobs->unpublish($b['job']);
        $bJob = (new JobRepository($pdo, $b['ctx']))->find($b['job']);
        $this->assertSame(0, (int) $bJob['stop_requested']);
        $this->assertSame(1, (int) $bJob['is_published'], "A unpublished B's timetable.");
        $this->assertSame([], (new TimetableService($pdo, $a))->entries($b['job']));
        $ctxA = (new ProblemLoader($pdo, $a))->load((int) $semesters->active()['id']);
        $bSessions = $pdo->query('SELECT id FROM class_sessions WHERE school_id = ' . $b['ctx']->id)->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([], array_values(array_intersect($ctxA->sessionIds, array_map('intval', $bSessions))));
        $this->assertFalse(in_array($b['room'], $ctxA->roomIds, true));
        $this->assertSame(null, (new JobQueue($pdo))->position($b['job']), 'A finished job has no queue position.');

        // CSV import upserts by natural key inside A only: B's room R1 keeps its name.
        $file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($file, "code,name,capacity,room_type\nR1,Renamed by A,90,lecture\n");
        $this->assertTrue((new CsvImporter($pdo, $a))->import('rooms', $file)['ok']);
        unlink($file);
        $this->assertSame('Room BRAVO', (new TableRepository($pdo, $b['ctx'], 'rooms'))->find($b['room'])['name']);

        // Members, invitations, activity log
        $members = new MemberRepository($pdo);
        $this->assertSame(null, $members->find($a, $b['owner']));
        $this->assertSame(null, $members->membership($b['owner'], $a->id));
        $members->setRole($a, $b['owner'], 'viewer');
        $members->remove($a, $b['owner']);
        $this->assertSame('owner', $members->membership($b['owner'], $b['ctx']->id)['role']);
        $invites = new InvitationRepository($pdo);
        $bInvite = $invites->findValid($b['invite']);
        $this->assertFalse($invites->revoke($a, (int) $bInvite['id']));
        $this->assertSame(['invitee-alpha@test.example'], array_column($invites->pending($a), 'email'));
        $log = (new AuditRepository($pdo, $a))->page('', 0, 1, 100)['rows'];
        $this->assertFalse(str_contains(json_encode($log), 'BRAVO'), "B's activity appears in A's log.");
        $this->dropAllTables($pdo);
    }

    public function testPagesApiExportAndPublicNeverCrossSchools(): void
    {
        $t = $this->twoSchools();
        $b = $t['b'];
        HttpClient::startServer();
        $http = new HttpClient();
        $http->login('owner-a@test.example', self::PASSWORD);

        // List and overview pages of A never show B's names (codes overlap on purpose).
        foreach (['dashboard', 'setup', 'rooms', 'groups', 'lecturers', 'courses', 'availability', 'sessions', 'feasibility', 'generate',
                     'timetables', 'published', 'members', 'rules', 'semesters', 'audit', 'school/profile', 'school/settings'] as $route) {
            $page = $http->get("/index.php?r={$route}");
            $this->assertSame(200, $page['status'], "{$route} should open.");
            $this->assertFalse(str_contains($page['body'], 'BRAVO'), "{$route} shows school B's data.");
        }
        $this->assertTrue(str_contains($http->get('/index.php?r=rooms')['body'], 'Room ALPHA'), "A's own data must show.");

        // Guessing B's ids: every record page answers 404, never 403 (its existence is not revealed).
        foreach (["rooms/edit&id={$b['room']}", "groups/edit&id={$b['group']}", "lecturers/edit&id={$b['lecturer']}",
                     "courses/edit&id={$b['course']}", "timetables/view&id={$b['job']}", "timetables/report&id={$b['job']}",
                     "timetables/export&id={$b['job']}", "timetables/print&id={$b['job']}", "generate/job&id={$b['job']}",
                     "timetables/export-dates&id={$b['job']}", "timetables/view&id={$b['job']}&mode=month", "semesters/calendar&id={$b['semester']}"] as $route) {
            $page = $http->get("/index.php?r={$route}");
            $this->assertSame(404, $page['status'], "{$route} must be 404.");
            $this->assertFalse(str_contains($page['body'], 'BRAVO'), "{$route} leaked B's data.");
        }
        $this->assertFalse(str_contains($http->get("/index.php?r=availability&lecturer={$b['lecturer']}")['body'], 'BRAVO'));
        $compare = $http->get("/index.php?r=timetables/compare&ids[]={$t['a']['job']}&ids[]={$b['job']}");
        $this->assertSame(303, $compare['status'], 'Comparing with a run of another school must not show it.');

        // Changes aimed at B's ids are refused and change nothing.
        foreach (["rooms/update" => ['id' => $b['room'], 'code' => 'HACK', 'name' => 'x', 'capacity' => 5, 'room_type' => 'lecture'],
                     "rooms/delete" => ['id' => $b['room']], "courses/delete" => ['id' => $b['course']],
                     "timetables/unpublish" => ['id' => $b['job']], "timetables/publish" => ['id' => $b['job']],
                     "generate/stop" => ['id' => $b['job']]] as $route => $fields) {
            $this->assertSame(404, $http->post("/index.php?r={$route}", $fields)['status'], "POST {$route} must be 404.");
        }
        $this->assertSame(303, $http->post('/index.php?r=periods/toggle', ['id' => $b['slot']])['status']);
        $this->assertSame(303, $http->post('/index.php?r=members/remove', ['user_id' => $b['owner']])['status']);
        $this->assertSame(404, $http->post('/index.php?r=schools/switch', ['school_id' => $b['ctx']->id])['status'], 'A joined B by switching.');
        $pdo = $t['pdo'];
        $this->assertSame('R1', (string) $pdo->query("SELECT code FROM rooms WHERE id = {$b['room']}")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT is_published FROM ga_jobs WHERE id = {$b['job']}")->fetchColumn());
        $this->assertSame(0, (int) $pdo->query("SELECT is_blocked FROM timeslots WHERE id = {$b['slot']}")->fetchColumn());
        $this->assertSame('owner', (string) $pdo->query("SELECT role FROM school_members WHERE user_id = {$b['owner']}")->fetchColumn());

        // API endpoints
        $progress = $http->get("/api/progress.php?job={$b['job']}&after=-1");
        $this->assertSame(404, $progress['status'], 'Progress of another school\'s run must be 404.');
        $own = $http->get("/api/progress.php?job={$t['a']['job']}&after=-1");
        $this->assertSame(200, $own['status']);
        $cell = $http->postApi('/api/availability.php', ['lecturer' => $b['lecturer'], 'timeslot' => $b['slot']]);
        $this->assertSame(422, $cell['status'], 'Availability of another school\'s lecturer must be refused.');

        // Public pages: by slug, published runs of that school only; ids of the other school are not found.
        $publicA = $http->get('/s/iso-a');
        $this->assertSame(200, $publicA['status']);
        $this->assertTrue(str_contains($publicA['body'], 'Level ALPHA') || str_contains($publicA['body'], '100L'));
        $this->assertFalse(str_contains($publicA['body'], 'BRAVO'));
        foreach (["/s/iso-a/room/{$b['room']}", "/s/iso-a/lecturer/{$b['lecturer']}", "/s/iso-a/level/{$b['group']}"] as $path) {
            $page = $http->get($path);
            $this->assertSame(404, $page['status'], "{$path} must be 404.");
            $this->assertFalse(str_contains($page['body'], 'BRAVO'), "{$path} leaked B's data.");
        }
        $this->assertTrue(str_contains($http->get('/s/iso-b')['body'], 'Lecturer BRAVO'), "B's own page shows B's lecturers.");
        $this->assertSame(200, $http->get("/s/iso-b/lecturer/{$b['lecturer']}")['status'], "B's lecturer grid opens on B's page.");
        $this->assertSame(404, $http->get('/s/does-not-exist')['status']);
        $monthA = $http->get("/index.php?r=timetables/view&id={$t['a']['job']}&mode=month&month=2026-03");
        $this->assertTrue(str_contains($monthA['body'], 'Holiday ALPHA'), "A's month shows A's holiday.");
        $this->assertFalse(str_contains($monthA['body'], 'BRAVO'), "A's month shows B's holiday.");
        $this->assertSame(303, $http->post('/index.php?r=semesters/closure-remove', ['id' => $t['a']['semester'], 'closure_id' => $b['closure']])['status']);
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM school_closures WHERE id = {$b['closure']}")->fetchColumn(), "A removed B's holiday over HTTP.");

        // A suspended school's public page is neutral and shows nothing.
        $pdo->exec("UPDATE schools SET status = 'suspended' WHERE id = {$b['ctx']->id}");
        $suspended = $http->get('/s/iso-b');
        $this->assertFalse(str_contains($suspended['body'], 'BRAVO'), 'A suspended school must not show its timetable.');
        $this->dropAllTables($pdo);
    }
}
