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

use bookingextension_examplanning\local\planned_options;
use bookingextension_examplanning\option\fields\examplanning as examplanning_field;
use mod_booking\bo_availability\bo_info;
use mod_booking\local\bookingworkflow\bookingrestriction;
use mod_booking\singleton_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests that participants can not book an option that the trainer plans.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\planned_options
 * @covers      \bookingextension_examplanning\local\bookingrestriction
 * @covers      \bookingextension_examplanning\option\fields\examplanning
 * @covers      \mod_booking\local\bookingworkflow\bookingrestriction
 * @covers      \mod_booking\bo_availability\conditions\extensionrestriction
 */
final class bookingrestriction_test extends \advanced_testcase {
    /** @var int Id of the booking option. */
    private int $optionid = 0;

    /** @var int Id of a participant of the course. */
    private int $studentid = 0;

    /**
     * Creates a course with a participant and a booking option, and switches the extension on.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $this->studentid = (int)self::getDataGenerator()->create_user()->id;
        self::getDataGenerator()->enrol_user($this->studentid, $course->id, 'student');

        /** @var \mod_booking_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $booking = $generator->create_instance(['course' => $course->id]);
        $this->optionid = (int)$generator->create_option((object)[
            'bookingid' => $booking->id,
            'text' => 'Exam',
            'course' => $course->id,
            'maxanswers' => 10,
        ])->id;

        set_config(examplanning::CONFIG_ENABLED, 1, 'bookingextension_examplanning');
        $this->reset_caches();
    }

    /**
     * Forgets what was read in this request.
     *
     * @return void
     */
    private function reset_caches(): void {
        planned_options::reset_cache();
        bookingrestriction::reset_static_cache();
        singleton_service::destroy_instance();
    }

    /**
     * The result of the availability check for the participant: [condition id, available].
     *
     * @return array
     */
    private function check(): array {
        // Who may book for others, for example an admin, gets past hints like this one. The participant does not.
        $this->setUser($this->studentid);
        $this->reset_caches();
        $settings = singleton_service::get_instance_of_booking_option_settings($this->optionid);
        [$id, $isavailable] = (new bo_info($settings))->is_available($this->optionid, $this->studentid, true);
        return [$id, $isavailable];
    }

    /**
     * The planned state is stored, read back and switched off again.
     *
     * @return void
     */
    public function test_planned_state_is_stored(): void {
        $this->assertFalse(planned_options::is_planned($this->optionid));

        planned_options::set_planned($this->optionid, true);
        planned_options::reset_cache();
        $this->assertTrue(planned_options::is_planned($this->optionid));

        planned_options::set_planned($this->optionid, false);
        planned_options::reset_cache();
        $this->assertFalse(planned_options::is_planned($this->optionid));
    }

    /**
     * A participant can not book a planned option and sees why.
     *
     * @return void
     */
    public function test_participant_can_not_book_a_planned_option(): void {
        planned_options::set_planned($this->optionid, true);

        [$id, $isavailable] = $this->check();

        $this->assertFalse($isavailable);
        $this->assertSame(MOD_BOOKING_BO_COND_EXTENSIONRESTRICTION, $id);
        $this->assertSame(
            get_string('bookingrestricted', 'bookingextension_examplanning'),
            bookingrestriction::get_reason($this->optionid, $this->studentid)
        );
    }

    /**
     * An option that is not planned is not restricted.
     *
     * @return void
     */
    public function test_option_that_is_not_planned_is_not_restricted(): void {
        [$id] = $this->check();

        $this->assertNotSame(MOD_BOOKING_BO_COND_EXTENSIONRESTRICTION, $id);
        $this->assertNull(bookingrestriction::get_reason($this->optionid, $this->studentid));
    }

    /**
     * When the extension is switched off, a planned option can be booked again.
     *
     * @return void
     */
    public function test_switched_off_extension_does_not_restrict(): void {
        planned_options::set_planned($this->optionid, true);
        set_config(examplanning::CONFIG_ENABLED, 0, 'bookingextension_examplanning');

        [$id] = $this->check();

        $this->assertNotSame(MOD_BOOKING_BO_COND_EXTENSIONRESTRICTION, $id);
    }

    /**
     * The form field only stores the planning for slot booking options.
     *
     * @return void
     */
    public function test_form_field_plans_only_slot_booking_options(): void {
        $option = (object)['id' => $this->optionid];

        $formdata = (object)[examplanning_field::ELEMENT => 1, 'optiontype' => MOD_BOOKING_OPTIONTYPE_DEFAULT];
        examplanning_field::save_data($formdata, $option);
        planned_options::reset_cache();
        $this->assertFalse(planned_options::is_planned($this->optionid), 'A normal option is not planned.');

        $formdata = (object)[examplanning_field::ELEMENT => 1, 'optiontype' => MOD_BOOKING_OPTIONTYPE_SLOTBOOKING];
        examplanning_field::save_data($formdata, $option);
        planned_options::reset_cache();
        $this->assertTrue(planned_options::is_planned($this->optionid));

        $formdata = (object)[examplanning_field::ELEMENT => 0, 'optiontype' => MOD_BOOKING_OPTIONTYPE_SLOTBOOKING];
        examplanning_field::save_data($formdata, $option);
        planned_options::reset_cache();
        $this->assertFalse(planned_options::is_planned($this->optionid));
    }
}
