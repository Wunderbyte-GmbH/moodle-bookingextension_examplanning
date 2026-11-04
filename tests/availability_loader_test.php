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
use bookingextension_examplanning\local\availability_loader;
use bookingextension_examplanning\local\occupations;
use local_entities\daily_limit;
use local_entities\persons;
use local_entities\planning_period;
use local_entities\unavailability;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests that the loader reads absences, occupations, teaching dates, exams, limits and the period from the database.
 *
 * All times are on 17 November 2026 in Vienna, the planning period is 16 to 30 November.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\availability_loader
 */
final class availability_loader_test extends \advanced_testcase {
    /** @var int The option that is planned. */
    private int $examoptionid = 0;

    /** @var int Another option of the same booking instance. */
    private int $otheroptionid = 0;

    /** @var int The user of the person. */
    private int $userid = 0;

    /** @var int The person that is checked. */
    private int $entityid = 0;

    /**
     * Creates two options, a person and the planning period.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setTimezone('Europe/Vienna');

        $course = self::getDataGenerator()->create_course();
        /** @var \mod_booking_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $booking = $generator->create_instance(['course' => $course->id]);
        $this->examoptionid = (int)$generator->create_option((object)[
            'bookingid' => $booking->id, 'text' => 'Exam', 'course' => $course->id,
        ])->id;
        $this->otheroptionid = (int)$generator->create_option((object)[
            'bookingid' => $booking->id, 'text' => 'Lesson', 'course' => $course->id,
        ])->id;

        $this->userid = (int)self::getDataGenerator()->create_user()->id;
        $this->entityid = persons::get_or_create_for_user($this->userid);
        set_config(examplanning::CONFIG_ENABLED, 1, 'bookingextension_examplanning');
        set_config(planning_period::CONFIG_START, '2026-11-16', 'local_entities');
        set_config(planning_period::CONFIG_END, '2026-11-30', 'local_entities');
        set_config(daily_limit::CONFIG_DEFAULT, 0, 'local_entities');
    }

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
     * A date of an option at which the user teaches.
     *
     * @param int $optionid
     * @param string $from
     * @param string $until
     * @return void
     */
    private function teach(int $optionid, string $from, string $until): void {
        global $DB;
        $dateid = $DB->insert_record('booking_optiondates', (object)[
            'bookingid' => 0,
            'optionid' => $optionid,
            'coursestarttime' => self::at($from),
            'courseendtime' => self::at($until),
        ]);
        $DB->insert_record('booking_optiondates_teachers', (object)['optiondateid' => $dateid, 'userid' => $this->userid]);
    }

    /**
     * A booked exam of the person.
     *
     * @param string $from
     * @param string $until
     * @return void
     */
    private function exam(string $from, string $until): void {
        global $DB;
        $DB->insert_record(occupations::TABLE, (object)[
            'optionid' => $this->examoptionid,
            'userid' => (int)self::getDataGenerator()->create_user()->id,
            'entityid' => $this->entityid,
            'role' => 'clerk',
            'slotstart' => self::at($from),
            'slotend' => self::at($until),
        ]);
    }

    /**
     * Loads and asks why the person is not free.
     *
     * @param string $from
     * @param string $until
     * @return string|null
     */
    private function reason(string $from, string $until): ?string {
        return availability_loader::load(
            [$this->entityid],
            self::at('2026-11-17 10:00'),
            self::at('2026-11-17 12:00'),
            $this->examoptionid
        )->reason($this->entityid, self::at($from), self::at($until));
    }

    /**
     * Absence, booked exam and planning period are read.
     *
     * @return void
     */
    public function test_absence_exam_and_period(): void {
        unavailability::replace_in_window($this->entityid, self::at('2026-11-17 00:00'), self::at('2026-11-18 00:00'), [], [
            ['starttime' => self::at('2026-11-17 08:00'), 'endtime' => self::at('2026-11-17 09:00')],
        ]);
        $this->exam('2026-11-17 10:00', '2026-11-17 10:30');

        $this->assertSame(availability::ABSENT, $this->reason('2026-11-17 08:30', '2026-11-17 09:00'));
        $this->assertSame(availability::OCCUPIED, $this->reason('2026-11-17 10:00', '2026-11-17 10:30'));
        $this->assertSame(availability::OUTSIDE_PERIOD, $this->reason('2026-12-01 10:00', '2026-12-01 10:30'));
        $this->assertNull($this->reason('2026-11-17 11:00', '2026-11-17 11:30'));
    }

    /**
     * Teaching at another option blocks, the dates of the option that is planned do not.
     *
     * @return void
     */
    public function test_teaching_only_at_other_options(): void {
        $this->teach($this->otheroptionid, '2026-11-17 13:00', '2026-11-17 14:00');
        $this->teach($this->examoptionid, '2026-11-17 15:00', '2026-11-17 16:00');

        $this->assertSame(availability::TEACHING, $this->reason('2026-11-17 13:30', '2026-11-17 14:00'));
        $this->assertNull($this->reason('2026-11-17 15:00', '2026-11-17 15:30'));
    }

    /**
     * The daily limit sees the exams of the whole day, also outside the loaded range.
     *
     * @return void
     */
    public function test_daily_limit_sees_the_whole_day(): void {
        daily_limit::set($this->entityid, 1);
        $this->exam('2026-11-17 18:00', '2026-11-17 18:30');

        $this->assertSame(availability::DAILY_LIMIT, $this->reason('2026-11-17 10:00', '2026-11-17 10:30'));
    }

    /**
     * A cancelled exam does not count for the daily limit.
     *
     * @return void
     */
    public function test_cancelled_exam_does_not_count(): void {
        global $DB;
        daily_limit::set($this->entityid, 1);
        $this->exam('2026-11-17 18:00', '2026-11-17 18:30');
        $DB->set_field(occupations::TABLE, 'timecancelled', time(), ['entityid' => $this->entityid]);

        $this->assertNull($this->reason('2026-11-17 10:00', '2026-11-17 10:30'));
    }
}
