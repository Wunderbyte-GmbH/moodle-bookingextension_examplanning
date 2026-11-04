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
use bookingextension_examplanning\local\distribution\distribution_result;
use bookingextension_examplanning\local\exam_calendar;
use bookingextension_examplanning\local\plan_validator;

/**
 * Tests for the checks of the changes of a draft plan, without database.
 *
 * Examiner is person 1, clerks are 2, 3 and 4. Participant 101 has clerk 2 at 09:00, 102 has clerk 3 at 09:30,
 * 103 could not be planned. All on 17 November 2026 in Vienna.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\plan_validator
 * @covers      \bookingextension_examplanning\local\exam_calendar
 */
final class plan_validator_test extends \basic_testcase {
    /**
     * A time in Vienna.
     *
     * @param string $time e.g. '09:00' on 17 November, or a full date
     * @return int
     */
    private static function at(string $time): int {
        $time = strlen($time) === 5 ? '2026-11-17 ' . $time : $time;
        return (new \DateTimeImmutable($time, new \DateTimeZone('Europe/Vienna')))->getTimestamp();
    }

    /**
     * A validator for the draft plan of the class doc.
     *
     * @param array $absences entity id => [[from, until]] as times
     * @param array $limits entity id => limit
     * @param int $gap
     * @return plan_validator
     */
    private static function validator(array $absences = [], array $limits = [], int $gap = 0): plan_validator {
        $ranges = array_map(
            static fn(array $list): array => array_map(static fn($r) => [self::at($r[0]), self::at($r[1])], $list),
            $absences
        );
        $availability = new availability($ranges, [], [], [], $limits, null, new \DateTimeZone('Europe/Vienna'));
        return new plan_validator(new exam_calendar($availability, $gap), [
            101 => ['examiner' => 1, 'clerk' => 2, 'start' => self::at('09:00'), 'end' => self::at('09:30')],
            102 => ['examiner' => 1, 'clerk' => 3, 'start' => self::at('09:30'), 'end' => self::at('10:00')],
            103 => ['examiner' => 1, 'clerk' => 4, 'start' => null, 'end' => null],
        ]);
    }

    /**
     * A slot of half an hour.
     *
     * @param string $start
     * @return int[]
     */
    private static function slot(string $start): array {
        return [self::at($start), self::at($start) + 1800];
    }

    /**
     * A free slot works, the slot of another exam of the examiner does not, the own slot does.
     *
     * @return void
     */
    public function test_move(): void {
        $validator = self::validator();

        $this->assertNull($validator->check_move(101, ...self::slot('10:00')));
        $this->assertNull($validator->check_move(101, ...self::slot('09:00')), 'The own slot is free.');
        $this->assertSame(
            ['role' => distribution_result::ROLE_EXAMINER, 'entityid' => 1, 'reason' => availability::OCCUPIED],
            $validator->check_move(101, ...self::slot('09:30'))
        );
    }

    /**
     * The targets of a move tell the own slot and the problem of each slot.
     *
     * @return void
     */
    public function test_move_targets(): void {
        $validator = self::validator([2 => [['10:00', '10:30']]]);

        $targets = $validator->move_targets(101, [self::slot('09:00'), self::slot('09:30'), self::slot('10:00')]);

        $this->assertTrue($targets[0]['current']);
        $this->assertNull($targets[0]['problem']);
        $this->assertSame(availability::OCCUPIED, $targets[1]['problem']['reason']);
        $this->assertSame(
            ['role' => distribution_result::ROLE_CLERK, 'entityid' => 2, 'reason' => availability::ABSENT],
            $targets[2]['problem']
        );
    }

    /**
     * Two participants can swap when both persons are free in the other slot, and the problem names whose slot fails.
     *
     * @return void
     */
    public function test_swap(): void {
        $this->assertNull(self::validator()->check_swap(101, 102));

        // Clerk 3 of 102 is absent at 09:00, the slot 102 would get.
        $problem = self::validator([3 => [['09:00', '09:30']]])->check_swap(101, 102);
        $this->assertSame(102, $problem['userid']);
        $this->assertSame(availability::ABSENT, $problem['reason']);
        $this->assertSame(3, $problem['entityid']);
    }

    /**
     * Only planned participants are offered for a swap.
     *
     * @return void
     */
    public function test_swap_targets_only_planned(): void {
        $this->assertSame([102 => null], self::validator()->swap_targets(101));
    }

    /**
     * A clerk who is free in the slot works, one who is absent does not.
     *
     * @return void
     */
    public function test_clerk(): void {
        $validator = self::validator([4 => [['09:00', '09:30']]]);

        $targets = $validator->clerk_targets(101, [2, 3, 4]);

        $this->assertNull($targets[2], 'The own clerk.');
        $this->assertNull($targets[3], 'Clerk 3 is at 09:30, not at 09:00.');
        $this->assertSame(availability::ABSENT, $targets[4]['reason']);

        $this->assertNull(self::validator()->check_clerk(102, 2), 'Clerk 2 ends at 09:30, when 102 starts.');
    }

    /**
     * The gap after an exam counts for a move and the daily limit counts the other exams of the plan.
     *
     * @return void
     */
    public function test_gap_and_daily_limit(): void {
        $this->assertSame(
            availability::OCCUPIED,
            self::validator([], [], 900)->check_move(101, ...self::slot('10:00'))['reason']
        );
        $this->assertNull(self::validator([], [], 900)->check_move(101, ...self::slot('10:15')));

        $this->assertSame(
            availability::DAILY_LIMIT,
            self::validator([], [1 => 1])->check_move(101, ...self::slot('11:00'))['reason']
        );
    }

    /**
     * A participant without slot can not be changed.
     *
     * @return void
     */
    public function test_unplanned_can_not_be_changed(): void {
        $this->expectException(\invalid_parameter_exception::class);
        self::validator()->check_move(103, ...self::slot('11:00'));
    }
}
