<?php
/**
 * Build a release zip for deployment.
 *
 *   php bin/package.php                  writes release/timetable-<date>.zip
 *   php bin/package.php --out=FILE.zip
 *
 * Left out: tests, .git, config/config.local.php, the contents of storage/
 * (its folders and their .htaccess files are kept), IDE folders, earlier
 * releases and developer notes.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required to build a release.\n");
    exit(1);
}

const EXCLUDE_PREFIXES = ['tests/', '.git/', '.idea/', '.vscode/', 'release/', 'node_modules/', 'storage/'];
const EXCLUDE_FILES = ['config/config.local.php', 'CLAUDE.md', '.gitignore', '.DS_Store'];
// Inside storage/ only the empty folders (kept by .gitkeep) and the deny-all .htaccess ship.
const KEEP_IN_STORAGE = ['storage/.htaccess', 'storage/logs/.gitkeep', 'storage/exports/.gitkeep',
    'storage/backups/.gitkeep', 'storage/cache/.gitkeep'];

$opts = getopt('', ['out:']);
$out = $opts['out'] ?? APP_ROOT . '/release/timetable-' . date('Ymd-His') . '.zip';
if (!is_dir(dirname($out)) && !mkdir(dirname($out), 0775, true)) {
    fwrite(STDERR, "Cannot create " . dirname($out) . "\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot write {$out}\n");
    exit(1);
}

$root = 'timetable/';
$count = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(APP_ROOT) + 1));
    if (!shouldInclude($relative)) {
        continue;
    }
    $zip->addFile($file->getPathname(), $root . $relative);
    $count++;
}
$zip->close();
printf("Release written: %s (%d files, %.1f KB)\n", $out, $count, filesize($out) / 1024);

function shouldInclude(string $path): bool
{
    if (in_array($path, KEEP_IN_STORAGE, true)) {
        return true;
    }
    if (in_array($path, EXCLUDE_FILES, true) || in_array(basename($path), EXCLUDE_FILES, true)) {
        return false;
    }
    foreach (EXCLUDE_PREFIXES as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return false;
        }
    }
    return true;
}
