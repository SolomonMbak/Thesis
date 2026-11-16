<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Semester dates and days without teaching (holidays) of one school, used by
 * the monthly calendar view (BUILD_SPEC 10.7).
 */
final class CalendarRepository
{
    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /** Set or clear the first and last day of teaching. Returns false if the semester is not this school's. */
    public function setDates(int $semesterId, ?string $start, ?string $end): bool
    {
        $stmt = $this->pdo->prepare('UPDATE semesters SET start_date = ?, end_date = ? WHERE id = ? AND school_id = ?');
        $stmt->execute([$start, $end, $semesterId, $this->school->id]);
        return (new SemesterRepository($this->pdo, $this->school))->find($semesterId) !== null;
    }

    /** @return array<int, array{id:int, closed_on:string, reason:string}> in date order */
    public function closures(int $semesterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, closed_on, reason FROM school_closures WHERE school_id = ? AND semester_id = ? ORDER BY closed_on'
        );
        $stmt->execute([$this->school->id, $semesterId]);
        return $stmt->fetchAll();
    }

    /** date => reason, for the calendar. */
    public function closureMap(int $semesterId): array
    {
        return array_column($this->closures($semesterId), 'reason', 'closed_on');
    }

    /** Add (or rename) a day without teaching. */
    public function addClosure(int $semesterId, string $date, string $reason): void
    {
        $this->pdo->prepare(
            'INSERT INTO school_closures (school_id, semester_id, closed_on, reason) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
        )->execute([$this->school->id, $semesterId, $date, $reason]);
    }

    public function removeClosure(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM school_closures WHERE id = ? AND school_id = ?');
        $stmt->execute([$id, $this->school->id]);
        return $stmt->rowCount() > 0;
    }
}
