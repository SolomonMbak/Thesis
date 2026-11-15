<?php
declare(strict_types=1);

namespace App\Data;

use PDO;

/**
 * Semesters. Exactly one may be active at a time.
 */
final class SemesterRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function active(): ?array
    {
        $row = $this->pdo->query('SELECT * FROM semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
        return $row ?: null;
    }

    public static function label(array $semester): string
    {
        $names = [1 => 'First', 2 => 'Second', 3 => 'Third'];
        $no = (int) $semester['semester_no'];
        return $semester['session_label'] . ' ' . ($names[$no] ?? (string) $no) . ' Semester';
    }
}
