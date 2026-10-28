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

use bookingextension_examplanning\local\clerk_candidates;
use bookingextension_examplanning\local\clerk_sources;
use bookingextension_examplanning\local\planned_options;
use bookingextension_examplanning\option\fields\examplanning as examplanning_field;
use local_entities\persons;
use local_entities\pool\pool_repository;
use mod_booking\teachers_handler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests for the sources of the clerks of a planned option and the persons they lead to.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\clerk_sources
 * @covers      \bookingextension_examplanning\local\clerk_candidates
 * @covers      \bookingextension_examplanning\option\fields\examplanning
 */
final class clerk_candidates_test extends \advanced_testcase {
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
        planned_options::reset_cache();
    }

    /**
     * Creates the person of a new user.
     *
     * @return int entity id
     */
    private function person(): int {
        return persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);
    }

    /**
     * Creates a pool of persons with the given members.
     *
     * @param int[] $entityids
     * @return int pool id
     */
    private function pool(array $entityids): int {
        global $USER;
        $repository = new pool_repository();
        $poolid = $repository->create('Pool ' . uniqid(), '', (int)$USER->id);
        $repository->add_members($poolid, $entityids, (int)$USER->id);
        return $poolid;
    }

    /**
     * The sources are stored per type and replaced as a whole.
     *
     * @return void
     */
    public function test_sources_are_stored_and_replaced(): void {
        clerk_sources::set($this->optionid, clerk_sources::POOL, [5, 3, 3]);
        clerk_sources::set($this->optionid, clerk_sources::ENTITY, [7]);
        $this->assertSame(['pools' => [3, 5], 'entities' => [7]], clerk_sources::get($this->optionid));

        clerk_sources::set($this->optionid, clerk_sources::POOL, [9]);
        $this->assertSame(['pools' => [9], 'entities' => [7]], clerk_sources::get($this->optionid));

        clerk_sources::delete($this->optionid);
        $this->assertSame(['pools' => [], 'entities' => []], clerk_sources::get($this->optionid));
    }

    /**
     * The candidates are the members of the pools and the single persons, each once.
     *
     * @return void
     */
    public function test_candidates_come_from_pools_and_single_persons(): void {
        $first = $this->person();
        $second = $this->person();
        $third = $this->person();
        clerk_sources::set($this->optionid, clerk_sources::POOL, [$this->pool([$first, $second])]);
        clerk_sources::set($this->optionid, clerk_sources::ENTITY, [$second, $third]);

        $expected = [$first, $second, $third];
        sort($expected);
        $this->assertSame($expected, clerk_candidates::for_option($this->optionid));
    }

    /**
     * The trainer is the examiner and never the own clerk; deleted users are no candidates.
     *
     * @return void
     */
    public function test_examiner_and_deleted_users_are_left_out(): void {
        $trainer = self::getDataGenerator()->create_user();
        (new teachers_handler($this->optionid))->subscribe_teacher_to_booking_option(
            (int)$trainer->id,
            $this->optionid,
            $this->cmid
        );
        $trainerperson = persons::get_or_create_for_user((int)$trainer->id);
        $gone = self::getDataGenerator()->create_user();
        $goneperson = persons::get_or_create_for_user((int)$gone->id);
        $clerk = $this->person();
        clerk_sources::set($this->optionid, clerk_sources::ENTITY, [$trainerperson, $goneperson, $clerk]);
        delete_user($gone);

        $this->assertSame([$clerk], clerk_candidates::for_option($this->optionid));
    }

    /**
     * Without sources there are no candidates.
     *
     * @return void
     */
    public function test_no_sources_no_candidates(): void {
        $this->assertSame([], clerk_candidates::for_option($this->optionid));
    }

    /**
     * The form stores the pools for everybody, the single persons only for whoever may see all persons.
     *
     * @return void
     */
    public function test_form_stores_single_persons_only_with_the_right(): void {
        $person = $this->person();
        $poolid = $this->pool([$person]);
        $option = (object)['id' => $this->optionid];
        $formdata = (object)[
            examplanning_field::ELEMENT => 1,
            'optiontype' => MOD_BOOKING_OPTIONTYPE_SLOTBOOKING,
            examplanning_field::ELEMENT_POOLS => [$poolid],
            examplanning_field::ELEMENT_PERSONS => [$person],
        ];

        // A trainer without the right to see all persons.
        $trainer = self::getDataGenerator()->create_user();
        $this->setUser($trainer);
        examplanning_field::save_data($formdata, $option);
        $this->assertSame(['pools' => [$poolid], 'entities' => []], clerk_sources::get($this->optionid));

        $this->setAdminUser();
        examplanning_field::save_data($formdata, $option);
        $this->assertSame(['pools' => [$poolid], 'entities' => [$person]], clerk_sources::get($this->optionid));
        \core\notification::fetch();
    }
}
