<?php
declare(strict_types=1);

namespace Tests;

use App\Data\JobRepository;
use App\Services\JobRunner;
use App\Services\ManualTimetableImporter;
use App\Services\PeriodGenerator;
use App\Services\SessionBuilder;
use App\Services\TimetableService;
use PDO;

/**
 * Grids (rowspan, clashes, each session once), quality report and manual
 * timetable import and scoring.
 */
final class TimetableTest extends TestCase
{
    private array $files = [];

    public function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
    }

    private function db(): PDO
    {
        $pdo = $this->singleSchoolDb();
        (new PeriodGenerator($pdo, $this->school))->generate('08:00', '12:00', 60);
        $pdo->exec("INSERT INTO semesters (id, session_label, semester_no, is_active) VALUES (1, '2025/2026', 1, 1)");
        $pdo->exec("INSERT INTO student_groups (id, code, name, size) VALUES (1, '100L', 'L1', 50)");
        $pdo->exec("INSERT INTO lecturers (id, staff_no, full_name, short_name) VALUES (1, 'S1', 'Ada', 'A'), (2, 'S2', 'Bola', 'B')");
        $pdo->exec("INSERT INTO rooms (id, code, name, capacity, room_type) VALUES (1, 'R1', 'Room', 60, 'lecture'), (2, 'LAB', 'Lab', 60, 'lab')");
        $pdo->exec("INSERT INTO courses (id, semester_id, code, title, hours_per_week, needs_lab, group_id, lecturer_id) VALUES
            (1, 1, 'CSC 101', 'One', 2, 0, 1, 1), (2, 1, 'CSC 102', 'Lab', 3, 1, 1, 2)");
        (new SessionBuilder($pdo, $this->school))->build(1);   // CSC 101: 1 + 1, CSC 102: one 3-hour block
        return $pdo;
    }

    private function csv(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'man');
        file_put_contents($file, $content);
        $this->files[] = $file;
        return $file;
    }

    public function testManualImportScoresClashes(): void
    {
        $pdo = $this->db();
        // CSC 101 session 2 at Mon 09:00 overlaps the lab block (Mon 08:00 to 11:00) for the same group.
        $file = $this->csv("course_code,session_no,day,start_time,room_code\n"
            . "CSC 101,1,Tue,08:00,R1\ncsc101,2,Mon,09:00,R1\nCSC 102,1,Monday,8:00,LAB\n");
        $result = (new ManualTimetableImporter($pdo, $this->school))->import($file, 1, 'Manual', null);
        $this->assertTrue($result['ok'], json_encode($result['errors']));
        $this->assertSame(1, $result['hard'], 'Group 100L has two classes at Mon 09:00 (H3 = 1).');

        $job = (new JobRepository($pdo, $this->school))->find($result['job_id']);
        $this->assertSame('manual', $job['job_type']);
        $breakdown = json_decode($job['breakdown_json'], true);
        $this->assertSame(1, $breakdown['H3']);

        $report = (new TimetableService($pdo, $this->school))->qualityReport($job);
        $h3 = array_values(array_filter($report['items'], fn ($i) => $i['code'] === 'H3'));
        $this->assertCount(1, $h3);
        $this->assertTrue(str_contains($h3[0]['text'], 'Level 100L has 2 classes at Mon 09:00'), $h3[0]['text']);
        $this->dropAllTables($pdo);
    }

    public function testManualImportReportsUnmatchedRowsAndMissingSessions(): void
    {
        $pdo = $this->db();
        $file = $this->csv("course_code,session_no,day,start_time,room_code\n"
            . "CSC 101,1,Tue,08:00,R1\nCSC 101,1,Wed,08:00,R1\nCSC 999,1,Mon,08:00,R1\nCSC 102,1,Sat,08:00,NOPE\n");
        $result = (new ManualTimetableImporter($pdo, $this->school))->import($file, 1, 'Manual', null);
        $this->assertFalse($result['ok']);
        $text = implode("\n", $result['errors']);
        $this->assertTrue(str_contains($text, 'Row 3: session already given on row 2'), $text);
        $this->assertTrue(str_contains($text, "Row 4: no session 1 of course 'CSC 999'"), $text);
        $this->assertTrue(str_contains($text, 'Row 5:') && str_contains($text, "room 'NOPE' does not exist"), $text);
        $this->assertTrue(str_contains($text, 'Session 2 of CSC101 has no row'), $text);
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM ga_jobs')->fetchColumn(), 'Nothing saved.');
        $this->dropAllTables($pdo);
    }

    public function testGridShowsEachSessionOnceWithRowspanAndClash(): void
    {
        $pdo = $this->db();
        $file = $this->csv("course_code,session_no,day,start_time,room_code\n"
            . "CSC 101,1,Tue,08:00,R1\nCSC 101,2,Mon,09:00,R1\nCSC 102,1,Mon,08:00,LAB\n");
        $jobId = (new ManualTimetableImporter($pdo, $this->school))->import($file, 1, 'Manual', null)['job_id'];
        $service = new TimetableService($pdo, $this->school);
        $entries = $service->entries($jobId);
        $layout = TimetableService::layout($service->grid($entries, 'level', 1));

        // Monday (day 1): the lab block clashes at 09:00, so it cannot be one tall cell.
        $this->assertSame(1, $layout[1][1]['rowspan']);
        $this->assertTrue($layout[2][1]['clash']);
        $this->assertCount(2, $layout[2][1]['items']);
        $this->assertFalse($layout[3][1]['clash']);

        // Lab room view: the block is alone there, so it spans 3 rows.
        $labLayout = TimetableService::layout($service->grid($entries, 'room', 2));
        $this->assertSame(3, $labLayout[1][1]['rowspan']);
        $this->assertTrue($labLayout[2][1]['skip'] && $labLayout[3][1]['skip']);

        // Each session starts exactly once in the level grid.
        $starts = 0;
        foreach ($layout as $row) {
            foreach ($row as $cell) {
                foreach ($cell['items'] ?? [] as $item) {
                    $starts += $item['start'] ? 1 : 0;
                }
            }
        }
        $this->assertSame(3, $starts);
        $this->dropAllTables($pdo);
    }

    public function testReportOfGeneratedRunMatchesItsSavedScore(): void
    {
        $pdo = $this->db();
        $id = (new JobRepository($pdo, $this->school))->createGaJob(1, [
            'population_size' => 10, 'max_generations' => 20, 'stall_generations' => 5, 'crossover_rate' => 0.8,
            'mutation_rate' => 0.05, 'tournament_size' => 3, 'elite_count' => 1, 'greedy_share' => 0.5,
            'max_repair_moves' => 30, 'repair_enabled' => 1, 'random_seed' => 3, 'log_every' => 1,
        ], null, null);
        (new JobRunner($pdo, null, false))->claimAndRun($id);
        $job = (new JobRepository($pdo, $this->school))->find($id);
        $report = (new TimetableService($pdo, $this->school))->qualityReport($job);
        $this->assertSame((int) $job['best_penalty'], $report['evaluation']->penalty, 'Re-scoring the saved timetable gives the same penalty.');
        $this->dropAllTables($pdo);
    }
}
