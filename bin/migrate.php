<?php
/**
 * Apply pending database migrations.
 *
 *   php bin/migrate.php           apply everything not yet applied
 *   php bin/migrate.php --status  list applied and pending files
 */
declare(strict_types=1);

use App\Core\Db;
use App\Core\Migrator;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

try {
    $migrator = new Migrator(Db::pdo(), APP_ROOT . '/database/migrations');

    if (in_array('--status', $argv, true)) {
        foreach ($migrator->applied() as $file) {
            echo "  applied  {$file}\n";
        }
        foreach ($migrator->pending() as $file) {
            echo "  pending  {$file}\n";
        }
        exit(0);
    }

    $applied = $migrator->migrate(function (string $file): void {
        echo "Applied {$file}\n";
    });
    echo $applied ? count($applied) . " migration(s) applied.\n" : "Database is up to date.\n";
    exit(0);
} catch (Throwable $ex) {
    fwrite(STDERR, 'Migration failed: ' . $ex->getMessage() . "\n");
    exit(1);
}
