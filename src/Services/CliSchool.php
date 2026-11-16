<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use App\Data\SchoolRepository;
use PDO;

/**
 * Chooses the school a command line script works on: --school=<slug>, or
 * the only school when there is exactly one. Scripts run with full rights
 * inside that one school and never touch another.
 */
final class CliSchool
{
    /** @throws \RuntimeException with a message listing the schools when the choice is unclear */
    public static function resolve(PDO $pdo, ?string $slug): SchoolContext
    {
        $repo = new SchoolRepository($pdo);
        if ($slug !== null && $slug !== '') {
            $school = $repo->findBySlug($slug);
            if ($school === null) {
                throw new \RuntimeException("There is no school with the link name '{$slug}'. " . self::list($pdo));
            }
            return SchoolContext::system($school);
        }
        $all = $pdo->query('SELECT * FROM schools ORDER BY id')->fetchAll();
        if (count($all) === 1) {
            return SchoolContext::system($all[0]);
        }
        throw new \RuntimeException($all ? 'Several schools exist, so choose one with --school=<slug>. ' . self::list($pdo)
            : 'There is no school yet. Run php bin/seed.php or create one in the browser.');
    }

    /** For scripts: resolve from the --school option, or print the reason and exit. */
    public static function fromOptions(PDO $pdo, array $opts): SchoolContext
    {
        try {
            return self::resolve($pdo, isset($opts['school']) ? (string) $opts['school'] : null);
        } catch (\RuntimeException $ex) {
            fwrite(STDERR, $ex->getMessage() . "\n");
            exit(2);
        }
    }

    private static function list(PDO $pdo): string
    {
        $slugs = $pdo->query('SELECT slug FROM schools ORDER BY slug')->fetchAll(PDO::FETCH_COLUMN);
        return $slugs ? 'Schools: ' . implode(', ', $slugs) . '.' : '';
    }
}
