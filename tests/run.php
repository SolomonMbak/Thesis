<?php
/**
 * Minimal test runner (no PHPUnit, no Composer).
 *
 *   php tests/run.php            run every tests/*Test.php
 *   php tests/run.php Csrf       run only files whose name contains "Csrf"
 *
 * Each file holds one class Tests\<Name>Test extending Tests\TestCase.
 * Every public method whose name starts with "test" is one test.
 * Exit code is 0 only when nothing failed.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/TestCase.php';

use Tests\SkippedTest;
use Tests\TestCase;

$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/*Test.php') ?: [];
sort($files);

$passed = $failed = $skipped = 0;
$failures = [];
$started = microtime(true);

foreach ($files as $file) {
    $class = 'Tests\\' . basename($file, '.php');
    if ($filter !== '' && stripos($class, $filter) === false) {
        continue;
    }
    require_once $file;
    echo basename($file, '.php') . "\n";

    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        /** @var TestCase $case */
        $case = new $class();
        try {
            $case->setUp();
            $case->$method();
            $passed++;
            echo "  ok    {$method}\n";
        } catch (SkippedTest $ex) {
            $skipped++;
            echo "  skip  {$method}: {$ex->getMessage()}\n";
        } catch (Throwable $ex) {
            $failed++;
            $failures[] = "{$class}::{$method}\n    " . get_class($ex) . ': ' . $ex->getMessage()
                . "\n    at " . $ex->getFile() . ':' . $ex->getLine();
            echo "  FAIL  {$method}\n";
        } finally {
            try {
                $case->tearDown();
            } catch (Throwable $ex) {
                echo "  (tearDown error: {$ex->getMessage()})\n";
            }
        }
    }
}

$seconds = number_format(microtime(true) - $started, 2);
echo "\n";
foreach ($failures as $failure) {
    echo "FAILED {$failure}\n\n";
}
echo "{$passed} passed, {$failed} failed, {$skipped} skipped in {$seconds}s\n";
exit($failed === 0 ? 0 : 1);
