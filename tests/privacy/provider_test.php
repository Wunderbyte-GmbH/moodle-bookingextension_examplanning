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

namespace bookingextension_examplanning\privacy;

use bookingextension_examplanning\examplanning;
use bookingextension_examplanning\local\assignments;
use bookingextension_examplanning\local\clerk_sources;
use bookingextension_examplanning\local\planned_options;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_entities\persons;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests for the privacy of the clerk assignments.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \context_module Context of the booking instance. */
    private \context_module $context;

    /** @var int First participant. */
    private int $anna = 0;

    /** @var int Second participant. */
    private int $ben = 0;

    /**
     * Creates a planned option in which both participants got a clerk.
     */
    protected function setUp(): void {
        global $USER;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        /** @var \mod_booking_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $booking = $generator->create_instance(['course' => $course->id]);
        $cmid = (int)get_coursemodule_from_instance('booking', $booking->id)->id;
        $this->context = \context_module::instance($cmid);
        $optionid = (int)$generator->create_option((object)[
            'bookingid' => $booking->id,
            'text' => 'Exam',
            'course' => $course->id,
        ])->id;
        set_config(examplanning::CONFIG_ENABLED, 1, 'bookingextension_examplanning');
        planned_options::set_planned($optionid, true);

        $this->anna = (int)self::getDataGenerator()->create_user()->id;
        $this->ben = (int)self::getDataGenerator()->create_user()->id;
        self::getDataGenerator()->enrol_user($this->anna, $course->id, 'student');
        self::getDataGenerator()->enrol_user($this->ben, $course->id, 'student');
        $clerk = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);
        clerk_sources::set($optionid, clerk_sources::ENTITY, [$clerk]);
        assignments::save($optionid, [$this->anna => $clerk, $this->ben => $clerk], (int)$USER->id);
    }

    /**
     * The participant is found in the context of the booking instance.
     *
     * @return void
     */
    public function test_contexts_and_users(): void {
        $contextids = provider::get_contexts_for_userid($this->anna)->get_contextids();
        $this->assertSame([$this->context->id], array_map('intval', $contextids));

        $userlist = new userlist($this->context, 'bookingextension_examplanning');
        provider::get_users_in_context($userlist);
        $expected = [$this->anna, $this->ben, (int)get_admin()->id];
        sort($expected);
        $found = array_map('intval', $userlist->get_userids());
        sort($found);
        $this->assertSame($expected, $found);
    }

    /**
     * The export holds the assignment of the participant.
     *
     * @return void
     */
    public function test_export(): void {
        $contextlist = new approved_contextlist(\core_user::get_user($this->anna), 'bookingextension_examplanning', [
            $this->context->id,
        ]);
        provider::export_user_data($contextlist);

        $data = writer::with_context($this->context)->get_data([get_string('pluginname', 'bookingextension_examplanning')]);
        $this->assertCount(1, $data->assignments);
    }

    /**
     * Deleting one user leaves the other, deleting the context removes all.
     *
     * @return void
     */
    public function test_delete(): void {
        global $DB;
        $contextlist = new approved_contextlist(\core_user::get_user($this->anna), 'bookingextension_examplanning', [
            $this->context->id,
        ]);
        provider::delete_data_for_user($contextlist);
        $this->assertFalse($DB->record_exists(assignments::TABLE, ['userid' => $this->anna]));
        $this->assertTrue($DB->record_exists(assignments::TABLE, ['userid' => $this->ben]));

        provider::delete_data_for_users(new approved_userlist($this->context, 'bookingextension_examplanning', [$this->ben]));
        $this->assertFalse($DB->record_exists(assignments::TABLE, ['userid' => $this->ben]));

        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(0, $DB->count_records(assignments::TABLE));
    }
}
