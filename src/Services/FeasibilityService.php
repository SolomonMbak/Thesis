<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Runs the feasibility checker on a semester and stores the result.
 * Used by the feasibility page and the job launcher.
 */
final class FeasibilityService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{ok:bool, failures:array} */
    public function run(int $semesterId, ?int $userId): array
    {
        $ctx = (new ProblemLoader($this->pdo))->load($semesterId);
        $failures = (new FeasibilityChecker())->check($ctx);
        $ok = $failures === [];
        $this->pdo->prepare('INSERT INTO feasibility_checks (semester_id, ok, failures_json, checked_by) VALUES (?, ?, ?, ?)')
            ->execute([$semesterId, $ok ? 1 : 0, json_encode($failures, JSON_UNESCAPED_UNICODE), $userId]);
        return ['ok' => $ok, 'failures' => $failures];
    }

    public function latest(int $semesterId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM feasibility_checks WHERE semester_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$semesterId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['failures'] = json_decode((string) $row['failures_json'], true) ?: [];
        return $row;
    }
}
