<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace bookingextension_examplanning;

use bookingextension_examplanning\local\availability;
use local_entities\unavailability\time_range;

/**
 * Tests for the check whether a person is free at a time, without database.
 *
 * Person 7 is the one that is checked. All times are on 17 and 18 November 2026 in Vienna.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\availability
 */
final class availability_test extends \basic_testcase {
    /** @var int The person that is checked. */
    private const PERSON = 7;

    /**
     * A time in Vienna.
     *
     * @param string $time e.g. '2026-11-17 09:00'
     * @return int
     */
    private static function at(string $time): int {
        return (new \DateTimeImmutable($time, new \DateTimeZone('Europe/Vienna')))->getTimestamp();
    }

    /**
     * An availability with the given data for person 7.
     *
     * @param array $data absences, occupations, teaching, exams ([from, until] as strings), limit, period
     * @return availability
     */
    private static function availability(array $data = []): availability {
        $ranges = static fn(array $list): array => [
            self::PERSON => array_map(static fn($r) => [self::at($r[0]), self::at($r[1])], $list),
        ];
        $period = isset($data['period'])
            ? new time_range(self::at($data['period'][0]), self::at($data['period'][1]))
            : null;
        return new availability(
            $ranges($data['absences'] ?? []),
            $ranges($data['occupations'] ?? []),
            $ranges($data['teaching'] ?? []),
            $ranges($data['exams'] ?? []),
            [self::PERSON => $data['limit'] ?? null],
            $period,
            new \DateTimeZone('Europe/Vienna')
        );
    }

    /**
     * Why person 7 is not free at a time.
     *
     * @param availability $availability
     * @param string $from
     * @param string $until
     * @return string|null
     */
    private static function reason(availability $availability, string $from, string $until): ?string {
        return $availability->reason(self::PERSON, self::at($from), self::at($until));
    }

    /**
     * Without anything the person is free.
     *
     * @return void
     */
    public function test_free_without_anything(): void {
        $this->assertNull(self::reason(self::availability(), '2026-11-17 09:00', '2026-11-17 09:30'));
        $this->assertNull(self::availability()->reason(99, self::at('2026-11-17 09:00'), self::at('2026-11-17 09:30')));
    }

    /**
     * Each reason is told, and ranges that only touch do not block.
     *
     * @return void
     */
    public function test_each_reason(): void {
        $availability = self::availability([
            'period' => ['2026-11-16 00:00', '2026-12-01 00:00'],
            'absences' => [['2026-11-17 08:00', '2026-11-17 09:00']],
            'occupations' => [['2026-11-17 10:00', '2026-11-17 11:00']],
            'teaching' => [['2026-11-17 12:00', '2026-11-17 13:00']],
        ]);

        $this->assertSame(availability::OUTSIDE_PERIOD, self::reason($availability, '2026-12-01 09:00', '2026-12-01 09:30'));
        $this->assertSame(availability::ABSENT, self::reason($availability, '2026-11-17 08:30', '2026-11-17 09:00'));
        $this->assertNull(self::reason($availability, '2026-11-17 09:00', '2026-11-17 09:30'), 'Touching is free.');
        $this->assertSame(availability::OCCUPIED, self::reason($availability, '2026-11-17 10:30', '2026-11-17 11:00'));
        $this->assertSame(availability::TEACHING, self::reason($availability, '2026-11-17 12:15', '2026-11-17 12:45'));
        $this->assertNull(self::reason($availability, '2026-11-17 13:00', '2026-11-17 13:30'));
    }

    /**
     * The first reason in the order period, absence, occupation, teaching, daily limit is told.
     *
     * @return void
     */
    public function test_absence_comes_before_occupation(): void {
        $availability = self::availability([
            'absences' => [['2026-11-17 09:00', '2026-11-17 10:00']],
            'occupations' => [['2026-11-17 09:00', '2026-11-17 10:00']],
        ]);

        $this->assertSame(availability::ABSENT, self::reason($availability, '2026-11-17 09:00', '2026-11-17 09:30'));
    }

    /**
     * The daily limit counts the booked exams of the day in Vienna, not of other days.
     *
     * @return void
     */
    public function test_daily_limit_counts_exams_of_the_day(): void {
        $availability = self::availability([
            'limit' => 2,
            'exams' => [
                ['2026-11-17 00:15', '2026-11-17 00:45'],
                ['2026-11-17 08:00', '2026-11-17 08:30'],
                ['2026-11-16 23:00', '2026-11-16 23:30'],
            ],
        ]);

        $this->assertSame(availability::DAILY_LIMIT, self::reason($availability, '2026-11-17 14:00', '2026-11-17 14:30'));
        $this->assertNull(self::reason($availability, '2026-11-18 09:00', '2026-11-18 09:30'));
    }

    /**
     * Without a limit any number of exams is fine.
     *
     * @return void
     */
    public function test_no_limit(): void {
        $availability = self::availability([
            'exams' => [['2026-11-17 08:00', '2026-11-17 08:30'], ['2026-11-17 09:00', '2026-11-17 09:30']],
        ]);

        $this->assertNull(self::reason($availability, '2026-11-17 14:00', '2026-11-17 14:30'));
    }

    /**
     * A reserved exam occupies its time and counts for the daily limit.
     *
     * @return void
     */
    public function test_reserved_exam_counts(): void {
        $availability = self::availability(['limit' => 1]);
        $availability->reserve(self::PERSON, self::at('2026-11-17 09:00'), self::at('2026-11-17 09:30'));

        $this->assertSame(availability::OCCUPIED, self::reason($availability, '2026-11-17 09:15', '2026-11-17 09:45'));
        $this->assertSame(availability::DAILY_LIMIT, self::reason($availability, '2026-11-17 11:00', '2026-11-17 11:30'));
        $this->assertNull(self::reason($availability, '2026-11-18 09:00', '2026-11-18 09:30'));
    }
}
