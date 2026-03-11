<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DateParsingTest extends TestCase
{
    // --- parse_date_input() tests ---

    public function testParseDateInputFullDate(): void
    {
        $this->assertSame('20240115', parse_date_input('1/15/2024'));
        $this->assertSame('20241231', parse_date_input('12/31/2024'));
    }

    public function testParseDateInputWithDashes(): void
    {
        $this->assertSame('20240115', parse_date_input('1-15-2024'));
        $this->assertSame('20241231', parse_date_input('12-31-2024'));
    }

    public function testParseDateInputTwoDigitYear(): void
    {
        $this->assertSame('20240601', parse_date_input('6/1/24'));
        $this->assertSame('20000101', parse_date_input('1/1/00'));
        // 2-digit year 99 becomes 2099, then > 2050 rule shifts to 1999
        $this->assertSame('19991231', parse_date_input('12/31/99'));
    }

    public function testParseDateInputYearOver2050(): void
    {
        // Years > 2050 should be shifted back 100 years (1990s convention)
        $this->assertSame('19960601', parse_date_input('6/1/2096'));
    }

    public function testParseDateInputMonthDayOnly(): void
    {
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');

        // A month in the past (or current) should use current year
        $pastMonth = max(1, $currentMonth - 1);
        $result = parse_date_input("$pastMonth/15");
        $this->assertSame(sprintf('%04d%02d15', $currentYear, $pastMonth), $result);

        // A month in the future should use last year
        if ($currentMonth < 12) {
            $futureMonth = $currentMonth + 1;
            $result = parse_date_input("$futureMonth/15");
            $this->assertSame(sprintf('%04d%02d15', $currentYear - 1, $futureMonth), $result);
        }
    }

    public function testParseDateInputDayOnly(): void
    {
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');
        $result = parse_date_input('15');
        $this->assertSame(sprintf('%04d%02d15', $currentYear, $currentMonth), $result);
    }

    public function testParseDateInputEmptyString(): void
    {
        $this->assertSame('', parse_date_input(''));
        $this->assertSame('', parse_date_input('  '));
    }

    public function testParseDateInputInvalidMonth(): void
    {
        $this->assertSame('', parse_date_input('13/1/2024'));
        $this->assertSame('', parse_date_input('0/1/2024'));
    }

    public function testParseDateInputInvalidDay(): void
    {
        $this->assertSame('', parse_date_input('1/0/2024'));
        $this->assertSame('', parse_date_input('1/32/2024'));
    }

    // --- date_to_str() tests ---

    public function testDateToStrDefaultFormat(): void
    {
        $result = date_to_str('20240115', '__mm__/__dd__/__yyyy__', false);
        $this->assertSame('1/15/2024', $result);
    }

    public function testDateToStrWithWeekday(): void
    {
        $result = date_to_str('20240115', '__mm__/__dd__/__yyyy__', true);
        // Jan 15, 2024 is a Monday
        $this->assertStringContainsString('Monday', $result);
        $this->assertStringContainsString('1/15/2024', $result);
    }

    public function testDateToStrMonthFormat(): void
    {
        $result = date_to_str('20240315', '__month__ __dd__, __yyyy__', false);
        $this->assertStringContainsString('March', $result);
        $this->assertStringContainsString('15', $result);
        $this->assertStringContainsString('2024', $result);
    }

    public function testDateToStrEmptyDateUsesCurrentDate(): void
    {
        $result = date_to_str('', '__mm__/__dd__/__yyyy__', false);
        $expected = date('n') . '/' . date('j') . '/' . date('Y');
        $this->assertSame($expected, $result);
    }

    // --- shiftDate() tests ---

    public function testShiftDateForward(): void
    {
        $this->assertSame('20240116', shiftDate('20240115', 1));
        $this->assertSame('20240125', shiftDate('20240115', 10));
    }

    public function testShiftDateBackward(): void
    {
        $this->assertSame('20240114', shiftDate('20240115', -1));
        $this->assertSame('20240105', shiftDate('20240115', -10));
    }

    public function testShiftDateAcrossMonthBoundary(): void
    {
        $this->assertSame('20240201', shiftDate('20240131', 1));
        $this->assertSame('20240131', shiftDate('20240201', -1));
    }

    public function testShiftDateAcrossYearBoundary(): void
    {
        $this->assertSame('20250101', shiftDate('20241231', 1));
        $this->assertSame('20231231', shiftDate('20240101', -1));
    }

    public function testShiftDateLeapYear(): void
    {
        $this->assertSame('20240229', shiftDate('20240228', 1)); // 2024 is leap year
        $this->assertSame('20230301', shiftDate('20230228', 1)); // 2023 is not
    }

    public function testShiftDateZeroDays(): void
    {
        $this->assertSame('20240115', shiftDate('20240115', 0));
    }
}
