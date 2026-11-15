<?php
declare(strict_types=1);

namespace App\Services;

use App\Engine\ProblemData;

/**
 * ProblemData plus the database ids and readable names behind each index,
 * so engine results can be saved and explained in plain English.
 */
final class ProblemContext
{
    public function __construct(
        public readonly ProblemData $data,
        /** @var int[] s => class_sessions.id */
        public readonly array $sessionIds,
        /** @var array<int, array{course_id:int, code:string, title:string, session_no:int, duration:int, lab:bool, group:string, lecturer:string}> */
        public readonly array $sessions,
        /** @var int[] p => timeslots.id */
        public readonly array $periodIds,
        /** @var array<int, array{day:int, period_no:int, start:string, end:string, blocked:bool}> */
        public readonly array $periods,
        /** @var int[] r => rooms.id */
        public readonly array $roomIds,
        /** @var array<int, array{code:string, name:string, capacity:int, lab:bool, active:bool}> */
        public readonly array $rooms,
        /** @var int[] l => lecturers.id */
        public readonly array $lecturerIds,
        /** @var string[] l => display name */
        public readonly array $lecturerNames,
        /** @var int[] g => student_groups.id */
        public readonly array $groupIds,
        /** @var string[] g => group code */
        public readonly array $groupCodes,
        /** @var int[] c => courses.id */
        public readonly array $courseIds,
        /** @var string[] c => course code */
        public readonly array $courseCodes,
        /** @var array<string,int> */
        public readonly array $weights
    ) {
    }

    /** "Mon 08:00" */
    public function periodLabel(int $p): string
    {
        $period = $this->periods[$p];
        return PeriodGenerator::SHORT_DAYS[$period['day']] . ' ' . $period['start'];
    }

    /** "CSC 101 (session 2)" */
    public function sessionLabel(int $s): string
    {
        return $this->sessions[$s]['code'] . ' (session ' . $this->sessions[$s]['session_no'] . ')';
    }

    public function periodIndexById(int $id): ?int
    {
        $index = array_search($id, $this->periodIds, true);
        return $index === false ? null : $index;
    }

    public function roomIndexById(int $id): ?int
    {
        $index = array_search($id, $this->roomIds, true);
        return $index === false ? null : $index;
    }
}
