<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Records who did what, for the audit log viewer.
 * Action names use "area.verb", e.g. course.update, job.start, auth.login.
 * Entries carry the school they belong to; platform events (installation,
 * backups, sign-in failures) have none.
 */
final class Audit
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(
        ?int $userId,
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $details = [],
        ?string $ip = null,
        ?int $schoolId = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_log (school_id, user_id, action, entity, entity_id, details, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $schoolId,
                $userId,
                $action,
                $entity,
                $entityId,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                $ip,
            ]);
        } catch (\PDOException $ex) {
            // An audit failure must not undo the user's action; log it instead.
            Logger::exception($ex);
        }
    }
}
