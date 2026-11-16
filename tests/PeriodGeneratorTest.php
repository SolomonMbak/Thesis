<?php
declare(strict_types=1);

namespace Tests;

use App\Services\PeriodGenerator;

final class PeriodGeneratorTest extends TestCase
{
    public function testDefaultDayHasTenOneHourPeriods(): void
    {
        $plan = PeriodGenerator::dayPlan('08:00', '18:00', 60);
        $this->assertCount(10, $plan);
        $this->assertSame(['start' => '08:00', 'end' => '09:00'], $plan[1]);
        $this->assertSame(['start' => '17:00', 'end' => '18:00'], $plan[10]);
    }

    public function testPartialLastPeriodIsDropped(): void
    {
        $this->assertCount(4, PeriodGenerator::dayPlan('08:00', '14:30', 90));
    }

    public function testInvalidDayIsRejected(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, fn () => PeriodGenerator::dayPlan('18:00', '08:00', 60));
        $this->assertThrows(\InvalidArgumentException::class, fn () => PeriodGenerator::dayPlan('08:00', '18:00', 5));
    }

    public function testFridayPrayerBlockIsTwelveToTwo(): void
    {
        $this->assertTrue(PeriodGenerator::isJumuah(5, '12:00', '13:00'));
        $this->assertTrue(PeriodGenerator::isJumuah(5, '13:00', '14:00'));
        $this->assertFalse(PeriodGenerator::isJumuah(5, '14:00', '15:00'));
        $this->assertFalse(PeriodGenerator::isJumuah(4, '12:00', '13:00'));
    }

    public function testGenerateCreates50PeriodsWith2Blocked(): void
    {
        $pdo = $this->singleSchoolDb();
        $result = (new PeriodGenerator($pdo, $this->school))->generate('08:00', '18:00', 60);
        $this->assertSame(50, $result['created']);
        $this->assertSame(2, (int) $pdo->query('SELECT SUM(is_blocked) FROM timeslots')->fetchColumn());

        $pdo->exec('UPDATE timeslots SET is_blocked = 1 WHERE day_of_week = 1 AND period_no = 1');
        $again = (new PeriodGenerator($pdo, $this->school))->generate('08:00', '16:00', 60);
        $this->assertSame(10, $again['removed']);
        $this->assertSame(3, (int) $pdo->query('SELECT SUM(is_blocked) FROM timeslots')->fetchColumn(), 'Blocked flags are kept.');
        $this->dropAllTables($pdo);
    }
}
