<?php
declare(strict_types=1);

namespace Tests;

use App\Services\Statistics;

/**
 * Expected values worked by hand (shown in comments) so they can be
 * checked in a spreadsheet.
 */
final class StatisticsTest extends TestCase
{
    private function near(float $expected, ?float $actual, float $tolerance, string $what): void
    {
        $this->assertTrue($actual !== null && abs($expected - $actual) <= $tolerance,
            "{$what}: expected {$expected}, got " . var_export($actual, true));
    }

    public function testDescriptives(): void
    {
        $d = Statistics::describe([2, 4, 4, 4, 5, 5, 7, 9]);
        $this->assertSame(8, $d['n']);
        $this->near(5.0, $d['mean'], 1e-12, 'mean');
        $this->near(2.138089935, $d['sd'], 1e-9, 'sample SD (Excel STDEV.S)');
        $this->near(4.5, $d['median'], 1e-12, 'median');
        $this->near(2.0, $d['min'], 0, 'min');
        $this->near(9.0, $d['max'], 0, 'max');
        $this->near(3.0, Statistics::median([5, 1, 3]), 0, 'odd median');
        $this->assertSame(null, Statistics::describe([])['mean']);
    }

    public function testRanksAverageTies(): void
    {
        // Sorted: 1, 2, 2, 2, 3, 3, 4, 5 -> ranks 1, 3, 3, 3, 5.5, 5.5, 7, 8
        $this->assertSame([1.0, 3.0, 3.0, 5.5, 3.0, 5.5, 7.0, 8.0], array_values(Statistics::ranks([1, 2, 2, 3, 2, 3, 4, 5])));
    }

    public function testMannWhitneyNoTies(): void
    {
        // R1 = 15, U1 = 0, U2 = 25; sigma = sqrt(25/12 x 11) = 4.78714; z = -12.5 / 4.78714 = -2.61116; p = 0.009023
        $r = Statistics::mannWhitney([1, 2, 3, 4, 5], [6, 7, 8, 9, 10]);
        $this->near(15.0, $r['r1'], 0, 'R1');
        $this->near(0.0, $r['u'], 0, 'U');
        $this->near(-2.611165, $r['z'], 1e-5, 'z');
        $this->near(0.009023, $r['p'], 2e-6, 'p');
    }

    public function testMannWhitneyWithTies(): void
    {
        // a ranks 1 + 3 + 3 + 5.5 = 12.5; U1 = 2.5, U2 = 13.5
        // ties: t = 3 (value 2) and t = 2 (value 3): sum(t^3 - t) = 24 + 6 = 30
        // variance = 16/12 x (9 - 30/56) = 11.285714; z = (2.5 - 8) / 3.359421 = -1.637187; p = 0.10159
        $r = Statistics::mannWhitney([1, 2, 2, 3], [2, 3, 4, 5]);
        $this->near(2.5, $r['u1'], 0, 'U1');
        $this->near(13.5, $r['u2'], 0, 'U2');
        $this->near(-1.637187, $r['z'], 1e-5, 'z');
        $this->near(0.10159, $r['p'], 2e-4, 'p');
    }

    public function testMannWhitneyIsSymmetric(): void
    {
        $a = [12, 15, 15, 18, 20, 21];
        $b = [14, 16, 22, 25, 25, 30, 31];
        $ab = Statistics::mannWhitney($a, $b);
        $ba = Statistics::mannWhitney($b, $a);
        $this->near($ab['p'], $ba['p'], 1e-12, 'p');
        $this->near($ab['u'], $ba['u'], 0, 'U');
        $this->near(-$ab['z'], $ba['z'], 1e-12, 'z');
    }

    public function testAllValuesTiedGivesPOne(): void
    {
        $this->near(1.0, Statistics::mannWhitney([3, 3, 3], [3, 3])['p'], 0, 'p');
    }

    public function testNormalCdf(): void
    {
        $this->near(0.5, Statistics::normalCdf(0), 1e-7, 'Phi(0)');
        $this->near(0.975002, Statistics::normalCdf(1.96), 1e-6, 'Phi(1.96)');
        $this->near(0.0013499, Statistics::normalCdf(-3), 1e-6, 'Phi(-3)');
    }
}
