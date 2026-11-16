<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Constraint weights of one school (BUILD_SPEC section 6). Each school
 * gets the defaults when it is created and can change them.
 */
final class WeightRepository
{
    public const DEFAULTS = [
        ['H', 'Any hard violation (H1 to H6)', 'hard', 1000],
        ['S1', 'Lecturer preferred periods', 'soft', 5],
        ['S2', 'Idle gaps for student groups', 'soft', 3],
        ['S3', 'Course sessions spread across days', 'soft', 4],
        ['S4', 'More than three consecutive hours', 'soft', 3],
        ['S5', 'Late period of the day', 'soft', 2],
    ];

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** Rows ordered hard first, then S1 to S5. */
    public function all(): array
    {
        $stmt = $this->pdo->prepare('SELECT code, description, constraint_type, weight FROM constraint_weights WHERE school_id = ? ORDER BY constraint_type, code');
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll();
    }

    /** ['H' => 1000, 'S1' => 5, ...] */
    public function map(): array
    {
        $stmt = $this->pdo->prepare('SELECT code, weight FROM constraint_weights WHERE school_id = ?');
        $stmt->execute([$this->school->id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function set(string $code, int $weight): void
    {
        $this->pdo->prepare('UPDATE constraint_weights SET weight = ? WHERE school_id = ? AND code = ?')
            ->execute([$weight, $this->school->id, $code]);
    }

    /** Insert the default weights (new school) without touching existing ones. */
    public function insertDefaults(): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO constraint_weights (school_id, code, description, constraint_type, weight) VALUES (?, ?, ?, ?, ?)');
        foreach (self::DEFAULTS as $row) {
            $stmt->execute([$this->school->id, ...$row]);
        }
    }
}
