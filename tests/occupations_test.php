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

use bookingextension_examplanning\local\occupations;
use local_entities\entities;
use local_entities\persons;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests that the booked exams show as occupations of the examiner and the clerk in Entities.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\occupations
 * @covers      \local_entities\hook\collect_occupations
 */
final class occupations_test extends \advanced_testcase {
    /** @var int Id of the booking option. */
    private int $optionid = 0;

    /** @var int Course module id of the booking instance. */
    private int $cmid = 0;

    /**
     * Creates a booking option and switches the extension on.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        /** @var \mod_booking_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $booking = $generator->create_instance(['course' => $course->id]);
        $this->cmid = (int)get_coursemodule_from_instance('booking', $booking->id)->id;
        $this->optionid = (int)$generator->create_option((object)[
            'bookingid' => $booking->id,
            'text' => 'Exam',
            'course' => $course->id,
        ])->id;

        set_config(examplanning::CONFIG_ENABLED, 1, 'bookingextension_examplanning');
    }

    /**
     * Stores a booked exam of a participant with one person in one role.
     *
     * @param int $userid participant
     * @param int $entityid person
     * @param string $role examiner or clerk
     * @param int $start
     * @return void
     */
    private function book(int $userid, int $entityid, string $role, int $start): void {
        global $DB;
        $DB->insert_record(occupations::TABLE, (object)[
            'optionid' => $this->optionid,
            'userid' => $userid,
            'entityid' => $entityid,
            'role' => $role,
            'slotstart' => $start,
            'slotend' => $start + 1800,
        ]);
    }

    /**
     * The exams of the option, as Entities sees them for a person.
     *
     * @param int $entityid
     * @return array
     */
    private function exams_of(int $entityid): array {
        return array_values(array_filter(
            entities::get_all_dates_for_entity($entityid, true),
            static fn($d) => $d->component === 'bookingextension_examplanning'
        ));
    }

    /**
     * The clerk and the examiner see the exam with the participant, the option, both persons and a link.
     *
     * @return void
     */
    public function test_exam_shows_for_examiner_and_clerk(): void {
        $participant = self::getDataGenerator()->create_user(['firstname' => 'Jacob', 'lastname' => 'Demo']);
        $examiner = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user([
            'firstname' => 'Tom',
            'lastname' => 'Trainer',
        ])->id);
        $clerk = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user([
            'firstname' => 'Billy',
            'lastname' => 'Teachy',
        ])->id);
        $start = time() + DAYSECS;
        $this->book((int)$participant->id, $examiner, 'examiner', $start);
        $this->book((int)$participant->id, $clerk, 'clerk', $start);

        $expected = get_string('occupation', 'bookingextension_examplanning', (object)[
            'participant' => 'Jacob Demo',
            'option' => 'Exam',
            'examiner' => 'Tom Trainer',
            'clerk' => 'Billy Teachy',
        ]);
        $exams = $this->exams_of($clerk);
        $this->assertCount(1, $exams);
        $this->assertSame($expected, $exams[0]->name);
        $this->assertSame($start, $exams[0]->starttime);
        $this->assertSame($start + 1800, $exams[0]->endtime);
        $this->assertSame($this->optionid, (int)$exams[0]->link->get_param('optionid'));
        $this->assertSame($this->cmid, (int)$exams[0]->link->get_param('cmid'));

        $exams = $this->exams_of($examiner);
        $this->assertCount(1, $exams);
        $this->assertSame($expected, $exams[0]->name);
    }

    /**
     * A person without booked exams has none, and a switched off extension tells nothing.
     *
     * @return void
     */
    public function test_no_exams_without_booking_or_when_switched_off(): void {
        $participant = (int)self::getDataGenerator()->create_user()->id;
        $clerk = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);
        $other = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);
        $this->book($participant, $clerk, 'clerk', time() + DAYSECS);

        $this->assertSame([], $this->exams_of($other));

        set_config(examplanning::CONFIG_ENABLED, 0, 'bookingextension_examplanning');
        $this->assertSame([], $this->exams_of($clerk));
    }

    /**
     * A cancelled exam does not show in the calendar.
     *
     * @return void
     */
    public function test_cancelled_exam_is_not_shown(): void {
        global $DB;
        $participant = (int)self::getDataGenerator()->create_user()->id;
        $clerk = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);
        $this->book($participant, $clerk, 'clerk', time() + DAYSECS);
        $DB->set_field(occupations::TABLE, 'timecancelled', time(), ['entityid' => $clerk]);

        $this->assertSame([], $this->exams_of($clerk));
    }
}
