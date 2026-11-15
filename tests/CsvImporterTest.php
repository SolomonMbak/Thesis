<?php
declare(strict_types=1);

namespace Tests;

use App\Services\CsvImporter;
use PDO;

final class CsvImporterTest extends TestCase
{
    private array $files = [];

    public function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
    }

    private function csv(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($file, $content);
        $this->files[] = $file;
        return $file;
    }

    public function testReadStripsBomAndSkipsBlankLines(): void
    {
        $read = CsvImporter::read($this->csv("\xEF\xBB\xBFcode,name,size\n100L,Level 1,120\n\n200L,Level 2,110\n"), ['code', 'name', 'size']);
        $this->assertSame([], $read['errors']);
        $this->assertSame([2, 4], array_keys($read['rows']), 'Rows are keyed by their line number in the file.');
        $this->assertSame('100L', $read['rows'][2]['code']);
    }

    public function testReadReportsMissingAndUnknownColumns(): void
    {
        $read = CsvImporter::read($this->csv("code,nmae,size\n100L,x,1\n"), ['code', 'name', 'size']);
        $this->assertCount(2, $read['errors']);
        $this->assertTrue(str_contains($read['errors'][0], 'name'));
    }

    public function testAllOrNothingWithRowNumbers(): void
    {
        $pdo = $this->migratedTestDb();
        $file = $this->csv("code,name,capacity,room_type\nLR1,Room 1,60,lecture\nLR2,Room 2,abc,lecture\nLAB1,Lab,40,kitchen\nLR1,Again,70,lecture\n");
        $result = (new CsvImporter($pdo))->import('rooms', $file);

        $this->assertFalse($result['ok']);
        $this->assertCount(3, $result['errors'], 'Rows 3, 4 and 5 each have a problem: ' . json_encode($result['errors']));
        $this->assertTrue(str_starts_with($result['errors'][0], 'Row 3:'));
        $this->assertTrue(str_contains($result['errors'][0], 'capacity'));
        $this->assertTrue(str_starts_with($result['errors'][1], 'Row 4:'));
        $this->assertTrue(str_contains($result['errors'][2], 'duplicate of row 2'));
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn(), 'Valid row 2 must not be saved either.');
        $this->dropAllTables($pdo);
    }

    public function testImportsAndUpsertsByNaturalKey(): void
    {
        $pdo = $this->migratedTestDb();
        $importer = new CsvImporter($pdo);
        $first = $importer->import('groups', $this->csv("code,name,size\n100L,Level 1,120\n200L,Level 2,110\n"));
        $this->assertTrue($first['ok']);
        $this->assertSame(2, $first['inserted']);

        $second = $importer->import('groups', $this->csv("code,name,size\n100L,Level One,125\n300L,Level 3,90\n"));
        $this->assertSame(1, $second['inserted']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(125, (int) $pdo->query("SELECT size FROM student_groups WHERE code = '100L'")->fetchColumn());
        $this->dropAllTables($pdo);
    }

    public function testCoursesResolveGroupAndLecturer(): void
    {
        $pdo = $this->migratedTestDb();
        $pdo->exec("INSERT INTO semesters (id, session_label, semester_no, is_active) VALUES (1, '2025/2026', 1, 1)");
        $importer = new CsvImporter($pdo);
        $importer->import('groups', $this->csv("code,name,size\n100L,Level 1,120\n"));
        $importer->import('lecturers', $this->csv("staff_no,title,full_name,short_name,email\nS1,Dr.,Ada Bello,A. Bello,\n"));

        $header = "code,title,credit_units,hours_per_week,needs_lab,allow_two_hour_block,class_size_override,group_code,lecturer_staff_no\n";
        $bad = $importer->import('courses', $this->csv($header . "CSC 101,Intro,3,3,0,1,,100L,S9\n"), 1);
        $this->assertFalse($bad['ok']);
        $this->assertTrue(str_contains($bad['errors'][0], "lecturer_staff_no 'S9' does not exist"));

        $good = $importer->import('courses', $this->csv($header . "CSC 101,Intro,3,3,0,1,,100L,s1\nCSC 102,Lab,2,3,yes,0,60,100l,S1\n"), 1);
        $this->assertTrue($good['ok'], json_encode($good['errors']));
        $row = $pdo->query("SELECT needs_lab, class_size_override FROM courses WHERE code = 'CSC 102'")->fetch(PDO::FETCH_NUM);
        $this->assertSame([1, 60], array_map('intval', $row));
        $this->assertSame(null, $pdo->query("SELECT class_size_override FROM courses WHERE code = 'CSC 101'")->fetchColumn());
        $this->dropAllTables($pdo);
    }

    public function testAvailabilityMatchesPeriodByDayAndTime(): void
    {
        $pdo = $this->migratedTestDb();
        $pdo->exec("INSERT INTO lecturers (id, staff_no, full_name, short_name) VALUES (1, 'S1', 'Ada', 'A')");
        $pdo->exec("INSERT INTO timeslots (id, day_of_week, period_no, start_time, end_time) VALUES (7, 1, 1, '08:00', '09:00')");
        $importer = new CsvImporter($pdo);
        $bad = $importer->import('availability', $this->csv("staff_no,day,start_time,status\nS1,Mon,09:00,unavailable\nS1,Sun,08:00,busy\n"));
        $this->assertCount(2, $bad['errors']);
        $good = $importer->import('availability', $this->csv("staff_no,day,start_time,status\nS1,Monday,8:00,unavailable\n"));
        $this->assertTrue($good['ok'], json_encode($good['errors']));
        $this->assertSame('unavailable', $pdo->query('SELECT status FROM lecturer_availability WHERE timeslot_id = 7')->fetchColumn());
        $this->dropAllTables($pdo);
    }
}
