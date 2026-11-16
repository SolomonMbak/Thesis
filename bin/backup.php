<?php
/**
 * Create a database backup in storage/backups (keeps the newest 10).
 *
 *   php bin/backup.php
 *   php bin/backup.php --php     force the PHP export instead of mysqldump
 *
 * Daily cron example: 0 2 * * * php /var/www/timetable/bin/backup.php
 * Restore: gunzip -c backup_YYYYMMDD_HHMMSS.sql.gz | mysql -u USER -p DATABASE
 */
declare(strict_types=1);

use App\Core\Audit;
use App\Core\Db;
use App\Services\BackupService;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
set_time_limit(0);

try {
    $service = new BackupService(Db::pdo());
    if (in_array('--php', $argv, true)) {
        $name = 'backup_' . date('Ymd_His') . '.sql.gz';
        $service->phpExport(APP_ROOT . '/storage/backups/' . $name);
        $service->prune();
        $result = ['file' => $name, 'method' => 'php', 'bytes' => filesize(APP_ROOT . '/storage/backups/' . $name)];
    } else {
        $result = $service->create();
    }
    (new Audit(Db::pdo()))->log(null, 'backup.create', null, null, $result, 'cli');
    printf("Backup written: storage/backups/%s (%s, %.1f KB)\n", $result['file'], $result['method'], $result['bytes'] / 1024);
} catch (Throwable $ex) {
    fwrite(STDERR, 'Backup failed: ' . $ex->getMessage() . "\n");
    exit(1);
}
