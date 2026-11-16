<?php
/**
 * Compare two experiment labels (BUILD_SPEC section 15).
 *
 *   php bin/stats.php --a=SGA --b=RGA
 *   php bin/stats.php --a=SGA --b=RGA --man=MAN --csv
 *
 * For each measure and label: n, mean, SD, median, min, max; feasibility
 * rate; and a two-sided Mann-Whitney U test (normal approximation with tie
 * correction, alpha 0.05) on final penalty, soft penalty, runtime and first
 * feasible generation. Runs that never became feasible have no first
 * feasible generation and are left out of that measure (n shows how many).
 * --man places a manual timetable within the distribution of label b.
 * --csv also writes the table to storage/exports.
 */
declare(strict_types=1);

use App\Core\Db;
use App\Services\CliSchool;
use App\Services\ExperimentResults;
use App\Services\Statistics;
use App\Services\SystemInfo;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

const ALPHA = 0.05;
$opts = getopt('', ['a:', 'b:', 'man::', 'csv', 'school:']);
$labelA = (string) ($opts['a'] ?? '');
$labelB = (string) ($opts['b'] ?? '');
if ($labelA === '' || $labelB === '') {
    fwrite(STDERR, "Usage: php bin/stats.php --a=SGA --b=RGA [--school=demo] [--man=MAN] [--csv]\n");
    exit(2);
}

$results = new ExperimentResults(Db::pdo(), CliSchool::fromOptions(Db::pdo(), $opts));
$a = $results->runs($labelA);
$b = $results->runs($labelB);
if (!$a || !$b) {
    fwrite(STDERR, "Need finished runs for both labels ({$labelA}: " . count($a) . ", {$labelB}: " . count($b) . ").\n");
    exit(1);
}

$measures = [
    'penalty' => 'Final penalty', 'soft' => 'Soft penalty', 'V_H' => 'Hard violations V_H',
    'runtime_ms' => 'Runtime (ms)', 'first_feasible_generation' => 'First feasible generation',
    'generations' => 'Generations run', 'fitness' => 'Fitness', 'peak_memory_mb' => 'Peak memory (MB)',
];
$tested = ['penalty', 'soft', 'runtime_ms', 'first_feasible_generation'];

$values = fn (array $runs, string $m) => array_values(array_filter(array_column($runs, $m), fn ($v) => $v !== null));
$lines = [];
$csv = [['measure', 'label', 'n', 'mean', 'sd', 'median', 'min', 'max', 'U', 'z', 'p', 'significant']];

foreach (SystemInfo::describe() as $k => $v) {
    $lines[] = "# {$k}: {$v}";
}
$lines[] = '';
$lines[] = sprintf('%-27s %-6s %4s %12s %12s %12s %12s %12s', 'Measure', 'Label', 'n', 'Mean', 'SD', 'Median', 'Min', 'Max');
foreach ($measures as $key => $name) {
    $test = in_array($key, $tested, true) ? Statistics::mannWhitney($values($a, $key), $values($b, $key)) : null;
    foreach ([$labelA => $a, $labelB => $b] as $label => $runs) {
        $d = Statistics::describe($values($runs, $key));
        $lines[] = sprintf('%-27s %-6s %4d %12s %12s %12s %12s %12s', $name, $label, $d['n'],
            fmt($d['mean']), fmt($d['sd']), fmt($d['median']), fmt($d['min']), fmt($d['max']));
        $csv[] = [$name, $label, $d['n'], $d['mean'], $d['sd'], $d['median'], $d['min'], $d['max'],
            $test['u'] ?? '', $test['z'] ?? '', $test['p'] ?? '', $test && $test['p'] !== null ? ($test['p'] < ALPHA ? 'yes' : 'no') : ''];
        $name = '';
    }
    if ($test) {
        $lines[] = $test['p'] === null
            ? '    Mann-Whitney U: not enough values'
            : sprintf('    Mann-Whitney U = %s, z = %.4f, p = %.6f (%s at alpha %.2f)', fmt($test['u']), $test['z'], $test['p'],
                $test['p'] < ALPHA ? 'significant' : 'not significant', ALPHA);
    }
}

$rate = fn (array $runs) => count(array_filter($runs, fn ($r) => $r['feasible'] === 1)) / count($runs);
$lines[] = '';
$lines[] = sprintf('Feasibility rate: %s %.1f%% (%d of %d), %s %.1f%% (%d of %d)',
    $labelA, 100 * $rate($a), (int) round($rate($a) * count($a)), count($a),
    $labelB, 100 * $rate($b), (int) round($rate($b) * count($b)), count($b));

if (array_key_exists('man', $opts)) {
    $man = $results->manual(is_string($opts['man']) && $opts['man'] !== '' ? $opts['man'] : null);
    if (!$man) {
        $lines[] = 'Manual timetable: none found (import it under Timetables first).';
    } else {
        $penalties = array_column($b, 'penalty');
        $better = count(array_filter($penalties, fn ($p) => $p < (int) $man['best_penalty']));
        $lines[] = sprintf('Manual timetable #%d (%s): V_H %d, penalty %d. %d of %d %s runs (%.1f%%) have a lower (better) penalty'
            . ' than the manual timetable; %s median is %s.',
            $man['id'], $man['label'], $man['hard_violations'], $man['best_penalty'], $better, count($penalties), $labelB,
            100 * $better / count($penalties), $labelB, fmt(Statistics::median($penalties)));
    }
}

echo implode(PHP_EOL, $lines) . PHP_EOL;

if (isset($opts['csv'])) {
    $file = APP_ROOT . '/storage/exports/stats_' . preg_replace('/[^A-Za-z0-9_-]/', '_', "{$labelA}_vs_{$labelB}") . '_' . date('Ymd_His') . '.csv';
    $out = fopen($file, 'wb');
    foreach (SystemInfo::describe() as $k => $v) {
        fwrite($out, "# {$k}: {$v}\n");
    }
    foreach ($csv as $row) {
        fputcsv($out, $row, ',', '"', '');
    }
    fclose($out);
    echo "Table written to {$file}\n";
}

function fmt(?float $v): string
{
    if ($v === null) {
        return '-';
    }
    if (abs($v - round($v)) < 1e-9) {
        return number_format($v, 0, '.', '');
    }
    // Small values such as fitness (about 0.001) need more decimals to be readable.
    return abs($v) < 1 ? sprintf('%.6f', $v) : number_format($v, 3, '.', '');
}
