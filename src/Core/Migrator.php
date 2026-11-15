<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Applies database/migrations/NNN_name.sql files in filename order and
 * records each one in schema_migrations, so every file runs exactly once.
 *
 * Used by bin/migrate.php, the installer and the health page.
 * Applied files must never be edited; add a new numbered file instead.
 */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir)
    {
    }

    /** @return string[] filenames not yet applied, in order */
    public function pending(): array
    {
        $this->ensureTable();
        $applied = array_flip($this->applied());
        return array_values(array_filter($this->files(), fn ($f) => !isset($applied[$f])));
    }

    /** @return string[] filenames already recorded */
    public function applied(): array
    {
        $this->ensureTable();
        return $this->pdo->query('SELECT filename FROM schema_migrations ORDER BY filename')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Apply all pending files. Returns the filenames applied.
     * Stops at the first failing statement and names the file in the error.
     * (MySQL commits each CREATE TABLE immediately, so a file is recorded
     * only after all its statements have succeeded.)
     */
    public function migrate(?callable $onApplied = null): array
    {
        $done = [];
        foreach ($this->pending() as $file) {
            $sql = (string) file_get_contents($this->dir . '/' . $file);
            foreach (self::splitStatements($sql) as $i => $statement) {
                try {
                    $this->pdo->exec($statement);
                } catch (\PDOException $ex) {
                    throw new \RuntimeException(
                        "Migration {$file} failed at statement " . ($i + 1) . ': ' . $ex->getMessage(),
                        0,
                        $ex
                    );
                }
            }
            $this->pdo->prepare('INSERT INTO schema_migrations (filename, applied_at) VALUES (?, ?)')
                ->execute([$file, date('Y-m-d H:i:s')]);
            $done[] = $file;
            if ($onApplied) {
                $onApplied($file);
            }
        }
        return $done;
    }

    /** @return string[] migration filenames on disk, sorted */
    public function files(): array
    {
        $files = array_map('basename', glob($this->dir . '/*.sql') ?: []);
        $files = array_values(array_filter($files, fn ($f) => preg_match('/^\d{3}_[a-z0-9_]+\.sql$/', $f)));
        sort($files, SORT_STRING);
        return $files;
    }

    private function ensureTable(): void
    {
        $engine = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' ENGINE=InnoDB' : '';
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
               filename VARCHAR(100) PRIMARY KEY,
               applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
             )' . $engine
        );
    }

    /**
     * Split a SQL file into statements on semicolons, ignoring semicolons
     * inside quotes, and dropping -- , # and block comments.
     *
     * @return string[]
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $current .= $ch;
                if ($ch === '\\' && $quote !== '`') {
                    $current .= $next;
                    $i++;
                } elseif ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === '\'' || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $current .= $ch;
            } elseif (($ch === '-' && $next === '-') || $ch === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end - 1;
            } elseif ($ch === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
            } elseif ($ch === ';') {
                $statements[] = trim($current);
                $current = '';
            } else {
                $current .= $ch;
            }
        }
        $statements[] = trim($current);
        return array_values(array_filter($statements, fn ($s) => $s !== ''));
    }
}
