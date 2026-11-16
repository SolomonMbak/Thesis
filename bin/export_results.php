<?php
/**
 * Export the runs of one experiment label to CSV in storage/exports.
 *
 *   php bin/export_results.php --label=SGA
 *   php bin/export_results.php --label=RGA --out=/path/to/file.csv
 *
 * Writes two files:
 *   results_<LABEL>_<date>.csv  one row per run: every measure from BUILD_SPEC section 15
 *   curve_<LABEL>_<date>.csv    median best penalty and median V_H per generation
 * Both start with comment lines (#) recording CPU, RAM, OS, PHP version and date.
 */
declare(strict_types=1);

use App\Core\Db;
use App\Services\CliSchool;
use App\Services\ExperimentResults;
use App\Services\SystemInfo;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

$opts = getopt('', ['label:', 'out:', 'school:']);
$label = trim((string) ($opts['label'] ?? ''));
if ($label === '') {
    fwrite(STDERR, "Usage: php bin/export_results.php --label=SGA [--school=demo] [--out=file.csv]\n");
    exit(2);
}

$results = new ExperimentResults(Db::pdo(), CliSchool::fromOptions(Db::pdo(), $opts));
$runs = $results->runs($label);
if (!$runs) {
    fwrite(STDERR, "No finished runs with label '{$label}'.\n");
    exit(1);
}

$safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $label);
$stamp = date('Ymd_His');
$resultsFile = $opts['out'] ?? APP_ROOT . "/storage/exports/results_{$safe}_{$stamp}.csv";
$curveFile = dirname($resultsFile) . "/curve_{$safe}_{$stamp}.csv";

writeCsv($resultsFile, $label, ExperimentResults::COLUMNS, array_map(
    fn ($r) => array_map(fn ($c) => $r[$c], ExperimentResults::COLUMNS),
    $runs
));
$curve = $results->medianCurve(array_column($runs, 'job_id'));
writeCsv($curveFile, $label, ['generation', 'runs', 'median_best_penalty', 'median_V_H'], array_map(
    fn ($p) => [$p['generation'], $p['runs'], $p['median_penalty'], $p['median_hard']],
    $curve
));

printf("%d runs exported to %s\nMedian convergence curve (%d generations) exported to %s\n",
    count($runs), $resultsFile, count($curve), $curveFile);

function writeCsv(string $path, string $label, array $header, array $rows): void
{
    $out = fopen($path, 'wb');
    if ($out === false) {
        fwrite(STDERR, "Cannot write {$path}\n");
        exit(1);
    }
    foreach (SystemInfo::describe() as $key => $value) {
        fwrite($out, "# {$key}: {$value}\n");
    }
    fwrite($out, "# label: {$label}\n");
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, $row, ',', '"', '');
    }
    fclose($out);
}
