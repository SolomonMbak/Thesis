<?php
declare(strict_types=1);

namespace Tests;

use App\Core\SchoolContext;
use App\Data\JobQueue;
use App\Data\JobRepository;
use App\Data\SchoolRepository;
use App\Data\SchoolSettingsRepository;
use App\Data\SemesterRepository;
use App\Data\SettingsRepository;
use App\Data\TableRepository;
use App\Data\UserRepository;
use App\Services\FeasibilityService;
use App\Services\SessionBuilder;
use App\Services\SetupGuide;

/**
 * Setup Guide steps follow the data (BUILD_SPEC 10.2), sessions rebuild
 * automatically, and the run queue is fair across schools (BUILD_SPEC 9).
 */
final class SetupGuideTest extends TestCase
{
    private function newSchool(\PDO $pdo, string $slug): SchoolContext
    {
        $owner = (new UserRepository($pdo))->create('Owner', "{$slug}@test.example", 'password-12345', true);
        $repo = new SchoolRepository($pdo);
        return SchoolContext::system($repo->find($repo->create(['name' => "School {$slug}", 'slug' => $slug], $owner)));
    }

    private function status(SetupGuide $guide): array
    {
        return array_map(fn ($s) => $s['status'], $guide->steps());
    }

    public function testStepsTurnDoneFromTheDataAndLockUntilReady(): void
    {
        $pdo = $this->migratedTestDb();
        $school = $this->newSchool($pdo, 'guide');
        $guide = fn () => new SetupGuide($pdo, $school);

        $this->assertSame([1 => 'todo', 2 => 'locked', 3 => 'locked', 4 => 'locked', 5 => 'locked', 6 => 'locked', 7 => 'locked',
            8 => 'locked', 9 => 'locked'], $this->status($guide()));
        (new SchoolSettingsRepository($pdo, $school))->set('profile_saved_at', date('Y-m-d H:i:s'));
        $this->assertSame('done', $guide()->steps()[1]['status']);
        $this->assertSame('todo', $guide()->steps()[2]['status']);

        $semester = (int) (new SemesterRepository($pdo, $school))->active()['id'];
        (new TableRepository($pdo, $school, 'rooms'))->insert(['code' => 'R1', 'name' => 'Room', 'capacity' => 100, 'room_type' => 'lecture']);
        $group = (new TableRepository($pdo, $school, 'student_groups'))->insert(['code' => '100L', 'name' => 'L1', 'size' => 50]);
        $lecturer = (new TableRepository($pdo, $school, 'lecturers'))->insert(['staff_no' => 'S1', 'full_name' => 'Ada', 'short_name' => 'A']);
        $steps = $guide()->steps();
        $this->assertSame(['done', 'done', 'done', 'todo', 'locked'], [$steps[2]['status'], $steps[3]['status'], $steps[4]['status'],
            $steps[5]['status'], $steps[7]['status']]);
        $this->assertSame('optional', $steps[6]['status'], 'Availability is optional.');

        $courses = new TableRepository($pdo, $school, 'courses');
        $course = $courses->insert(['semester_id' => $semester, 'code' => 'C1', 'title' => 'One', 'hours_per_week' => 2,
            'group_id' => $group, 'lecturer_id' => $lecturer]);
        $this->assertSame('todo', $guide()->steps()[7]['status'], 'Check readiness unlocks once rooms and courses exist.');

        // Readiness: passing counts only while the data stays as it was checked.
        $this->assertTrue((new FeasibilityService($pdo, $school))->run($semester, null)['ok']);
        $this->assertSame('done', $guide()->steps()[7]['status']);
        $courses->update($course, ['hours_per_week' => 3]);
        $this->assertSame('todo', $guide()->steps()[7]['status'], 'A change after the check makes the step To do again.');
        $this->assertFalse($guide()->isComplete());
        $this->assertSame(5, $guide()->doneCount(), 'Steps 1 to 5 are done.');
        $this->assertSame(7, $guide()->next()['no']);
        $this->dropAllTables($pdo);
    }

    public function testSessionsFollowTheCoursesAutomatically(): void
    {
        $pdo = $this->migratedTestDb();
        $school = $this->newSchool($pdo, 'auto');
        $semester = (int) (new SemesterRepository($pdo, $school))->active()['id'];
        $group = (new TableRepository($pdo, $school, 'student_groups'))->insert(['code' => '100L', 'name' => 'L1', 'size' => 50]);
        $lecturer = (new TableRepository($pdo, $school, 'lecturers'))->insert(['staff_no' => 'S1', 'full_name' => 'Ada', 'short_name' => 'A']);
        $courses = new TableRepository($pdo, $school, 'courses');
        $course = $courses->insert(['semester_id' => $semester, 'code' => 'C1', 'title' => 'One', 'hours_per_week' => 3,
            'group_id' => $group, 'lecturer_id' => $lecturer]);
        $builder = new SessionBuilder($pdo, $school);
        $this->assertSame(3, $builder->ensureCurrent($semester)['created']);
        $this->assertSame(null, $builder->ensureCurrent($semester), 'Nothing changed, nothing rebuilt.');
        $courses->update($course, ['allow_two_hour_block' => 1]);
        $this->assertSame(2, $builder->ensureCurrent($semester)['created'], 'A 2-hour block plus one hour.');
        $this->dropAllTables($pdo);
    }

    public function testQueueRespectsTheConcurrencyCapAcrossSchools(): void
    {
        $pdo = $this->migratedTestDb();
        (new SettingsRepository($pdo))->set('max_concurrent_jobs', '1');
        $params = ['population_size' => 10, 'max_generations' => 5, 'stall_generations' => 5, 'crossover_rate' => 0.8, 'mutation_rate' => 0.05,
            'tournament_size' => 2, 'elite_count' => 1, 'greedy_share' => 0.2, 'max_repair_moves' => 10, 'repair_enabled' => 1, 'random_seed' => 1, 'log_every' => 1];
        $ids = [];
        foreach (['q1', 'q2'] as $slug) {
            $school = $this->newSchool($pdo, $slug);
            $semester = (int) (new SemesterRepository($pdo, $school))->active()['id'];
            $ids[] = (new JobRepository($pdo, $school))->createGaJob($semester, $params, null, null);
        }
        $queue = new JobQueue($pdo);
        $this->assertSame($ids[0], $queue->oldestQueuedId(), 'Oldest first, whichever school.');
        $this->assertSame(2, $queue->position($ids[1]), 'The second school\'s run is number 2 in the queue.');
        $this->assertTrue($queue->claim($ids[0], 1));
        $this->assertFalse($queue->claim($ids[1], 2), 'The cap of 1 running job holds across schools.');
        $this->assertSame(1, $queue->position($ids[1]));
        (new SettingsRepository($pdo))->set('max_concurrent_jobs', '2');
        $this->assertTrue($queue->claim($ids[1], 2));
        $this->dropAllTables($pdo);
    }

    public function testDeletingASchoolRemovesAllItsDataOnly(): void
    {
        $pdo = $this->migratedTestDb();
        $keep = $this->newSchool($pdo, 'keep');
        $gone = $this->newSchool($pdo, 'gone');
        (new TableRepository($pdo, $gone, 'rooms'))->insert(['code' => 'R1', 'name' => 'Room', 'capacity' => 10, 'room_type' => 'lecture']);
        (new SchoolRepository($pdo))->delete($gone->id);
        foreach (['timeslots', 'rooms', 'semesters', 'constraint_weights', 'school_settings', 'school_members'] as $table) {
            $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE school_id = {$gone->id}")->fetchColumn(), "{$table} left behind.");
        }
        $this->assertSame(50, (int) $pdo->query("SELECT COUNT(*) FROM timeslots WHERE school_id = {$keep->id}")->fetchColumn());
        $this->dropAllTables($pdo);
    }
}
