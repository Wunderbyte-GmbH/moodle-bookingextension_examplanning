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

use bookingextension_examplanning\local\examiner;
use bookingextension_examplanning\local\planned_options;
use bookingextension_examplanning\option\fields\examplanning as examplanning_field;
use local_entities\persons;
use mod_booking\teachers_handler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests for the examiner of a planned option: the one trainer, as a person in Entities.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\examiner
 * @covers      \bookingextension_examplanning\observer
 * @covers      \bookingextension_examplanning\option\fields\examplanning
 */
final class examiner_test extends \advanced_testcase {
    /** @var int Id of the booking option. */
    private int $optionid = 0;

    /** @var int Course module id of the booking instance. */
    private int $cmid = 0;

    /**
     * Creates a booking option without trainer and switches the extension on.
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
        planned_options::reset_cache();
    }

    /**
     * Makes a new user a trainer of the option.
     *
     * @return int user id
     */
    private function add_trainer(): int {
        $userid = (int)self::getDataGenerator()->create_user()->id;
        (new teachers_handler($this->optionid))->subscribe_teacher_to_booking_option($userid, $this->optionid, $this->cmid);
        return $userid;
    }

    /**
     * Without a trainer or with two trainers there is no examiner, with one there is.
     *
     * @return void
     */
    public function test_exactly_one_trainer_is_needed(): void {
        $this->assertSame(examiner::PROBLEM_NONE, examiner::get_problem($this->optionid));

        $first = $this->add_trainer();
        $this->assertNull(examiner::get_problem($this->optionid));
        $entityid = examiner::get_entityid($this->optionid);
        $this->assertSame(persons::get_entityid_for_user($first), $entityid);

        $this->add_trainer();
        $this->assertSame(examiner::PROBLEM_MANY, examiner::get_problem($this->optionid));
        $this->expectException(\moodle_exception::class);
        examiner::get_entityid($this->optionid);
    }

    /**
     * The examiner gets a person in Entities if there is none.
     *
     * @return void
     */
    public function test_examiner_gets_a_person(): void {
        $userid = $this->add_trainer();
        $this->assertNull(persons::get_entityid_for_user($userid));

        $entityid = examiner::get_entityid($this->optionid);

        $this->assertSame($entityid, persons::get_entityid_for_user($userid));
    }

    /**
     * A trainer added to a planned option gets a person at once, in an option that is not planned not.
     *
     * @return void
     */
    public function test_trainer_of_a_planned_option_gets_a_person(): void {
        $notplanned = $this->add_trainer();
        $this->assertNull(persons::get_entityid_for_user($notplanned), 'Not planned: no person.');

        planned_options::set_planned($this->optionid, true);
        $planned = $this->add_trainer();
        $this->assertNotNull(persons::get_entityid_for_user($planned));
    }

    /**
     * Switching the planning on gives the trainers that were there before their persons.
     *
     * @return void
     */
    public function test_switching_on_creates_persons_of_existing_trainers(): void {
        $userid = $this->add_trainer();
        $this->assertNull(persons::get_entityid_for_user($userid));

        $formdata = (object)[examplanning_field::ELEMENT => 1, 'optiontype' => MOD_BOOKING_OPTIONTYPE_SLOTBOOKING];
        $option = (object)['id' => $this->optionid];
        examplanning_field::save_data($formdata, $option);

        $this->assertNotNull(persons::get_entityid_for_user($userid));
    }

    /**
     * Switching the planning on without exactly one trainer tells why the exams can not be planned yet.
     *
     * @return void
     */
    public function test_switching_on_without_trainer_warns(): void {
        $formdata = (object)[examplanning_field::ELEMENT => 1, 'optiontype' => MOD_BOOKING_OPTIONTYPE_SLOTBOOKING];
        $option = (object)['id' => $this->optionid];

        examplanning_field::save_data($formdata, $option);

        $messages = array_map(static fn($n) => $n->get_message(), \core\notification::fetch());
        $this->assertContains(get_string('examiner_none', 'bookingextension_examplanning'), $messages);
        planned_options::reset_cache();
        $this->assertTrue(planned_options::is_planned($this->optionid), 'The planning is switched on anyway.');
    }
}
