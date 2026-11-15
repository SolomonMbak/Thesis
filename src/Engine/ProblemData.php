<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Everything the GA needs to know about one timetabling problem, as flat
 * integer-indexed arrays (BUILD_SPEC 8.1 and 8.8). Built from plain arrays,
 * so it has no link to the database; ProblemLoader fills it from MySQL and
 * tests build it by hand.
 *
 * Index spaces: sessions s (= gene index), periods p, rooms r, lecturers l,
 * groups g, courses c, days d. Periods are ordered by day, then time.
 *
 * Input shape for fromArrays():
 *   periods   => [['day' => 1, 'blocked' => false], ...]        ordered by day, then time
 *   rooms     => [['capacity' => 60, 'lab' => false, 'active' => true], ...]
 *   lecturers => [['unavailable' => [p, ...], 'preferred' => [p, ...]], ...]
 *   groups    => number of groups
 *   courses   => number of courses
 *   sessions  => [['lecturer' => l, 'group' => g, 'course' => c, 'size' => n, 'lab' => bool, 'duration' => k], ...]
 *   weights   => ['H' => 1000, 'S1' => 5, 'S2' => 3, 'S3' => 4, 'S4' => 3, 'S5' => 2]
 */
final class ProblemData
{
    // Periods
    public int $P = 0;
    public int $D = 0;
    /** @var int[] day index 0..D-1 of each period */
    public array $periodDay = [];
    /** @var int[] position of the period within its day, from 0 */
    public array $periodPos = [];
    /** @var bool[] */
    public array $blocked = [];
    /** @var bool[] true for the last period of each day (S5) */
    public array $lastOfDay = [];
    /** @var int[] first period index of each day */
    public array $dayFirst = [];
    /** @var int[] number of periods in each day */
    public array $dayLength = [];

    // Rooms
    public int $R = 0;
    public array $roomCap = [];
    public array $roomLab = [];
    public array $roomActive = [];

    // Lecturers: flat matrices indexed [l * P + p]
    public int $L = 0;
    public array $unavailable = [];
    public array $preferred = [];
    /** @var bool[] lecturer marked at least one preferred period (S1 applies) */
    public array $lecHasPref = [];

    public int $G = 0;
    public int $C = 0;

    // Sessions (genes)
    public int $S = 0;
    public array $sLec = [];
    public array $sGroup = [];
    public array $sCourse = [];
    public array $sSize = [];
    public array $sLab = [];
    public array $sDur = [];

    // Weights
    public int $wH = 1000;
    /** @var int[] index 1..5 for S1..S5 */
    public array $wS = [];

    /** @var int[][] valid start periods per session (BUILD_SPEC 8.3) */
    public array $allowedStarts = [];
    /** @var int[][] suitable rooms per session */
    public array $allowedRooms = [];

    public static function fromArrays(array $in): self
    {
        $d = new self();
        $d->loadPeriods($in['periods']);
        $d->loadRooms($in['rooms']);
        $d->loadLecturers($in['lecturers']);
        $d->G = (int) $in['groups'];
        $d->C = (int) $in['courses'];
        $d->loadSessions($in['sessions']);
        $d->loadWeights($in['weights'] ?? []);
        $d->computeDomains();
        return $d;
    }

    /** Is period p (and the next duration-1 periods) usable as a start for session s? */
    public function isValidStart(int $s, int $p): bool
    {
        $dur = $this->sDur[$s];
        if ($p < 0 || $p + $dur > $this->P) {
            return false;
        }
        $day = $this->periodDay[$p];
        $base = $this->sLec[$s] * $this->P;
        for ($k = 0; $k < $dur; $k++) {
            $q = $p + $k;
            if ($this->periodDay[$q] !== $day || $this->blocked[$q] || $this->unavailable[$base + $q]) {
                return false;
            }
        }
        return true;
    }

    /** Room passes H4 (capacity) and H5 (practicals in labs) for session s and is active. */
    public function isSuitableRoom(int $s, int $r): bool
    {
        return $this->roomActive[$r]
            && $this->roomCap[$r] >= $this->sSize[$s]
            && (!$this->sLab[$s] || $this->roomLab[$r]);
    }

    private function loadPeriods(array $periods): void
    {
        $this->P = count($periods);
        $dayIndex = [];
        $prevDay = null;
        $pos = 0;
        foreach (array_values($periods) as $p => $period) {
            $day = (int) $period['day'];
            if (!isset($dayIndex[$day])) {
                $dayIndex[$day] = count($dayIndex);
                $this->dayFirst[] = $p;
                $this->dayLength[] = 0;
            }
            $pos = $day === $prevDay ? $pos + 1 : 0;
            $prevDay = $day;
            $di = $dayIndex[$day];
            $this->periodDay[$p] = $di;
            $this->periodPos[$p] = $pos;
            $this->blocked[$p] = (bool) $period['blocked'];
            $this->dayLength[$di]++;
        }
        $this->D = count($dayIndex);
        for ($p = 0; $p < $this->P; $p++) {
            $this->lastOfDay[$p] = $p === $this->P - 1 || $this->periodDay[$p + 1] !== $this->periodDay[$p];
        }
    }

    private function loadRooms(array $rooms): void
    {
        foreach (array_values($rooms) as $r => $room) {
            $this->roomCap[$r] = (int) $room['capacity'];
            $this->roomLab[$r] = (bool) $room['lab'];
            $this->roomActive[$r] = (bool) ($room['active'] ?? true);
        }
        $this->R = count($this->roomCap);
    }

    private function loadLecturers(array $lecturers): void
    {
        $this->L = count($lecturers);
        $this->unavailable = array_fill(0, max(1, $this->L * $this->P), false);
        $this->preferred = array_fill(0, max(1, $this->L * $this->P), false);
        foreach (array_values($lecturers) as $l => $lec) {
            foreach ($lec['unavailable'] ?? [] as $p) {
                $this->unavailable[$l * $this->P + (int) $p] = true;
            }
            foreach ($lec['preferred'] ?? [] as $p) {
                $this->preferred[$l * $this->P + (int) $p] = true;
            }
            $this->lecHasPref[$l] = !empty($lec['preferred']);
        }
    }

    private function loadSessions(array $sessions): void
    {
        foreach (array_values($sessions) as $s => $sess) {
            $this->sLec[$s] = (int) $sess['lecturer'];
            $this->sGroup[$s] = (int) $sess['group'];
            $this->sCourse[$s] = (int) $sess['course'];
            $this->sSize[$s] = (int) $sess['size'];
            $this->sLab[$s] = (bool) $sess['lab'];
            $this->sDur[$s] = max(1, (int) $sess['duration']);
        }
        $this->S = count($this->sDur);
    }

    private function loadWeights(array $w): void
    {
        $this->wH = (int) ($w['H'] ?? 1000);
        $defaults = [1 => 5, 2 => 3, 3 => 4, 4 => 3, 5 => 2];
        foreach ($defaults as $i => $default) {
            $this->wS[$i] = (int) ($w['S' . $i] ?? $default);
        }
    }

    private function computeDomains(): void
    {
        for ($s = 0; $s < $this->S; $s++) {
            $starts = [];
            for ($p = 0; $p < $this->P; $p++) {
                if ($this->isValidStart($s, $p)) {
                    $starts[] = $p;
                }
            }
            $rooms = [];
            for ($r = 0; $r < $this->R; $r++) {
                if ($this->isSuitableRoom($s, $r)) {
                    $rooms[] = $r;
                }
            }
            $this->allowedStarts[$s] = $starts;
            $this->allowedRooms[$s] = $rooms;
        }
    }
}
