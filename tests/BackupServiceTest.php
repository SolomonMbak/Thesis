<?php
declare(strict_types=1);

namespace Tests;

use App\Services\BackupService;

final class BackupServiceTest extends TestCase
{
    private string $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/tt_backups_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    public function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testPathRejectsAnythingButBackupNames(): void
    {
        touch($this->dir . '/backup_20260101_120000.sql.gz');
        $service = new BackupService($this->sqlite(), $this->dir);
        $this->assertTrue($service->path('backup_20260101_120000.sql.gz') !== null);
        $this->assertSame(null, $service->path('../config/config.local.php'));
        $this->assertSame(null, $service->path('backup_20260101_120000.sql.gz/../../x'));
        $this->assertSame(null, $service->path('backup_20990101_120000.sql.gz'), 'Missing file.');
    }

    public function testPruneKeepsTheNewestTen(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            touch(sprintf('%s/backup_202601%02d_120000.sql.gz', $this->dir, $i));
        }
        $service = new BackupService($this->sqlite(), $this->dir);
        $service->prune();
        $names = array_column($service->list(), 'name');
        $this->assertCount(10, $names);
        $this->assertSame('backup_20260113_120000.sql.gz', $names[0]);
        $this->assertFalse(in_array('backup_20260103_120000.sql.gz', $names, true));
    }

    public function testPhpExportRestoresIntoAnEmptyDatabase(): void
    {
        $pdo = $this->singleSchoolDb();
        $pdo->exec("INSERT INTO lecturers (staff_no, full_name, short_name) VALUES ('S1', 'O''Brien; Ada', 'A')");
        $file = $this->dir . '/backup_20260101_000000.sql.gz';
        (new BackupService($pdo, $this->dir))->phpExport($file);

        $this->dropAllTables($pdo);
        foreach (\App\Core\Migrator::splitStatements((string) gzdecode((string) file_get_contents($file))) as $sql) {
            $pdo->exec($sql);
        }
        $this->assertSame("O'Brien; Ada", $pdo->query('SELECT full_name FROM lecturers')->fetchColumn());
        $this->assertSame(6, (int) $pdo->query('SELECT COUNT(*) FROM constraint_weights')->fetchColumn());
        $this->dropAllTables($pdo);
    }
}
