<?php
declare(strict_types=1);

namespace Tests;

use App\Engine\Chromosome;
use App\Engine\FitnessEvaluator;
use App\Engine\Rng;

/**
 * Hand-built cases with known answers (BUILD_SPEC increment 3).
 * Unless stated, one day of 10 periods (index 0 = 08:00, 9 = last period),
 * no preferences, and rooms big enough.
 */
final class FitnessEvaluatorTest extends TestCase
{
    /**
     * @param array $spec    context() spec
     * @param array $genes   [[start, room], ...] per session
     */
    private function score(array $spec, array $genes): array
    {
        $data = $this->context($spec + ['days' => 1, 'perDay' => 10])->data;
        $c = new Chromosome(array_column($genes, 0), array_column($genes, 1));
        $e = (new FitnessEvaluator($data))->evaluate($c);
        return ['b' => $e->breakdown, 'e' => $e];
    }

    private function only(array $breakdown, array $expected): void
    {
        $all = ['H1' => 0, 'H2' => 0, 'H3' => 0, 'H4' => 0, 'H5' => 0, 'H6' => 0, 'S1' => 0, 'S2' => 0, 'S3' => 0, 'S4' => 0, 'S5' => 0];
        foreach ($expected + $all as $code => $value) {
            $this->assertSame($value, $breakdown[$code], "{$code}:");
        }
    }

    public function testH1SameLecturerSamePeriod(): void
    {
        $r = $this->score(
            ['rooms' => [[100], [100]], 'groups' => 2, 'sessions' => [[0, 0, 0], [0, 1, 1]]],
            [[0, 0], [0, 1]]
        );
        $this->only($r['b'], ['H1' => 1]);
    }

    public function testH2ThreeSessionsInOneRoomCountsTwo(): void
    {
        $r = $this->score(
            ['lecturers' => [[], [], []], 'groups' => 3, 'sessions' => [[0, 0, 0], [1, 1, 1], [2, 2, 2]]],
            [[0, 0], [0, 0], [0, 0]]
        );
        $this->only($r['b'], ['H2' => 2]);
    }

    public function testH3TwoHourSessionOverlapsOneHourInItsSecondHour(): void
    {
        $r = $this->score(
            ['rooms' => [[100], [100]], 'lecturers' => [[], []], 'sessions' => [[0, 0, 0, 50, false, 2], [1, 0, 1]]],
            [[0, 0], [1, 1]]
        );
        $this->only($r['b'], ['H3' => 1]);
    }

    public function testH4RoomTooSmall(): void
    {
        $r = $this->score(['rooms' => [[100]], 'sessions' => [[0, 0, 0, 150]]], [[0, 0]]);
        $this->only($r['b'], ['H4' => 1]);
    }

    public function testH5PracticalNotInLab(): void
    {
        $r = $this->score(['rooms' => [[100], [100, true]], 'sessions' => [[0, 0, 0, 50, true]]], [[0, 0]]);
        $this->only($r['b'], ['H5' => 1]);
        $ok = $this->score(['rooms' => [[100], [100, true]], 'sessions' => [[0, 0, 0, 50, true]]], [[0, 1]]);
        $this->only($ok['b'], []);
    }

    public function testH6BlockedPeriod(): void
    {
        $r = $this->score(['blocked' => [2], 'sessions' => [[0, 0, 0]]], [[2, 0]]);
        $this->only($r['b'], ['H6' => 1]);
    }

    public function testH6LecturerUnavailable(): void
    {
        $r = $this->score(['lecturers' => [['unavailable' => [3]]], 'sessions' => [[0, 0, 0]]], [[3, 0]]);
        $this->only($r['b'], ['H6' => 1]);
    }

    public function testH6CountsOncePerPeriodEvenIfBlockedAndUnavailable(): void
    {
        $r = $this->score(['blocked' => [3], 'lecturers' => [['unavailable' => [3]]], 'sessions' => [[0, 0, 0]]], [[3, 0]]);
        $this->only($r['b'], ['H6' => 1]);
    }

    public function testH6SessionRunningPastDayEnd(): void
    {
        // Two days of 10. A 2-hour session starting at the last period of day 1
        // must not spill into day 2: 1 missing period.
        $r = $this->score(['days' => 2, 'sessions' => [[0, 0, 0, 50, false, 2]]], [[9, 0]]);
        $this->assertSame(1, $r['b']['H6']);
        $this->assertSame(1, $r['b']['S5'], 'Its first hour still covers the last period.');

        // A 3-hour session starting at the last period of the last day: 2 missing.
        $last = $this->score(['days' => 2, 'sessions' => [[0, 0, 0, 50, false, 3]]], [[19, 0]]);
        $this->assertSame(2, $last['b']['H6']);
    }

    public function testS1NotPreferredOnlyWhenLecturerHasPreferences(): void
    {
        $r = $this->score(['lecturers' => [['preferred' => [0]]], 'sessions' => [[0, 0, 0, 50, false, 2]]], [[0, 0]]);
        $this->only($r['b'], ['S1' => 1]);
        $none = $this->score(['lecturers' => [[]], 'sessions' => [[0, 0, 0]]], [[4, 0]]);
        $this->only($none['b'], []);
    }

    public function testS2IdleGapBetweenClasses(): void
    {
        // Classes at periods 1 and 4 (index 0 and 3): periods 2 and 3 are idle.
        $r = $this->score(['lecturers' => [[], []], 'sessions' => [[0, 0, 0], [1, 0, 1]]], [[0, 0], [3, 0]]);
        $this->only($r['b'], ['S2' => 2]);
    }

    public function testS2BlockedPeriodIsNotAGap(): void
    {
        $r = $this->score(['blocked' => [1], 'lecturers' => [[], []], 'sessions' => [[0, 0, 0], [1, 0, 1]]], [[0, 0], [3, 0]]);
        $this->only($r['b'], ['S2' => 1]);
    }

    public function testS3TwoSessionsOfACourseOnOneDay(): void
    {
        $r = $this->score(['courses' => 1, 'sessions' => [[0, 0, 0], [0, 0, 0]]], [[0, 0], [1, 0]]);
        $this->only($r['b'], ['S3' => 1]);
        $spread = $this->score(['days' => 2, 'courses' => 1, 'sessions' => [[0, 0, 0], [0, 0, 0]]], [[0, 0], [10, 0]]);
        $this->only($spread['b'], []);
    }

    public function testS4FiveBackToBackHours(): void
    {
        $sessions = [];
        $genes = [];
        for ($i = 0; $i < 5; $i++) {
            $sessions[] = [$i, 0, $i];
            $genes[] = [$i, 0];
        }
        $r = $this->score(['lecturers' => array_fill(0, 5, []), 'sessions' => $sessions], $genes);
        $this->only($r['b'], ['S4' => 2]);
    }

    public function testS5LastPeriod(): void
    {
        $r = $this->score(['sessions' => [[0, 0, 0]]], [[9, 0]]);
        $this->only($r['b'], ['S5' => 1]);
    }

    public function testPenaltyAndFitnessWithDefaultWeights(): void
    {
        // H1 = 1 and S5 = 1: penalty = 1000 x 1 + 2 x 1 = 1002.
        $r = $this->score(
            ['rooms' => [[100], [100]], 'groups' => 2, 'sessions' => [[0, 0, 0], [0, 1, 1]]],
            [[9, 0], [9, 1]]
        );
        $e = $r['e'];
        $this->assertSame(1, $e->hard);
        $this->assertSame(4, $e->soft, 'Both sessions cover the last period: 2 x S5 weight 2.');
        $this->assertSame(1004, $e->penalty);
        $this->assertSame(1 / 1005, $e->fitness);
        $this->assertFalse($e->isFeasible());

        $clean = $this->score(['sessions' => [[0, 0, 0]]], [[0, 0]])['e'];
        $this->assertSame(0, $clean->penalty);
        $this->assertSame(1.0, $clean->fitness);
        $this->assertTrue($clean->isFeasible());
    }

    public function testCustomWeightsAreUsed(): void
    {
        $r = $this->score(['weights' => ['H' => 50, 'S1' => 1, 'S2' => 1, 'S3' => 1, 'S4' => 1, 'S5' => 7], 'sessions' => [[0, 0, 0, 150]]], [[9, 0]]);
        $this->assertSame(57, $r['e']->penalty);
    }

    public function testExplainAgreesWithEvaluate(): void
    {
        // Random timetables on a busy 2-day problem: per-code totals must match.
        $sessions = [];
        for ($i = 0; $i < 30; $i++) {
            $sessions[] = [$i % 4, $i % 3, $i % 12, 40 + ($i % 5) * 20, $i % 7 === 0, 1 + ($i % 3)];
        }
        $data = $this->context([
            'days' => 2, 'perDay' => 6, 'blocked' => [5], 'groups' => 3, 'courses' => 12,
            'rooms' => [[60], [100], [80, true]],
            'lecturers' => [['unavailable' => [1, 7], 'preferred' => [0, 2]], [], ['preferred' => [3]], ['unavailable' => [10]]],
            'sessions' => $sessions,
        ])->data;
        $evaluator = new FitnessEvaluator($data);
        $rng = new Rng(7);
        for ($round = 0; $round < 20; $round++) {
            $start = $room = [];
            foreach ($sessions as $i => $s) {
                $start[] = $rng->int(0, $data->P - 1);
                $room[] = $rng->int(0, $data->R - 1);
            }
            $c = new Chromosome($start, $room);
            $totals = array_fill_keys(['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'S1', 'S2', 'S3', 'S4', 'S5'], 0);
            foreach ($evaluator->explain($c) as $item) {
                $totals[$item['code']] += $item['count'];
            }
            $this->assertSame($evaluator->evaluate($c)->breakdown, $totals, "Round {$round}:");
        }
    }

    public function testEvaluationIsCachedUntilAGeneChanges(): void
    {
        $data = $this->context(['days' => 1, 'perDay' => 10, 'sessions' => [[0, 0, 0]]])->data;
        $evaluator = new FitnessEvaluator($data);
        $c = new Chromosome([9], [0]);
        $first = $evaluator->evaluate($c);
        $this->assertTrue($first === $evaluator->evaluate($c), 'Second call should return the cached object.');
        $c->setGene(0, 0, 0);
        $this->assertSame(0, $evaluator->evaluate($c)->penalty);
    }

    public function testBenchmarkEvaluationSpeed(): void
    {
        // Informational (BUILD_SPEC 8.8): about 150 sessions, 5 days x 10, 8 rooms.
        $rng = new Rng(42);
        $sessions = [];
        for ($i = 0; $i < 150; $i++) {
            $sessions[] = [$i % 14, $i % 4, intdiv($i, 3), 60, $i % 25 === 0, $i % 25 === 0 ? 3 : 1];
        }
        $data = $this->context([
            'days' => 5, 'perDay' => 10, 'groups' => 4, 'courses' => 50,
            'rooms' => [[60], [80], [100], [120], [150], [200], [60, true], [60, true]],
            'lecturers' => array_fill(0, 14, ['preferred' => [1, 2, 3]]),
            'sessions' => $sessions,
        ])->data;
        $evaluator = new FitnessEvaluator($data);
        $chromosomes = [];
        for ($k = 0; $k < 50; $k++) {
            $start = $room = [];
            for ($s = 0; $s < 150; $s++) {
                $start[] = $rng->pick($data->allowedStarts[$s]);
                $room[] = $rng->pick($data->allowedRooms[$s]);
            }
            $chromosomes[] = new Chromosome($start, $room);
        }
        $runs = 2000;
        $t = hrtime(true);
        for ($i = 0; $i < $runs; $i++) {
            $c = $chromosomes[$i % 50];
            $c->invalidate();
            $evaluator->evaluate($c);
        }
        $ms = (hrtime(true) - $t) / 1e6 / $runs;
        echo '        benchmark: ' . number_format($ms, 4) . " ms per evaluation (150 sessions)\n";
        $this->assertTrue($ms < 50, 'Evaluation should take well under 50 ms.');
    }
}
