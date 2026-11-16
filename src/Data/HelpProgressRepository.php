<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Which tips, tour stops and optional setup steps a user has seen or
 * skipped in a school (help_progress), e.g. tour_done, tip_rooms, skip_availability.
 */
final class HelpProgressRepository
{
    public function __construct(private PDO $pdo, private SchoolContext $school, private int $userId)
    {
    }

    /** @return array<string,true> */
    public function done(): array
    {
        $stmt = $this->pdo->prepare('SELECT item FROM help_progress WHERE user_id = ? AND school_id = ?');
        $stmt->execute([$this->userId, $this->school->id]);
        return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    }

    public function has(string $item): bool
    {
        return isset($this->done()[$item]);
    }

    public function mark(string $item): void
    {
        if (!preg_match('/^[a-z0-9_]{1,60}$/', $item)) {
            throw new \InvalidArgumentException('Bad help item name.');
        }
        $this->pdo->prepare('INSERT IGNORE INTO help_progress (user_id, school_id, item) VALUES (?, ?, ?)')
            ->execute([$this->userId, $this->school->id, $item]);
    }

    public function clear(string $item): void
    {
        $this->pdo->prepare('DELETE FROM help_progress WHERE user_id = ? AND school_id = ? AND item = ?')
            ->execute([$this->userId, $this->school->id, $item]);
    }

    /** Has anyone in this school skipped an optional step? Steps are shared by the whole school. */
    public static function anyoneDid(PDO $pdo, SchoolContext $school, string $item): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM help_progress WHERE school_id = ? AND item = ?');
        $stmt->execute([$school->id, $item]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
