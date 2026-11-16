<?php
declare(strict_types=1);

namespace Tests;

use App\Data\JobQueue;
use App\Data\JobRepository;
use App\Services\JobRunner;
use App\Services\PeriodGenerator;
use App\Services\SessionBuilder;
use PDO;

/**
 * Job lifecycle against the MySQL test database: atomic claim, a full run
 * that saves entries and progress, stop requests and stale-job recovery.
 */
final class JobRunnerTest extends TestCase
{
    private function seededDb(): PDO
    {
        $pdo = $this->singleSchoolDb();
        (new PeriodGenerator($pdo, $this->school))->generate('08:00', '12:00', 60);   // 4 periods a day, 20 a week
        $pdo->exec("INSERT INTO semesters (id, session_label, semester_no, is_active) VALUES (1, '2025/2026', 1, 1)");
        $pdo->exec("INSERT INTO student_groups (id, code, name, size) VALUES (1, '100L', 'L1', 50), (2, '200L', 'L2', 40)");
        $pdo->exec("INSERT INTO lecturers (id, staff_no, full_name, short_name) VALUES (1, 'S1', 'Ada', 'A'), (2, 'S2', 'Bola', 'B')");
        $pdo->exec("INSERT INTO rooms (id, code, name, capacity, room_type) VALUES (1, 'R1', 'Room', 60, 'lecture'), (2, 'L1', 'Lab', 60, 'lab')");
        $pdo->exec("INSERT INTO courses (semester_id, code, title, hours_per_week, needs_lab, group_id, lecturer_id) VALUES
            (1, 'C1', 'One', 3, 0, 1, 1), (1, 'C2', 'Two', 2, 0, 2, 2), (1, 'C3', 'Lab', 2, 1, 1, 2)");
        (new SessionBuilder($pdo, $this->school))->build(1);
        return $pdo;
    }

    private function queueJob(PDO $pdo, array $overrides = []): int
    {
        return (new JobRepository($pdo, $this->school))->createGaJob(1, $overrides + [
            'population_size' => 20, 'max_generations' => 30, 'stall_generations' => 10, 'crossover_rate' => 0.8,
            'mutation_rate' => 0.05, 'tournament_size' => 3, 'elite_count' => 2, 'greedy_share' => 0.2,
            'max_repair_moves' => 30, 'repair_enabled' => 1, 'random_seed' => 5, 'log_every' => 1,
        ], 'test', null);
    }

    public function testClaimIsAtomic(): void
    {
        $pdo = $this->seededDb();
        $jobs = new JobRepository($pdo, $this->school);
        $id = $this->queueJob($pdo);
        $this->assertTrue((new JobQueue($pdo))->claim($id, 111, false));
        $this->assertFalse((new JobQueue($pdo))->claim($id, 222, false), 'A second claim must fail.');
        $this->assertSame(111, (int) $jobs->find($id)['worker_pid']);
        $this->dropAllTables($pdo);
    }

    public function testFullRunSavesTimetableAndSummary(): void
    {
        $pdo = $this->seededDb();
        $id = $this->queueJob($pdo);
        $status = (new JobRunner($pdo, null, false))->claimAndRun($id);
        $this->assertSame('finished', $status);

        $job = (new JobRepository($pdo, $this->school))->find($id);
        $this->assertSame(0, (int) $job['hard_violations']);
        $this->assertTrue($job['weights_json'] !== null, 'Weights are snapshotted.');
        $this->assertTrue((int) $job['runtime_ms'] >= 0 && $job['breakdown_json'] !== null);
        $sessions = (int) $pdo->query('SELECT COUNT(*) FROM class_sessions WHERE archived = 0')->fetchColumn();
        $this->assertSame($sessions, (int) $pdo->query("SELECT COUNT(*) FROM timetable_entries WHERE job_id = {$id}")->fetchColumn(),
            'Every session appears exactly once.');
        $this->assertSame((int) $job['generations_run'] + 1, (int) $pdo->query("SELECT COUNT(*) FROM ga_progress WHERE job_id = {$id}")->fetchColumn(),
            'One progress row per generation, plus generation 0.');
        $this->assertSame(null, (new JobRunner($pdo, null, false))->claimAndRun($id), 'A finished job cannot be run again.');
        $this->dropAllTables($pdo);
    }

    public function testStopRequestEndsRunAsStopped(): void
    {
        $pdo = $this->seededDb();
        $id = $this->queueJob($pdo, ['max_generations' => 500, 'stall_generations' => 500]);
        (new JobQueue($pdo))->claim($id, 1, false);
        $pdo->exec("UPDATE ga_jobs SET stop_requested = 1 WHERE id = {$id}");
        $this->assertSame('stopped', (new JobRunner($pdo, null, false))->run($id));
        $this->assertSame(10, (int) (new JobRepository($pdo, $this->school))->find($id)['generations_run']);
        $this->dropAllTables($pdo);
    }

    public function testStopBeforeStartMarksQueuedJobStopped(): void
    {
        $pdo = $this->seededDb();
        $jobs = new JobRepository($pdo, $this->school);
        $id = $this->queueJob($pdo);
        $jobs->requestStop($id);
        $this->assertSame('stopped', $jobs->find($id)['status']);
        $this->assertFalse((new JobQueue($pdo))->claim($id, 1, false));
        $this->dropAllTables($pdo);
    }

    public function testStaleRunningJobBecomesFailed(): void
    {
        $pdo = $this->seededDb();
        $jobs = new JobRepository($pdo, $this->school);
        $old = $this->queueJob($pdo);
        $fresh = $this->queueJob($pdo);
        (new JobQueue($pdo))->claim($old, 1, false);
        (new JobQueue($pdo))->claim($fresh, 2, false);
        $pdo->exec("UPDATE ga_jobs SET started_at = NOW() - INTERVAL 20 MINUTE WHERE id = {$old}");
        $pdo->exec("UPDATE ga_jobs SET started_at = NOW() - INTERVAL 20 MINUTE WHERE id = {$fresh}");
        // The fresh job logged progress 15 minutes into its run, i.e. 5 minutes ago.
        $pdo->exec("INSERT INTO ga_progress (job_id, generation, best_fitness, best_penalty, avg_penalty, hard_violations, soft_penalty, elapsed_ms)
                    VALUES ({$fresh}, 1, 0.1, 9, 9, 0, 9, 900000)");

        $this->assertSame(1, (new JobQueue($pdo))->recoverStale());
        $this->assertSame('failed', $jobs->find($old)['status']);
        $this->assertTrue(str_contains((string) $jobs->find($old)['notes'], 'Worker stopped unexpectedly'));
        $this->assertSame('running', $jobs->find($fresh)['status']);
        $this->dropAllTables($pdo);
    }

    public function testFailedRunRecordsTheReason(): void
    {
        $pdo = $this->seededDb();
        $id = $this->queueJob($pdo);
        $pdo->exec('UPDATE rooms SET is_active = 0');   // no rooms: the engine refuses to start
        $this->assertSame('failed', (new JobRunner($pdo, null, false))->claimAndRun($id));
        $this->assertTrue(str_contains((string) (new JobRepository($pdo, $this->school))->find($id)['notes'], 'no valid start or room'));
        $this->dropAllTables($pdo);
    }
}
