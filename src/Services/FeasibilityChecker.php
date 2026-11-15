<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Necessary-condition checks before a run (BUILD_SPEC section 7).
 * Reports every failure, by name, with a link to the page that fixes it.
 * Passing does not guarantee a clash-free timetable exists.
 *
 * Rule 5 uses the same allowedStarts and allowedRooms as the GA, so any
 * session the GA could not place is always reported here first.
 */
final class FeasibilityChecker
{
    /**
     * @return array<int, array{rule:int, message:string, link:array{0:string,1:array,2:string}|null}>
     */
    public function check(ProblemContext $ctx): array
    {
        $d = $ctx->data;
        if ($d->S === 0) {
            return [self::failure(0, 'No class sessions have been built for this semester. Add courses, then build sessions.',
                ['sessions', [], 'Build sessions'])];
        }

        $openPeriods = count(array_filter($d->blocked, fn ($b) => !$b));
        $failures = [];
        array_push(
            $failures,
            ...$this->roomCapacity($ctx, $openPeriods),
            ...$this->lecturerLoad($ctx),
            ...$this->groupLoad($ctx, $openPeriods),
            ...$this->sessionDomains($ctx)
        );
        return $failures;
    }

    /** Rules 1 and 2: enough room-periods in total, and enough lab-periods. */
    private function roomCapacity(ProblemContext $ctx, int $openPeriods): array
    {
        $d = $ctx->data;
        $failures = [];
        $activeRooms = 0;
        $activeLabs = 0;
        for ($r = 0; $r < $d->R; $r++) {
            if ($d->roomActive[$r]) {
                $activeRooms++;
                $activeLabs += $d->roomLab[$r] ? 1 : 0;
            }
        }
        $total = array_sum($d->sDur);
        $supply = $openPeriods * $activeRooms;
        if ($total > $supply) {
            $failures[] = self::failure(1, "The timetable needs {$total} room-periods, but {$activeRooms} active room(s) "
                . "x {$openPeriods} open period(s) give only {$supply}. Add or reactivate rooms, or unblock periods.",
                ['rooms', [], 'Rooms']);
        }
        $labTotal = 0;
        for ($s = 0; $s < $d->S; $s++) {
            $labTotal += $d->sLab[$s] ? $d->sDur[$s] : 0;
        }
        $labSupply = $openPeriods * $activeLabs;
        if ($labTotal > $labSupply) {
            $failures[] = self::failure(2, "Practicals need {$labTotal} lab-periods, but {$activeLabs} active lab(s) "
                . "x {$openPeriods} open period(s) give only {$labSupply}. Add or reactivate labs.",
                ['rooms', [], 'Rooms']);
        }
        return $failures;
    }

    /** Rule 3: each lecturer has enough open, not-unavailable periods. */
    private function lecturerLoad(ProblemContext $ctx): array
    {
        $d = $ctx->data;
        $needed = array_fill(0, $d->L, 0);
        for ($s = 0; $s < $d->S; $s++) {
            $needed[$d->sLec[$s]] += $d->sDur[$s];
        }
        $failures = [];
        for ($l = 0; $l < $d->L; $l++) {
            $available = 0;
            for ($p = 0; $p < $d->P; $p++) {
                if (!$d->blocked[$p] && !$d->unavailable[$l * $d->P + $p]) {
                    $available++;
                }
            }
            if ($needed[$l] > $available) {
                $failures[] = self::failure(3, "{$ctx->lecturerNames[$l]} needs {$needed[$l]} teaching periods "
                    . "but is available for only {$available}.",
                    ['availability', ['lecturer' => $ctx->lecturerIds[$l]], 'Edit availability']);
            }
        }
        return $failures;
    }

    /** Rule 4: no group needs more periods than are open. */
    private function groupLoad(ProblemContext $ctx, int $openPeriods): array
    {
        $d = $ctx->data;
        $needed = array_fill(0, $d->G, 0);
        for ($s = 0; $s < $d->S; $s++) {
            $needed[$d->sGroup[$s]] += $d->sDur[$s];
        }
        $failures = [];
        for ($g = 0; $g < $d->G; $g++) {
            if ($needed[$g] > $openPeriods) {
                $failures[] = self::failure(4, "Group {$ctx->groupCodes[$g]} has {$needed[$g]} hours of classes "
                    . "but there are only {$openPeriods} open periods in the week.",
                    ['courses', ['group_id' => $ctx->groupIds[$g]], 'View courses']);
            }
        }
        return $failures;
    }

    /** Rule 5: every session has a suitable room and a valid start. */
    private function sessionDomains(ProblemContext $ctx): array
    {
        $d = $ctx->data;
        $failures = [];
        for ($s = 0; $s < $d->S; $s++) {
            $info = $ctx->sessions[$s];
            $label = $ctx->sessionLabel($s);
            if (!$d->allowedRooms[$s]) {
                $need = $info['lab'] ? 'an active lab' : 'an active room';
                $failures[] = self::failure(5, "{$label} has no suitable room: it needs {$need} with at least {$d->sSize[$s]} seats.",
                    ['rooms', [], 'Rooms']);
            }
            if (!$d->allowedStarts[$s]) {
                $hours = $d->sDur[$s] === 1 ? 'an open period' : "{$d->sDur[$s]} consecutive open periods on one day";
                $failures[] = self::failure(5, "{$label} has no valid start time: it needs {$hours} "
                    . "when {$ctx->lecturerNames[$d->sLec[$s]]} is available, and there is none.",
                    ['availability', ['lecturer' => $ctx->lecturerIds[$d->sLec[$s]]], 'Edit availability']);
            }
        }
        return $failures;
    }

    private static function failure(int $rule, string $message, ?array $link): array
    {
        return ['rule' => $rule, 'message' => $message, 'link' => $link];
    }
}
