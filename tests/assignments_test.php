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

use bookingextension_examplanning\local\assignments;
use bookingextension_examplanning\local\clerk_sources;
use bookingextension_examplanning\local\participants;
use bookingextension_examplanning\local\planned_options;
use bookingextension_examplanning\local\planning_access;
use local_entities\persons;
use mod_booking\teachers_handler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests for the assignment of a clerk to every participant of a planned option.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\assignments
 * @covers      \bookingextension_examplanning\local\participants
 * @covers      \bookingextension_examplanning\local\planning_access
 */
final class assignments_test extends \advanced_testcase {
    /** @var int Id of the booking option. */
    private int $optionid = 0;

    /** @var int Course module id of the booking instance. */
    private int $cmid = 0;

    /** @var \stdClass The course of the booking instance. */
    private \stdClass $course;

    /**
     * Creates a planned booking option and switches the extension on.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = self::getDataGenerator()->create_course();
        /** @var \mod_booking_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $booking = $generator->create_instance(['course' => $this->course->id]);
        $this->cmid = (int)get_coursemodule_from_instance('booking', $booking->id)->id;
        $this->optionid = (int)$generator->create_option((object)[
            'bookingid' => $booking->id,
            'text' => 'Exam',
            'course' => $this->course->id,
        ])->id;

        set_config(examplanning::CONFIG_ENABLED, 1, 'bookingextension_examplanning');
        planned_options::set_planned($this->optionid, true);
        planned_options::reset_cache();
    }

    /**
     * Enrols a new user in the course.
     *
     * @param string $role
     * @param array $record
     * @return int user id
     */
    private function enrol(string $role, array $record = []): int {
        $userid = (int)self::getDataGenerator()->create_user($record)->id;
        self::getDataGenerator()->enrol_user($userid, $this->course->id, $role);
        return $userid;
    }

    /**
     * Makes a new person a possible clerk of the option.
     *
     * @return int entity id
     */
    private function clerk(): int {
        $entityid = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);
        $current = clerk_sources::get($this->optionid)['entities'];
        clerk_sources::set($this->optionid, clerk_sources::ENTITY, array_merge($current, [$entityid]));
        return $entityid;
    }

    /**
     * The participants are the students, ordered by name, each once; teachers are no participants.
     *
     * @return void
     */
    public function test_participants_are_the_students(): void {
        $zoe = $this->enrol('student', ['firstname' => 'Zoe', 'lastname' => 'Zander']);
        $anna = $this->enrol('student', ['firstname' => 'Anna', 'lastname' => 'Adler']);
        $this->enrol('editingteacher');
        // A second student role must not list Anna twice.
        $context = \context_module::instance($this->cmid);
        $otherrole = create_role('Exam student', 'examstudent', '', 'student');
        role_assign($otherrole, $anna, $context->id);

        $this->assertSame([$anna, $zoe], array_keys(participants::for_option($this->optionid)));
        $this->assertSame('Adler', participants::for_option($this->optionid)[$anna]->lastname);
    }

    /**
     * Assignments are stored, changed and removed with 0.
     *
     * @return void
     */
    public function test_assignments_are_stored_changed_and_removed(): void {
        global $USER;
        $first = $this->enrol('student');
        $second = $this->enrol('student');
        $clerka = $this->clerk();
        $clerkb = $this->clerk();

        assignments::save($this->optionid, [$first => $clerka, $second => $clerkb], (int)$USER->id);
        $this->assertEquals([$first => $clerka, $second => $clerkb], assignments::get($this->optionid));

        assignments::save($this->optionid, [$first => $clerkb, $second => 0], (int)$USER->id);
        $this->assertEquals([$first => $clerkb], assignments::get($this->optionid));

        assignments::delete($this->optionid);
        $this->assertSame([], assignments::get($this->optionid));
    }

    /**
     * Somebody who is no participant can not get a clerk.
     *
     * @return void
     */
    public function test_no_participant_is_rejected(): void {
        global $USER;
        $stranger = (int)self::getDataGenerator()->create_user()->id;

        $this->expectException(\invalid_parameter_exception::class);
        assignments::save($this->optionid, [$stranger => $this->clerk()], (int)$USER->id);
    }

    /**
     * A person who is not among the clerks of the option is rejected.
     *
     * @return void
     */
    public function test_person_who_is_no_clerk_is_rejected(): void {
        global $USER;
        $student = $this->enrol('student');
        $other = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);

        $this->expectException(\invalid_parameter_exception::class);
        assignments::save($this->optionid, [$student => $other], (int)$USER->id);
    }

    /**
     * The trainer and who may edit options may plan; a student may not.
     *
     * @return void
     */
    public function test_who_may_plan(): void {
        $trainer = (int)self::getDataGenerator()->create_user()->id;
        (new teachers_handler($this->optionid))->subscribe_teacher_to_booking_option($trainer, $this->optionid, $this->cmid);
        $student = $this->enrol('student');

        $this->assertTrue(planning_access::can_plan($this->optionid, $trainer));
        $this->assertTrue(planning_access::can_plan($this->optionid, get_admin()->id));
        $this->assertFalse(planning_access::can_plan($this->optionid, $student));

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        planning_access::require_plan($this->optionid);
    }
}
