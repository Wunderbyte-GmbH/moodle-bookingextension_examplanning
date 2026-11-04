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
use bookingextension_examplanning\local\distribution\distribution_result;
use bookingextension_examplanning\local\option_slots;
use bookingextension_examplanning\local\plan_editor;
use bookingextension_examplanning\local\plan_store;
use bookingextension_examplanning\local\planned_options;
use local_entities\persons;
use local_entities\planning_period;
use local_entities\unavailability;
use mod_booking\singleton_service;
use mod_booking\teachers_handler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests that the changes of a draft plan are checked again with the data of the database and stored.
 *
 * The option has slots of an hour from 09:00 to 12:00 on 10 January 2050. Anna has the first slot with clerk A,
 * Ben the second with clerk B. Clerk C is a further possible clerk.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\plan_editor
 * @covers      \bookingextension_examplanning\local\option_slots
 * @covers      \bookingextension_examplanning\local\problem_text
 */
final class plan_editor_test extends \advanced_testcase {
    /** @var int The option. */
    private int $optionid = 0;

    /** @var int The run. */
    private int $planid = 0;

    /** @var int[][] The slots of the option. */
    private array $slots = [];

    /** @var array<string, int> User ids of the participants. */
    private array $users = [];

    /** @var array<string, int> Entity ids of the clerks. */
    private array $clerks = [];

    /**
     * Creates the option, the persons and a ready run.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setTimezone('Europe/Vienna', 'Europe/Vienna');

        $course = self::getDataGenerator()->create_course();
        /** @var \mod_booking_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $booking = $generator->create_instance(['course' => $course->id]);
        $record = [
            'bookingid' => $booking->id,
            'text' => 'Exam',
            'course' => $course->id,
            'optiontype' => MOD_BOOKING_OPTIONTYPE_SLOTBOOKING,
            'maxanswers' => 20,
            'slot_enabled' => 1,
            'slot_type' => 'fixed',
            'slot_duration_minutes' => 60,
            'slot_interval_minutes' => 60,
            'slot_opening_time' => '09:00',
            'slot_closing_time' => '12:00',
            'slot_valid_from' => make_timestamp(2050, 1, 10),
            'slot_valid_until' => make_timestamp(2050, 1, 10),
            'slot_max_participants_per_slot' => 1,
            'slot_max_slots_per_user' => 1,
            'slot_booking_view_mode' => 'list',
            'slot_teachers_required' => 0,
        ];
        for ($day = 1; $day <= 7; $day++) {
            $record['slot_day_' . $day] = 1;
        }
        $this->optionid = (int)$generator->create_option((object)$record)->id;
        singleton_service::destroy_instance();
        $cmid = (int)singleton_service::get_instance_of_booking_option_settings($this->optionid)->cmid;

        set_config(examplanning::CONFIG_ENABLED, 1, 'bookingextension_examplanning');
        set_config(planning_period::CONFIG_START, '2050-01-01', 'local_entities');
        set_config(planning_period::CONFIG_END, '2050-01-31', 'local_entities');
        planned_options::set_planned($this->optionid, true);

        $trainer = (int)self::getDataGenerator()->create_user(['firstname' => 'Tom', 'lastname' => 'Trainer'])->id;
        (new teachers_handler($this->optionid))->subscribe_teacher_to_booking_option($trainer, $this->optionid, $cmid);
        $examiner = persons::get_or_create_for_user($trainer);
        foreach (['anna', 'ben'] as $name) {
            $this->users[$name] = (int)self::getDataGenerator()->create_user(['firstname' => ucfirst($name)])->id;
            self::getDataGenerator()->enrol_user($this->users[$name], $course->id, 'student');
        }
        foreach (['a', 'b', 'c'] as $name) {
            $user = self::getDataGenerator()->create_user(['firstname' => 'Clerk', 'lastname' => strtoupper($name)]);
            $this->clerks[$name] = persons::get_or_create_for_user((int)$user->id);
        }
        clerk_sources::set($this->optionid, clerk_sources::ENTITY, array_values($this->clerks));
        assignments::save($this->optionid, [
            $this->users['anna'] => $this->clerks['a'],
            $this->users['ben'] => $this->clerks['b'],
        ], 2);

        $this->slots = option_slots::slots($this->optionid);
        $this->assertCount(3, $this->slots, 'Three slots of an hour.');
        $result = new distribution_result();
        $result->plan($this->users['anna'], ...$this->slots[0]);
        $result->plan($this->users['ben'], ...$this->slots[1]);
        $this->planid = plan_store::create($this->optionid, null, 2);
        plan_store::save_result($this->planid, $examiner, [
            $this->users['anna'] => $this->clerks['a'],
            $this->users['ben'] => $this->clerks['b'],
        ], $result);
    }

    /**
     * The start of the slot of a participant in the run.
     *
     * @param string $name
     * @return int
     */
    private function start_of(string $name): int {
        return (int)plan_store::items($this->planid)[$this->users[$name]]->slotstart;
    }

    /**
     * A move to a free slot is stored, a move to the slot of the other exam of the examiner is refused with the reason.
     *
     * @return void
     */
    public function test_move(): void {
        plan_editor::move($this->planid, $this->users['anna'], $this->slots[2][0]);
        $this->assertSame($this->slots[2][0], $this->start_of('anna'));

        try {
            plan_editor::move($this->planid, $this->users['anna'], $this->slots[1][0]);
            $this->fail('The examiner is occupied then.');
        } catch (\moodle_exception $e) {
            $this->assertSame('change_invalid', $e->errorcode);
            $this->assertStringContainsString('Tom Trainer', $e->getMessage());
        }
        $this->assertSame($this->slots[2][0], $this->start_of('anna'), 'Nothing changed.');
    }

    /**
     * A slot that the option does not have is refused.
     *
     * @return void
     */
    public function test_move_to_unknown_slot(): void {
        $this->expectExceptionMessage(get_string('change_noslot', 'bookingextension_examplanning'));
        plan_editor::move($this->planid, $this->users['anna'], $this->slots[0][0] + 60);
    }

    /**
     * Two participants swap their slots.
     *
     * @return void
     */
    public function test_swap(): void {
        plan_editor::swap($this->planid, $this->users['anna'], $this->users['ben']);

        $this->assertSame($this->slots[1][0], $this->start_of('anna'));
        $this->assertSame($this->slots[0][0], $this->start_of('ben'));
    }

    /**
     * A free clerk is stored in the run and in the assignment, an absent one and one who is no clerk are refused.
     *
     * @return void
     */
    public function test_change_clerk(): void {
        plan_editor::change_clerk($this->planid, $this->users['anna'], $this->clerks['c'], 2);
        $this->assertSame($this->clerks['c'], (int)plan_store::items($this->planid)[$this->users['anna']]->clerkentityid);
        $this->assertSame($this->clerks['c'], assignments::get($this->optionid)[$this->users['anna']]);

        unavailability::replace_in_window($this->clerks['a'], $this->slots[0][0], $this->slots[0][1], [], [
            ['starttime' => $this->slots[0][0], 'endtime' => $this->slots[0][1]],
        ]);
        try {
            plan_editor::change_clerk($this->planid, $this->users['anna'], $this->clerks['a'], 2);
            $this->fail('Clerk A is absent then.');
        } catch (\moodle_exception $e) {
            $this->assertSame('change_invalid', $e->errorcode);
        }

        $nobody = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);
        $this->expectExceptionMessage(get_string('change_noclerk', 'bookingextension_examplanning'));
        plan_editor::change_clerk($this->planid, $this->users['anna'], $nobody, 2);
    }

    /**
     * A run that is not ready can not be changed.
     *
     * @return void
     */
    public function test_run_that_is_not_ready(): void {
        plan_store::set_status($this->planid, plan_store::RUNNING);

        $this->expectExceptionMessage(get_string('change_notready', 'bookingextension_examplanning'));
        plan_editor::swap($this->planid, $this->users['anna'], $this->users['ben']);
    }
}
