<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Migrator;
use PDO;

/**
 * The migration runner applies each file once, in order.
 * Portable checks run on SQLite with fixture files; the real 001_init.sql is
 * checked against MySQL when a test database is configured.
 */
final class MigratorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures/migrations';

    public function testAppliesPendingFilesInOrder(): void
    {
        $pdo = $this->sqlite();
        $applied = (new Migrator($pdo, self::FIXTURES))->migrate();
        $this->assertSame(['001_first.sql', '002_second.sql'], $applied);
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM fruit')->fetchColumn());
        $this->assertSame('pear', $pdo->query('SELECT name FROM fruit WHERE id = 2')->fetchColumn());
    }

    public function testSecondRunAppliesNothing(): void
    {
        $pdo = $this->sqlite();
        $migrator = new Migrator($pdo, self::FIXTURES);
        $migrator->migrate();
        $this->assertSame([], $migrator->migrate(), 'Nothing should be applied twice.');
        $this->assertSame([], $migrator->pending());
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM fruit')->fetchColumn(), 'Rows not inserted twice.');
        $this->assertSame(['001_first.sql', '002_second.sql'], $migrator->applied());
    }

    public function testOnlyNewFileIsAppliedLater(): void
    {
        $pdo = $this->sqlite();
        $dir = sys_get_temp_dir() . '/tt_migrations_' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            copy(self::FIXTURES . '/001_first.sql', $dir . '/001_first.sql');
            $migrator = new Migrator($pdo, $dir);
            $this->assertSame(['001_first.sql'], $migrator->migrate());

            copy(self::FIXTURES . '/002_second.sql', $dir . '/002_second.sql');
            $this->assertSame(['002_second.sql'], $migrator->pending());
            $this->assertSame(['002_second.sql'], $migrator->migrate());
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    public function testFailingFileIsNotRecordedAndNamesTheFile(): void
    {
        $pdo = $this->sqlite();
        $dir = sys_get_temp_dir() . '/tt_migrations_' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            file_put_contents($dir . '/001_bad.sql', "CREATE TABLE ok_table (id INT);\nTHIS IS NOT SQL;");
            $ex = $this->assertThrows(\RuntimeException::class, fn () => (new Migrator($pdo, $dir))->migrate());
            $this->assertTrue(str_contains($ex->getMessage(), '001_bad.sql'), 'Error must name the file.');
            $this->assertTrue(str_contains($ex->getMessage(), 'statement 2'), 'Error must name the statement.');
            $this->assertSame(['001_bad.sql'], (new Migrator($pdo, $dir))->pending(), 'Failed file stays pending.');
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    public function testSplitterIgnoresSemicolonsInQuotesAndComments(): void
    {
        $sql = "-- comment; with semicolon\n"
            . "CREATE TABLE a (x VARCHAR(10)); # another; comment\n"
            . "INSERT INTO a VALUES ('one;two'), ('it\\'s');\n"
            . "/* block; comment */ INSERT INTO a VALUES (\"q;q\");";
        $statements = Migrator::splitStatements($sql);
        $this->assertCount(3, $statements);
        $this->assertSame("INSERT INTO a VALUES ('one;two'), ('it\\'s')", $statements[1]);
    }

    public function testIgnoresFilesWithoutNumberPrefix(): void
    {
        $files = (new Migrator($this->sqlite(), self::FIXTURES))->files();
        $this->assertSame(['001_first.sql', '002_second.sql'], $files, 'README.txt and notes.sql are ignored.');
    }

    public function testRealInitialSchemaOnMysql(): void
    {
        $pdo = $this->mysqlTestDb();
        $migrator = new Migrator($pdo, APP_ROOT . '/database/migrations');
        $applied = $migrator->migrate();
        $this->assertTrue(in_array('001_init.sql', $applied, true));
        $this->assertSame([], $migrator->migrate(), 'Second run applies nothing.');

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(25, $tables, 'All 25 tables of BUILD_SPEC 4 (with 4.1 multi-school and the 10.7 calendar) should exist.');
        // A fresh install has no school yet, so no weights; each new school gets its own copy.
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM constraint_weights')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn());
        $this->assertTrue((bool) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'school_id' AND IS_NULLABLE = 'NO'")->fetchColumn(), 'courses.school_id is required.');
        $this->dropAllTables($pdo);
    }
}
