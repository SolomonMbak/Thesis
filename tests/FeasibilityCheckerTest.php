<?php
declare(strict_types=1);

namespace Tests;

use App\Services\FeasibilityChecker;

/**
 * Each rule of BUILD_SPEC section 7 fails when it should and passes otherwise.
 * One day of 4 periods keeps the numbers easy to follow.
 */
final class FeasibilityCheckerTest extends TestCase
{
    private function rules(array $spec): array
    {
        $failures = (new FeasibilityChecker())->check($this->context($spec + ['days' => 1, 'perDay' => 4]));
        return array_values(array_unique(array_column($failures, 'rule')));
    }

    public function testPassesWhenEverythingFits(): void
    {
        $this->assertSame([], $this->rules([
            'rooms' => [[100]],
            'sessions' => [[0, 0, 0], [0, 0, 1]],
        ]));
    }

    public function testNoSessionsFails(): void
    {
        $this->assertSame([0], $this->rules(['sessions' => []]));
    }

    public function testRule1TooFewRoomPeriods(): void
    {
        // 2 groups x 3 hours = 6 room-periods, 1 room x 4 periods = 4.
        $sessions = [];
        for ($i = 0; $i < 6; $i++) {
            $sessions[] = [$i % 2, $i % 2, $i];
        }
        $rules = $this->rules(['rooms' => [[100]], 'lecturers' => [[], []], 'groups' => 2, 'sessions' => $sessions]);
        $this->assertTrue(in_array(1, $rules, true), 'Rule 1 should fail.');
        $this->assertFalse(in_array(1, $this->rules(['rooms' => [[100], [100]], 'lecturers' => [[], []], 'groups' => 2,
            'sessions' => $sessions]), true), 'Two rooms give 8 room-periods.');
    }

    public function testRule2TooFewLabPeriods(): void
    {
        $sessions = [[0, 0, 0, 30, true, 3], [1, 1, 1, 30, true, 3]];
        $rules = $this->rules(['rooms' => [[100], [40, true]], 'lecturers' => [[], []], 'groups' => 2, 'sessions' => $sessions]);
        $this->assertTrue(in_array(2, $rules, true), '6 lab hours but only 4 lab-periods.');
        $this->assertFalse(in_array(2, $this->rules(['rooms' => [[100], [40, true], [40, true]], 'lecturers' => [[], []],
            'groups' => 2, 'sessions' => $sessions]), true));
    }

    public function testRule2InactiveLabDoesNotCount(): void
    {
        $rules = $this->rules(['rooms' => [[100], [40, true, false]], 'sessions' => [[0, 0, 0, 30, true, 2]]]);
        $this->assertTrue(in_array(2, $rules, true));
    }

    public function testRule3LecturerNotAvailableEnough(): void
    {
        $spec = [
            'rooms' => [[100], [100]],
            'lecturers' => [['unavailable' => [0, 1]]],
            'groups' => 2,
            'sessions' => [[0, 0, 0], [0, 0, 1], [0, 1, 2]],
        ];
        $failures = (new FeasibilityChecker())->check($this->context($spec + ['days' => 1, 'perDay' => 4]));
        $rule3 = array_values(array_filter($failures, fn ($f) => $f['rule'] === 3));
        $this->assertCount(1, $rule3);
        $this->assertSame('Lecturer 1 needs 3 teaching periods but is available for only 2.', $rule3[0]['message']);

        $spec['lecturers'] = [['unavailable' => [0]]];
        $this->assertFalse(in_array(3, $this->rules($spec), true), '3 periods needed, 3 available.');
    }

    public function testRule3BlockedPeriodsAreNotAvailable(): void
    {
        $rules = $this->rules(['blocked' => [0, 1, 2], 'rooms' => [[100]], 'sessions' => [[0, 0, 0], [0, 0, 1]]]);
        $this->assertTrue(in_array(3, $rules, true));
    }

    public function testRule4GroupHasTooManyHours(): void
    {
        $sessions = [];
        for ($i = 0; $i < 5; $i++) {
            $sessions[] = [$i, 0, $i];
        }
        $lecturers = array_fill(0, 5, []);
        $this->assertTrue(in_array(4, $this->rules(['rooms' => [[100], [100]], 'lecturers' => $lecturers, 'sessions' => $sessions]), true));
        array_pop($sessions);
        $this->assertFalse(in_array(4, $this->rules(['rooms' => [[100], [100]], 'lecturers' => $lecturers, 'sessions' => $sessions]), true));
    }

    public function testRule5NoSuitableRoom(): void
    {
        $this->assertSame([5], $this->rules(['rooms' => [[40]], 'sessions' => [[0, 0, 0, 50]]]), 'Room too small.');
        $this->assertSame([2, 5], $this->rules(['rooms' => [[100]], 'sessions' => [[0, 0, 0, 50, true]]]),
            'Practical needs a lab (with no lab at all, rule 2 fails too).');
        $this->assertSame([5], $this->rules(['rooms' => [[100], [40, true]], 'sessions' => [[0, 0, 0, 50, true]]]), 'Lab too small.');
        $this->assertSame([], $this->rules(['rooms' => [[100, true]], 'sessions' => [[0, 0, 0, 50, true]]]));
    }

    public function testRule5NoValidStart(): void
    {
        // A 3-hour session; the lecturer is out at period 1, leaving no 3-period run in a 4-period day.
        $rules = $this->rules(['rooms' => [[100]], 'lecturers' => [['unavailable' => [1]]], 'sessions' => [[0, 0, 0, 50, false, 3]]]);
        $this->assertSame([5], $rules);
        $this->assertSame([], $this->rules(['rooms' => [[100]], 'lecturers' => [['unavailable' => [0]]],
            'sessions' => [[0, 0, 0, 50, false, 3]]]), 'Periods 1 to 3 are still a valid run.');
    }

    public function testRule5SessionCannotCrossDays(): void
    {
        // Two days of 2 periods: a 3-hour block never fits in one day.
        $failures = (new FeasibilityChecker())->check($this->context([
            'days' => 2, 'perDay' => 2, 'rooms' => [[100]], 'sessions' => [[0, 0, 0, 50, false, 3]],
        ]));
        $this->assertSame([5], array_values(array_unique(array_column($failures, 'rule'))));
    }

    public function testReportsEveryFailureNotJustTheFirst(): void
    {
        $failures = (new FeasibilityChecker())->check($this->context([
            'days' => 1, 'perDay' => 4, 'rooms' => [[10]],
            'sessions' => [[0, 0, 0, 50], [0, 0, 1, 50]],
        ]));
        $this->assertCount(2, $failures, 'Both sessions lack a room and both must be listed.');
    }
}
