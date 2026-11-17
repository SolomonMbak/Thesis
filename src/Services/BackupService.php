<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;

/**
 * Database backups to storage/backups as gzip-compressed SQL.
 * Uses mysqldump when it can be run; otherwise a PHP export of every table.
 * Keeps the newest 10 backups.
 */
final class BackupService
{
    public const KEEP = 10;
    private const NAME_PATTERN = '/^backup_\d{8}_\d{6}\.sql\.gz$/';

    public function __construct(private PDO $pdo, private ?string $dir = null)
    {
        $this->dir ??= APP_ROOT . '/storage/backups';
    }

    /** @return array{file:string, method:string, bytes:int} */
    public function create(): array
    {
        $name = 'backup_' . date('Ymd_His') . '.sql.gz';
        $path = $this->dir . '/' . $name;
        $method = $this->mysqldump($path) ? 'mysqldump' : 'php';
        if ($method === 'php') {
            $this->phpExport($path);
        }
        $this->prune();
        return ['file' => $name, 'method' => $method, 'bytes' => (int) filesize($path)];
    }

    /** @return array<int, array{name:string, bytes:int, time:int}> newest first */
    public function list(): array
    {
        $files = [];
        foreach (glob($this->dir . '/backup_*.sql.gz') ?: [] as $path) {
            if (preg_match(self::NAME_PATTERN, basename($path))) {
                $files[] = ['name' => basename($path), 'bytes' => (int) filesize($path), 'time' => (int) filemtime($path)];
            }
        }
        usort($files, fn ($a, $b) => strcmp($b['name'], $a['name']));
        return $files;
    }

    /** Full path of a backup, or null if the name is not one of ours. Blocks path tricks. */
    public function path(string $name): ?string
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $path = $this->dir . '/' . $name;
        return is_file($path) ? $path : null;
    }

    public function prune(): void
    {
        foreach (array_slice($this->list(), self::KEEP) as $old) {
            @unlink($this->dir . '/' . $old['name']);
        }
    }

    /**
     * Try mysqldump. The database password is never on the command line or in
     * the environment (both can be read by other processes): it goes in a
     * temporary option file only the app's own user can read, deleted at once.
     * --no-tablespaces lets a database user with only this database's
     * privileges make the dump (MySQL 8 otherwise demands PROCESS).
     */
    private function mysqldump(string $gzPath): bool
    {
        if (!Environment::canExec() || !function_exists('proc_open')) {
            return false;
        }
        $binary = self::findMysqldump();
        if ($binary === null) {
            return false;
        }
        $options = $this->writeOptionFile();
        if ($options === null) {
            return false;
        }
        try {
            return $this->runMysqldump($binary, $options, $gzPath);
        } finally {
            @unlink($options);
        }
    }

    /** A [client] option file with the connection details, readable by the owner only. Null if it cannot be written. */
    private function writeOptionFile(): ?string
    {
        $quote = fn (string $v): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
        $content = "[client]\n"
            . 'host=' . $quote((string) Config::get('db_host')) . "\n"
            . 'port=' . (int) Config::get('db_port') . "\n"
            . 'user=' . $quote((string) Config::get('db_user')) . "\n"
            . 'password=' . $quote((string) Config::get('db_pass')) . "\n";
        $dir = APP_ROOT . '/storage/cache';
        $file = $dir . '/mysqldump_' . bin2hex(random_bytes(8)) . '.cnf';
        $old = umask(0077);
        try {
            $written = @file_put_contents($file, $content, LOCK_EX);
        } finally {
            umask($old);
        }
        if ($written === false) {
            return null;
        }
        @chmod($file, 0600);
        return $file;
    }

    private function runMysqldump(string $binary, string $optionFile, string $gzPath): bool
    {
        // --defaults-extra-file must be the first option.
        $cmd = [$binary, '--defaults-extra-file=' . $optionFile, '--single-transaction', '--no-tablespaces', '--routines',
            '--default-character-set=utf8mb4', (string) Config::get('db_name')];
        $process = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        $gz = gzopen($gzPath, 'wb6');
        $bytes = 0;
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                $bytes += strlen($chunk);
                gzwrite($gz, $chunk);
            }
        }
        gzclose($gz);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $ok = proc_close($process) === 0 && $bytes > 0;
        if (!$ok) {
            @unlink($gzPath);
        }
        return $ok;
    }

    public static function findMysqldump(): ?string
    {
        $candidates = ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/local/mysql/bin/mysqldump',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe'];
        foreach ($candidates as $candidate) {
            if (@is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /** Write CREATE TABLE and INSERT statements for every table. */
    public function phpExport(string $gzPath): void
    {
        $gz = gzopen($gzPath, 'wb6');
        $write = fn (string $s) => gzwrite($gz, $s);
        $write("-- Timetable system backup (PHP export) " . date('c') . "\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $quoted = '`' . str_replace('`', '``', $table) . '`';
            $create = $this->pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM)[1];
            $write("DROP TABLE IF EXISTS {$quoted};\n{$create};\n\n");
            $stmt = $this->pdo->query("SELECT * FROM {$quoted}");
            $batch = [];
            while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
                $batch[] = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $this->pdo->quote((string) $v), $row)) . ')';
                if (count($batch) >= 200) {
                    $write("INSERT INTO {$quoted} VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                $write("INSERT INTO {$quoted} VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            $write("\n");
        }
        $write("SET FOREIGN_KEY_CHECKS = 1;\n");
        gzclose($gz);
    }
}
