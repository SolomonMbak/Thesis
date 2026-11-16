<?php
declare(strict_types=1);

namespace Tests;

use App\Services\SessionBuilder;

final class SessionBuilderTest extends TestCase
{
    public function testLectureThreeHoursIsThreeOneHourSessions(): void
    {
        $this->assertSame([1, 1, 1], SessionBuilder::split(3, false, false));
    }

    public function testTwoHourBlockOption(): void
    {
        $this->assertSame([2, 1], SessionBuilder::split(3, false, true));
        $this->assertSame([2], SessionBuilder::split(2, false, true));
        $this->assertSame([1], SessionBuilder::split(1, false, true), 'One hour cannot form a 2-hour block.');
    }

    public function testLabThreeHoursIsOneBlock(): void
    {
        $this->assertSame([3], SessionBuilder::split(3, true, false));
        $this->assertSame([2], SessionBuilder::split(2, true, false));
    }

    public function testLabFourHoursSplitsIntoBlocksOfUpToThree(): void
    {
        $this->assertSame([3, 1], SessionBuilder::split(4, true, false));
        $this->assertSame([3, 3], SessionBuilder::split(6, true, true), 'The 2-hour option does not apply to practicals.');
    }

    public function testGeneIndexIsContiguousAndOrderedByCourse(): void
    {
        $pdo = $this->singleSchoolDb();
        $pdo->exec("INSERT INTO semesters (id, session_label, semester_no, is_active) VALUES (1, '2025/2026', 1, 1)");
        $pdo->exec("INSERT INTO student_groups (id, code, name, size) VALUES (1, '100L', 'L1', 100)");
        $pdo->exec("INSERT INTO lecturers (id, staff_no, full_name, short_name) VALUES (1, 'S1', 'Ada', 'A')");
        // Inserted out of id order on purpose: gene order must follow course id.
        $pdo->exec("INSERT INTO courses (id, semester_id, code, title, hours_per_week, needs_lab, allow_two_hour_block, group_id, lecturer_id) VALUES
            (20, 1, 'B', 'B', 4, 1, 0, 1, 1),
            (10, 1, 'A', 'A', 3, 0, 1, 1, 1)");

        $result = (new SessionBuilder($pdo, $this->school))->build(1);
        $this->assertSame(4, $result['created']);
        $rows = $pdo->query('SELECT course_id, session_no, duration, gene_index FROM class_sessions ORDER BY gene_index')->fetchAll();
        $this->assertSame([0, 1, 2, 3], array_map('intval', array_column($rows, 'gene_index')));
        $this->assertSame([10, 10, 20, 20], array_map('intval', array_column($rows, 'course_id')));
        $this->assertSame([2, 1, 3, 1], array_map('intval', array_column($rows, 'duration')));

        // Rebuilding with no saved timetables deletes and recreates.
        $again = (new SessionBuilder($pdo, $this->school))->build(1);
        $this->assertSame(4, $again['deleted']);
        $this->assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM class_sessions')->fetchColumn());
        $this->dropAllTables($pdo);
    }

    public function testRebuildArchivesSessionsUsedBySavedTimetables(): void
    {
        $pdo = $this->singleSchoolDb();
        $pdo->exec("INSERT INTO semesters (id, session_label, semester_no, is_active) VALUES (1, '2025/2026', 1, 1)");
        $pdo->exec("INSERT INTO student_groups (id, code, name, size) VALUES (1, '100L', 'L1', 100)");
        $pdo->exec("INSERT INTO lecturers (id, staff_no, full_name, short_name) VALUES (1, 'S1', 'Ada', 'A')");
        $pdo->exec("INSERT INTO rooms (id, code, name, capacity) VALUES (1, 'R1', 'R', 100)");
        $pdo->exec("INSERT INTO timeslots (id, day_of_week, period_no, start_time, end_time) VALUES (1, 1, 1, '08:00', '09:00')");
        $pdo->exec("INSERT INTO courses (id, semester_id, code, title, hours_per_week, group_id, lecturer_id) VALUES (1, 1, 'A', 'A', 2, 1, 1)");
        (new SessionBuilder($pdo, $this->school))->build(1);
        $pdo->exec("INSERT INTO ga_jobs (id, semester_id, status) VALUES (1, 1, 'finished')");
        $pdo->exec('INSERT INTO timetable_entries (job_id, session_id, timeslot_id, room_id) SELECT 1, id, 1, 1 FROM class_sessions LIMIT 1');

        $result = (new SessionBuilder($pdo, $this->school))->build(1);
        $this->assertSame(2, $result['archived']);
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM class_sessions WHERE archived = 0')->fetchColumn());
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM class_sessions WHERE archived = 1')->fetchColumn());
        $this->dropAllTables($pdo);
    }
}
