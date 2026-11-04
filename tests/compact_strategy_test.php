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
use bookingextension_examplanning\local\distribution\compact_strategy;
use bookingextension_examplanning\local\distribution\distribution_result;

/**
 * Tests for the compact distribution of the participants over the slots, without database.
 *
 * Examiner is person 1, clerks are 2 and 3. Participants are users 101 to 104. The slots are half an hour from
 * 09:00 on 17 November 2026 in Vienna.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\distribution\compact_strategy
 * @covers      \bookingextension_examplanning\local\distribution\distribution_result
 */
final class compact_strategy_test extends \basic_testcase {
    /** @var int The examiner. */
    private const EXAMINER = 1;

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
     * Slots of half an hour, one after the other.
     *
     * @param string $first start of the first slot
     * @param int $count
     * @return int[][]
     */
    private static function slots(string $first, int $count): array {
        $slots = [];
        for ($i = 0; $i < $count; $i++) {
            $start = self::at($first) + $i * 1800;
            $slots[] = [$start, $start + 1800];
        }
        return $slots;
    }

    /**
     * An availability with absences and limits.
     *
     * @param array $absences entity id => [[from, until]] as strings
     * @param array $limits entity id => limit
     * @return availability
     */
    private static function availability(array $absences = [], array $limits = []): availability {
        $ranges = array_map(
            static fn(array $list): array => array_map(static fn($r) => [self::at($r[0]), self::at($r[1])], $list),
            $absences
        );
        return new availability($ranges, [], [], [], $limits, null, new \DateTimeZone('Europe/Vienna'));
    }

    /**
     * The start of each planned exam, as time in Vienna.
     *
     * @param distribution_result $result
     * @return array user id => 'H:i'
     */
    private static function starts(distribution_result $result): array {
        $tz = new \DateTimeZone('Europe/Vienna');
        return array_map(
            static fn(array $slot): string => (new \DateTimeImmutable('@' . $slot[0]))->setTimezone($tz)->format('H:i'),
            $result->get_planned()
        );
    }

    /**
     * The exams follow each other from the first slot on.
     *
     * @return void
     */
    public function test_exams_are_planned_as_early_as_possible(): void {
        $result = (new compact_strategy())->distribute(
            self::EXAMINER,
            [101 => 2, 102 => 3, 103 => 2],
            self::slots('2026-11-17 09:00', 6),
            0,
            self::availability()
        );

        $this->assertSame([101 => '09:00', 102 => '09:30', 103 => '10:00'], self::starts($result));
        $this->assertSame([], $result->get_unplanned());
    }

    /**
     * The participant whose clerk is free in few slots comes first and still gets one.
     *
     * @return void
     */
    public function test_participant_with_fewest_free_slots_comes_first(): void {
        // Clerk 3 is only free at 09:00. Without the order, 101 would take it.
        $availability = self::availability([3 => [['2026-11-17 09:30', '2026-11-17 12:00']]]);

        $result = (new compact_strategy())->distribute(
            self::EXAMINER,
            [101 => 2, 102 => 3],
            self::slots('2026-11-17 09:00', 3),
            0,
            $availability
        );

        $this->assertSame([102 => '09:00', 101 => '09:30'], self::starts($result));
    }

    /**
     * The buffer of the option keeps a gap after each exam of a person.
     *
     * @return void
     */
    public function test_gap_after_an_exam(): void {
        $result = (new compact_strategy())->distribute(
            self::EXAMINER,
            [101 => 2, 102 => 3],
            self::slots('2026-11-17 09:00', 4),
            900,
            self::availability()
        );

        $this->assertSame([101 => '09:00', 102 => '10:00'], self::starts($result));
    }

    /**
     * Without clerk, examiner or slots the participant gets the matching reason.
     *
     * @return void
     */
    public function test_reasons_without_clerk_examiner_or_slots(): void {
        $strategy = new compact_strategy();
        $slots = self::slots('2026-11-17 09:00', 2);

        $result = $strategy->distribute(self::EXAMINER, [101 => 0], $slots, 0, self::availability());
        $this->assertSame(distribution_result::NO_CLERK, $result->get_unplanned()[101][0]['reason']);

        $result = $strategy->distribute(null, [101 => 2], $slots, 0, self::availability());
        $this->assertSame(distribution_result::NO_EXAMINER, $result->get_unplanned()[101][0]['reason']);

        $result = $strategy->distribute(self::EXAMINER, [101 => 2], [], 0, self::availability());
        $this->assertSame(distribution_result::NO_SLOTS, $result->get_unplanned()[101][0]['reason']);
    }

    /**
     * Without a common free slot the reasons say who is not free how often, most often first.
     *
     * @return void
     */
    public function test_reasons_count_per_person(): void {
        $availability = self::availability([
            self::EXAMINER => [['2026-11-17 09:00', '2026-11-17 09:30']],
            2 => [['2026-11-17 09:30', '2026-11-17 11:00']],
        ]);

        $result = (new compact_strategy())->distribute(
            self::EXAMINER,
            [101 => 2],
            self::slots('2026-11-17 09:00', 3),
            0,
            $availability
        );

        $this->assertSame([], $result->get_planned());
        $this->assertSame([
            ['reason' => availability::ABSENT, 'role' => distribution_result::ROLE_CLERK, 'count' => 2],
            ['reason' => availability::ABSENT, 'role' => distribution_result::ROLE_EXAMINER, 'count' => 1],
        ], $result->get_unplanned()[101]);
    }

    /**
     * The daily limit of the examiner moves the rest to the next day.
     *
     * @return void
     */
    public function test_daily_limit_moves_to_next_day(): void {
        $slots = array_merge(self::slots('2026-11-17 09:00', 3), self::slots('2026-11-18 09:00', 3));

        $result = (new compact_strategy())->distribute(
            self::EXAMINER,
            [101 => 2, 102 => 3, 103 => 2],
            $slots,
            0,
            self::availability([], [self::EXAMINER => 2])
        );

        $days = array_map(
            static fn(array $s): string => (new \DateTimeImmutable('@' . $s[0]))
                ->setTimezone(new \DateTimeZone('Europe/Vienna'))->format('d.m.'),
            $result->get_planned()
        );
        $this->assertSame([101 => '17.11.', 102 => '17.11.', 103 => '18.11.'], $days);
    }

    /**
     * The same seed gives the same plan, another seed may change the order at a tie, and all are planned.
     *
     * @return void
     */
    public function test_seed_shuffles_at_a_tie_and_is_repeatable(): void {
        $run = static fn(?int $seed): array => self::starts((new compact_strategy())->distribute(
            self::EXAMINER,
            [101 => 2, 102 => 3, 103 => 2, 104 => 3],
            self::slots('2026-11-17 09:00', 8),
            0,
            self::availability(),
            $seed
        ));

        $this->assertSame($run(42), $run(42));
        $orders = [];
        foreach (range(1, 20) as $seed) {
            $planned = $run($seed);
            $this->assertCount(4, $planned);
            $orders[implode(',', array_keys($planned))] = true;
        }
        $this->assertGreaterThan(1, count($orders), 'Different seeds give different orders.');
    }
}
