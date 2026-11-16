<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Reading the activity log of one school, with filters and paging.
 * (Writing is App\Core\Audit.)
 */
final class AuditRepository
{
    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** @return array{rows: array, total: int} */
    public function page(string $actionPrefix, int $userId, int $page, int $perPage): array
    {
        $where = ['a.school_id = ?'];
        $params = [$this->school->id];
        if ($actionPrefix !== '') {
            $where[] = 'a.action LIKE ?';
            $params[] = $actionPrefix . '%';
        }
        if ($userId > 0) {
            $where[] = 'a.user_id = ?';
            $params[] = $userId;
        }
        $whereSql = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log a WHERE {$whereSql}");
        $count->execute($params);
        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             WHERE {$whereSql} ORDER BY a.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage)
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** People who appear in this school's log (members and former members). */
    public function people(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT u.id, u.full_name FROM audit_log a JOIN users u ON u.id = a.user_id WHERE a.school_id = ? ORDER BY u.full_name'
        );
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function areas(): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT SUBSTRING_INDEX(action, '.', 1) FROM audit_log WHERE school_id = ? ORDER BY 1");
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
