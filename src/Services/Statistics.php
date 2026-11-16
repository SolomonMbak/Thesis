<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Descriptive statistics and the Mann-Whitney U test for Chapter Four
 * (BUILD_SPEC section 15). Pure functions, no database.
 */
final class Statistics
{
    /** @return array{n:int, mean:?float, sd:?float, median:?float, min:?float, max:?float} */
    public static function describe(array $values): array
    {
        $values = array_values(array_map('floatval', $values));
        $n = count($values);
        if ($n === 0) {
            return ['n' => 0, 'mean' => null, 'sd' => null, 'median' => null, 'min' => null, 'max' => null];
        }
        return [
            'n' => $n,
            'mean' => self::mean($values),
            'sd' => self::sd($values),
            'median' => self::median($values),
            'min' => min($values),
            'max' => max($values),
        ];
    }

    public static function mean(array $values): float
    {
        return array_sum($values) / count($values);
    }

    /** Sample standard deviation (n - 1), as Excel's STDEV.S. Null for fewer than 2 values. */
    public static function sd(array $values): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $mean = self::mean($values);
        $sum = 0.0;
        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }
        return sqrt($sum / ($n - 1));
    }

    public static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        return $n % 2 === 1 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /** Average ranks (1-based) with ties sharing the mean of their positions. */
    public static function ranks(array $values): array
    {
        $indexed = [];
        foreach (array_values($values) as $i => $v) {
            $indexed[] = [$v, $i];
        }
        usort($indexed, fn ($a, $b) => $a[0] <=> $b[0]);
        $ranks = [];
        $n = count($indexed);
        for ($i = 0; $i < $n;) {
            $j = $i;
            while ($j + 1 < $n && $indexed[$j + 1][0] == $indexed[$i][0]) {
                $j++;
            }
            $average = (float) (($i + $j) / 2 + 1);
            for ($k = $i; $k <= $j; $k++) {
                $ranks[$indexed[$k][1]] = $average;
            }
            $i = $j + 1;
        }
        ksort($ranks);
        return $ranks;
    }

    /**
     * Two-sided Mann-Whitney U test, normal approximation with tie correction
     * and no continuity correction (as SPSS "Asymp. Sig.").
     *
     *   U1 = R1 - n1(n1 + 1) / 2,   U2 = n1 n2 - U1,   U = min(U1, U2)
     *   sigma = sqrt( n1 n2 / 12 x ( (n + 1) - sum(t^3 - t) / (n (n - 1)) ) )
     *   z = (U1 - n1 n2 / 2) / sigma,   p = 2 (1 - Phi(|z|))
     *
     * @return array{n1:int, n2:int, r1:float, r2:float, u1:float, u2:float, u:float, z:?float, p:?float}
     */
    public static function mannWhitney(array $a, array $b): array
    {
        $a = array_values(array_map('floatval', $a));
        $b = array_values(array_map('floatval', $b));
        $n1 = count($a);
        $n2 = count($b);
        if ($n1 === 0 || $n2 === 0) {
            return ['n1' => $n1, 'n2' => $n2, 'r1' => 0.0, 'r2' => 0.0, 'u1' => 0.0, 'u2' => 0.0, 'u' => 0.0, 'z' => null, 'p' => null];
        }
        $all = array_merge($a, $b);
        $ranks = self::ranks($all);
        $r1 = array_sum(array_slice($ranks, 0, $n1));
        $r2 = array_sum(array_slice($ranks, $n1));
        $u1 = $r1 - $n1 * ($n1 + 1) / 2;
        $u2 = $n1 * $n2 - $u1;

        $n = $n1 + $n2;
        $tieSum = 0.0;
        foreach (array_count_values(array_map('strval', $all)) as $t) {
            $tieSum += $t ** 3 - $t;
        }
        $variance = $n1 * $n2 / 12 * (($n + 1) - $tieSum / ($n * ($n - 1)));
        if ($variance <= 0) {
            return compact('n1', 'n2', 'r1', 'r2', 'u1', 'u2') + ['u' => min($u1, $u2), 'z' => 0.0, 'p' => 1.0];
        }
        $z = ($u1 - $n1 * $n2 / 2) / sqrt($variance);
        $p = min(1.0, 2 * (1 - self::normalCdf(abs($z))));
        return compact('n1', 'n2', 'r1', 'r2', 'u1', 'u2') + ['u' => min($u1, $u2), 'z' => $z, 'p' => $p];
    }

    /** Standard normal cumulative distribution. */
    public static function normalCdf(float $z): float
    {
        return 0.5 * self::erfc(-$z / M_SQRT2);
    }

    /**
     * Complementary error function, Numerical Recipes erfc approximation
     * (Chebyshev fit, fractional error below 1.2e-7 everywhere).
     */
    public static function erfc(float $x): float
    {
        $z = abs($x);
        $t = 1 / (1 + 0.5 * $z);
        $r = $t * exp(-$z * $z - 1.26551223 + $t * (1.00002368 + $t * (0.37409196 + $t * (0.09678418
            + $t * (-0.18628806 + $t * (0.27886807 + $t * (-1.13520398 + $t * (1.48851587
            + $t * (-0.82215223 + $t * 0.17087277)))))))));
        return $x >= 0 ? $r : 2 - $r;
    }
}
