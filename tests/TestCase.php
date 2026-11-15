<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Core\Db;
use PDO;

final class AssertionFailed extends \RuntimeException
{
}

final class SkippedTest extends \RuntimeException
{
}

/**
 * Base class with the few assertions the tests need.
 */
abstract class TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    protected function assertTrue(mixed $value, string $message = 'Expected true'): void
    {
        if ($value !== true) {
            throw new AssertionFailed($message . ', got ' . var_export($value, true));
        }
    }

    protected function assertFalse(mixed $value, string $message = 'Expected false'): void
    {
        if ($value !== false) {
            throw new AssertionFailed($message . ', got ' . var_export($value, true));
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed(trim($message . ' Expected ' . var_export($expected, true)
                . ', got ' . var_export($actual, true)));
        }
    }

    protected function assertCount(int $expected, array $items, string $message = ''): void
    {
        $this->assertSame($expected, count($items), $message ?: 'Wrong number of items.');
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new AssertionFailed($message ?: "Missing key '{$key}' in " . json_encode($array));
        }
    }

    /** Assert that $fn throws an exception of the given class. */
    protected function assertThrows(string $class, callable $fn, string $message = ''): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $ex) {
            if ($ex instanceof $class) {
                return $ex;
            }
            throw new AssertionFailed("Expected {$class}, got " . get_class($ex) . ': ' . $ex->getMessage());
        }
        throw new AssertionFailed($message ?: "Expected {$class} to be thrown.");
    }

    protected function skip(string $reason): never
    {
        throw new SkippedTest($reason);
    }

    /** In-memory SQLite database for tests of portable SQL. */
    protected function sqlite(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->skip('pdo_sqlite extension not installed');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    /**
     * Connection to the MySQL test database named by test_db_name in
     * config.local.php. Every table is dropped first, so never point it at
     * real data. Skips the test when no test database is configured.
     */
    protected function mysqlTestDb(): PDO
    {
        $name = (string) Config::get('test_db_name', '');
        if ($name === '') {
            $this->skip('no test_db_name in config/config.local.php');
        }
        if ($name === Config::get('db_name')) {
            throw new \RuntimeException('test_db_name must differ from db_name: tests drop every table.');
        }
        try {
            $pdo = Db::connect([
                'host' => Config::get('db_host'),
                'port' => Config::get('db_port'),
                'name' => $name,
                'user' => Config::get('db_user'),
                'pass' => Config::get('db_pass'),
            ]);
        } catch (\PDOException $ex) {
            $this->skip('test database not reachable: ' . $ex->getMessage());
        }
        $this->dropAllTables($pdo);
        return $pdo;
    }

    /** MySQL test database with every migration applied and no data. */
    protected function migratedTestDb(): PDO
    {
        $pdo = $this->mysqlTestDb();
        (new \App\Core\Migrator($pdo, APP_ROOT . '/database/migrations'))->migrate();
        return $pdo;
    }

    /**
     * A small hand-built problem: $days days of $perDay periods, rooms and
     * sessions given as short arrays. Returns a ProblemContext so services
     * that need names can be tested without a database.
     *
     * $spec keys: days, perDay, blocked [p...], rooms [[cap, lab, active]...],
     * lecturers [['unavailable' => [...], 'preferred' => [...]]...], groups, courses,
     * sessions [[lecturer, group, course, size, lab, duration]...], weights
     */
    protected function context(array $spec): \App\Services\ProblemContext
    {
        $days = $spec['days'] ?? 1;
        $perDay = $spec['perDay'] ?? 10;
        $periods = [];
        $periodInfo = [];
        for ($d = 1; $d <= $days; $d++) {
            for ($n = 1; $n <= $perDay; $n++) {
                $p = count($periods);
                $blocked = in_array($p, $spec['blocked'] ?? [], true);
                $periods[] = ['day' => $d, 'blocked' => $blocked];
                $periodInfo[] = ['day' => $d, 'period_no' => $n, 'start' => sprintf('%02d:00', 7 + $n),
                    'end' => sprintf('%02d:00', 8 + $n), 'blocked' => $blocked];
            }
        }
        $rooms = array_map(fn ($r) => ['capacity' => $r[0], 'lab' => $r[1] ?? false, 'active' => $r[2] ?? true], $spec['rooms'] ?? [[100]]);
        $lecturers = $spec['lecturers'] ?? [[]];
        $sessions = array_map(fn ($s) => [
            'lecturer' => $s[0], 'group' => $s[1], 'course' => $s[2], 'size' => $s[3] ?? 50,
            'lab' => $s[4] ?? false, 'duration' => $s[5] ?? 1,
        ], $spec['sessions'] ?? []);
        $groups = $spec['groups'] ?? 1;
        $courses = $spec['courses'] ?? max(1, count($sessions));

        $data = \App\Engine\ProblemData::fromArrays([
            'periods' => $periods, 'rooms' => $rooms, 'lecturers' => $lecturers,
            'groups' => $groups, 'courses' => $courses, 'sessions' => $sessions,
            'weights' => $spec['weights'] ?? ['H' => 1000, 'S1' => 5, 'S2' => 3, 'S3' => 4, 'S4' => 3, 'S5' => 2],
        ]);
        $sessionInfo = [];
        foreach ($sessions as $i => $s) {
            $sessionInfo[] = ['course_id' => $s['course'] + 1, 'code' => 'C' . ($s['course'] + 1), 'title' => 'Course',
                'session_no' => $i + 1, 'duration' => $s['duration'], 'lab' => $s['lab'], 'size' => $s['size'],
                'group' => 'G' . ($s['group'] + 1), 'lecturer' => 'L' . ($s['lecturer'] + 1)];
        }
        return new \App\Services\ProblemContext(
            $data, range(1, max(1, count($sessions))), $sessionInfo,
            range(1, count($periods)), $periodInfo,
            range(1, count($rooms)), array_map(fn ($r, $i) => ['code' => 'R' . ($i + 1), 'name' => 'Room', 'capacity' => $r['capacity'],
                'lab' => $r['lab'], 'active' => $r['active']], $rooms, array_keys($rooms)),
            range(1, count($lecturers)), array_map(fn ($i) => 'Lecturer ' . ($i + 1), array_keys($lecturers)),
            range(1, $groups), array_map(fn ($i) => 'G' . ($i + 1), range(0, $groups - 1)),
            range(1, $courses), array_map(fn ($i) => 'C' . ($i + 1), range(0, $courses - 1)),
            $spec['weights'] ?? []
        );
    }

    protected function dropAllTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
