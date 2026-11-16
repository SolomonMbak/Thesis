<?php
declare(strict_types=1);

namespace Tests;

use App\Services\CalendarService;

/**
 * Monthly calendar view (BUILD_SPEC 10.7): the weekly timetable laid onto
 * real dates, with weekends, days outside the semester and holidays left out.
 */
final class CalendarServiceTest extends TestCase
{
    private const PERIODS = [1 => ['start' => '08:00', 'end' => '09:00'], 2 => ['start' => '09:00', 'end' => '10:00'],
        3 => ['start' => '10:00', 'end' => '11:00']];

    /** A Monday class at 08:00 for 2 hours and a Wednesday class at 10:00. */
    private function entries(): array
    {
        return [
            ['day' => 1, 'period_no' => 1, 'duration' => 2, 'start_time' => '08:00', 'course_code' => 'CSC 101', 'room_code' => 'LT1', 'group_code' => '100L'],
            ['day' => 3, 'period_no' => 3, 'duration' => 1, 'start_time' => '10:00', 'course_code' => 'GST 101', 'room_code' => 'LT2', 'group_code' => '100L'],
        ];
    }

    private function cell(array $month, string $date): array
    {
        foreach ($month['weeks'] as $week) {
            foreach ($week as $cell) {
                if ($cell['date'] === $date) {
                    return $cell;
                }
            }
        }
        throw new AssertionFailed("No cell for {$date}");
    }

    public function testMonthLaysTheWeekOntoRealDates(): void
    {
        // March 2026 starts on a Sunday; teaching from Mon 2 to Fri 27 March, Fri 20 March is a holiday.
        $semester = ['start_date' => '2026-03-02', 'end_date' => '2026-03-27'];
        $month = CalendarService::month(2026, 3, $this->entries(), self::PERIODS, $semester, ['2026-03-20' => 'Eid al-Fitr']);

        $this->assertSame('March 2026', $month['label']);
        $this->assertSame('2026-02', $month['prev']);
        $this->assertSame('2026-04', $month['next']);
        $this->assertCount(5, $month['weeks'][0], 'Weeks have Monday to Friday only.');

        $monday = $this->cell($month, '2026-03-02');
        $this->assertSame('teaching', $monday['status']);
        $this->assertSame('CSC 101', $monday['items'][0]['course_code']);
        $this->assertSame('10:00', $monday['items'][0]['end_time'], 'A 2-hour class ends at the end of its second period.');
        $this->assertSame('GST 101', $this->cell($month, '2026-03-04')['items'][0]['course_code']);
        $this->assertSame([], $this->cell($month, '2026-03-03')['items'], 'Tuesday has no classes in this week.');

        $holiday = $this->cell($month, '2026-03-20');
        $this->assertSame('closed', $holiday['status']);
        $this->assertSame('Eid al-Fitr', $holiday['reason']);
        $this->assertSame([], $holiday['items']);

        $this->assertSame('outside', $this->cell($month, '2026-03-30')['status'], 'After the last day of teaching.');
        $this->assertSame(19, $month['teaching_days'], '20 weekdays from 2 to 27 March, minus the holiday.');
        $this->assertSame(1, $month['closed_days']);
    }

    public function testWithoutSemesterDatesEveryWeekdayShowsTheWeek(): void
    {
        $month = CalendarService::month(2026, 3, $this->entries(), self::PERIODS, ['start_date' => null, 'end_date' => null], []);
        $this->assertFalse($month['has_dates']);
        $this->assertSame('no_dates', $this->cell($month, '2026-03-30')['status']);
        $this->assertSame('CSC 101', $this->cell($month, '2026-03-30')['items'][0]['course_code']);
    }

    public function testOccurrencesListEveryClassOnItsDateWithoutHolidays(): void
    {
        $semester = ['start_date' => '2026-03-02', 'end_date' => '2026-03-13'];
        $rows = CalendarService::occurrences($this->entries(), self::PERIODS, $semester, ['2026-03-09' => 'Holiday']);
        $this->assertSame(['2026-03-02', '2026-03-04', '2026-03-11'], array_column($rows, 'date'),
            'Two Mondays and two Wednesdays, minus the holiday on Monday 9 March.');
        $this->assertSame([], CalendarService::occurrences($this->entries(), self::PERIODS, ['start_date' => null, 'end_date' => null], []));
    }

    public function testPickMonthPrefersTheRequestThenTodayThenTheSemesterStart(): void
    {
        $semester = ['start_date' => '2026-01-12', 'end_date' => '2026-04-30'];
        $this->assertSame([2026, 2], CalendarService::pickMonth('2026-02', $semester));
        $this->assertSame([2026, 3], CalendarService::pickMonth(null, $semester, strtotime('2026-03-15')));
        $this->assertSame([2026, 1], CalendarService::pickMonth(null, $semester, strtotime('2026-11-16')), 'Outside the semester: its first month.');
        $this->assertSame([2026, 1], CalendarService::pickMonth('2026-13', $semester, strtotime('2026-11-16')), 'A bad month is ignored.');
    }
}
