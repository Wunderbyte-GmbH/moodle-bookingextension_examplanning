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
use bookingextension_examplanning\local\occupations;
use bookingextension_examplanning\local\plan_confirmer;
use bookingextension_examplanning\local\plan_rollback;
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
 * Tests that a draft plan is booked all or nothing, with examiner and clerk stored for each exam.
 *
 * The option has slots of an hour from 09:00 to 12:00 on 10 January 2050. Anna has the first slot with clerk A,
 * Ben the second with clerk B. Clerk C is a further possible clerk.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\plan_confirmer
 * @covers      \bookingextension_examplanning\local\plan_rollback
 * @covers      \bookingextension_examplanning\local\clerk_enrolment
 */
final class plan_confirmer_test extends \advanced_testcase {
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

    /** @var int Entity id of the examiner. */
    private int $examiner = 0;

    /** @var int The course. */
    private int $courseid = 0;

    /**
     * Creates the option, the persons and a ready run.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setTimezone('Europe/Vienna', 'Europe/Vienna');

        $course = self::getDataGenerator()->create_course();
        $this->courseid = (int)$course->id;
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
        $this->examiner = $examiner;
        $this->planid = plan_store::create($this->optionid, null, 2);
        plan_store::save_result($this->planid, $examiner, [
            $this->users['anna'] => $this->clerks['a'],
            $this->users['ben'] => $this->clerks['b'],
        ], $result);
    }

    /**
     * The participants who have a booking in the option.
     *
     * @return int[] user ids, sorted
     */
    private function booked(): array {
        global $DB;
        $userids = array_map('intval', $DB->get_fieldset_select(
            'booking_answers',
            'userid',
            'optionid = :optionid AND waitinglist = :booked',
            ['optionid' => $this->optionid, 'booked' => MOD_BOOKING_STATUSPARAM_BOOKED]
        ));
        sort($userids);
        return $userids;
    }

    /**
     * Every participant is booked into the slot, examiner and clerk are stored with the run, the run is booked.
     *
     * @return void
     */
    public function test_confirm_books_everybody(): void {
        global $DB;

        $this->assertSame(2, plan_confirmer::confirm($this->planid, 2));

        $expected = [$this->users['anna'], $this->users['ben']];
        sort($expected);
        $this->assertSame($expected, $this->booked());
        $this->assertSame(plan_store::BOOKED, plan_store::get($this->planid)->status);

        $staff = $DB->get_records(occupations::TABLE, ['userid' => $this->users['anna']], 'role');
        $this->assertCount(2, $staff);
        $clerk = array_values(array_filter($staff, static fn($s) => $s->role === 'clerk'))[0];
        $examiner = array_values(array_filter($staff, static fn($s) => $s->role === 'examiner'))[0];
        $this->assertSame($this->clerks['a'], (int)$clerk->entityid);
        $this->assertSame($this->examiner, (int)$examiner->entityid);
        $this->assertSame($this->slots[0][0], (int)$clerk->slotstart);
        $this->assertSame($this->planid, (int)$clerk->runid);
        $answer = $DB->get_record('booking_answers', ['optionid' => $this->optionid, 'userid' => $this->users['anna']]);
        $this->assertSame((int)$answer->id, (int)$clerk->answerid);
        $slotdata = \mod_booking\local\slotbooking\slot_answer::get_slot_data($answer);
        $this->assertSame($this->slots[0][0], (int)$slotdata['slots'][0]['start']);
    }

    /**
     * With a participant who could not be planned nobody is booked and the run stays open.
     *
     * @return void
     */
    public function test_nobody_is_booked_when_one_is_not_planned(): void {
        global $DB;
        $DB->set_field(plan_store::TABLE_ITEM, 'slotstart', null, ['planid' => $this->planid, 'userid' => $this->users['ben']]);
        $DB->set_field(plan_store::TABLE_ITEM, 'slotend', null, ['planid' => $this->planid, 'userid' => $this->users['ben']]);

        try {
            plan_confirmer::confirm($this->planid, 2);
            $this->fail('Ben is not planned.');
        } catch (\moodle_exception $e) {
            $this->assertSame('confirm_unplanned', $e->errorcode);
            $this->assertStringContainsString('Ben', $e->getMessage());
        }
        $this->assertSame([], $this->booked());
        $this->assertSame(plan_store::READY, plan_store::get($this->planid)->status);
    }

    /**
     * When a clerk is absent now, nobody is booked and the reason is told.
     *
     * @return void
     */
    public function test_nobody_is_booked_when_something_changed(): void {
        unavailability::replace_in_window($this->clerks['b'], $this->slots[1][0], $this->slots[1][1], [], [
            ['starttime' => $this->slots[1][0], 'endtime' => $this->slots[1][1]],
        ]);

        try {
            plan_confirmer::confirm($this->planid, 2);
            $this->fail('Clerk B is absent.');
        } catch (\moodle_exception $e) {
            $this->assertSame('confirm_problems', $e->errorcode);
            $this->assertStringContainsString('Clerk B', $e->getMessage());
        }
        $this->assertSame([], $this->booked());
        $this->assertSame(0, \core\di::get(\moodle_database::class)->count_records(occupations::TABLE));
    }

    /**
     * A run that is booked can not be booked again.
     *
     * @return void
     */
    public function test_booked_run_can_not_be_booked_again(): void {
        plan_confirmer::confirm($this->planid, 2);

        $this->expectExceptionMessage(get_string('change_notready', 'bookingextension_examplanning'));
        plan_confirmer::confirm($this->planid, 2);
    }

    /**
     * A cancellation in Booking marks the exam of that participant as cancelled, the row stays.
     *
     * @return void
     */
    public function test_cancellation_in_booking_marks_the_exam(): void {
        global $DB;
        plan_confirmer::confirm($this->planid, 2);
        $settings = singleton_service::get_instance_of_booking_option_settings($this->optionid);
        $option = singleton_service::get_instance_of_booking_option((int)$settings->cmid, $this->optionid);

        $option->user_delete_response($this->users['anna']);

        $anna = $DB->get_records(occupations::TABLE, ['userid' => $this->users['anna']]);
        $this->assertCount(2, $anna, 'The rows stay for the rules.');
        foreach ($anna as $row) {
            $this->assertNotEmpty($row->timecancelled);
        }
        foreach ($DB->get_records(occupations::TABLE, ['userid' => $this->users['ben']]) as $row) {
            $this->assertNull($row->timecancelled, 'Ben is still booked.');
        }
    }

    /**
     * Taking back cancels all bookings, marks the exams, makes the plan a draft again, and it can be booked again.
     *
     * @return void
     */
    public function test_rollback_and_book_again(): void {
        global $DB;
        plan_confirmer::confirm($this->planid, 2);

        $this->assertSame(2, plan_rollback::rollback($this->planid));

        $this->assertSame([], $this->booked());
        $this->assertSame(plan_store::READY, plan_store::get($this->planid)->status);
        $this->assertSame(0, $DB->count_records_select(occupations::TABLE, 'timecancelled IS NULL'));
        $this->assertSame(4, $DB->count_records(occupations::TABLE), 'The rows stay for the rules.');

        $this->assertSame(2, plan_confirmer::confirm($this->planid, 2));
        $this->assertCount(2, $this->booked());
        $this->assertSame(4, $DB->count_records_select(occupations::TABLE, 'timecancelled IS NULL'));
    }

    /**
     * A plan that is not booked, or whose first exam has begun, can not be taken back.
     *
     * @return void
     */
    public function test_rollback_only_booked_and_before_the_first_exam(): void {
        global $DB;
        try {
            plan_rollback::rollback($this->planid);
            $this->fail('The plan is not booked.');
        } catch (\moodle_exception $e) {
            $this->assertSame('rollback_notbooked', $e->errorcode);
        }

        plan_confirmer::confirm($this->planid, 2);
        $DB->set_field(occupations::TABLE, 'slotstart', time() - 60, ['userid' => $this->users['anna']]);

        $this->expectExceptionMessage(get_string('rollback_started', 'bookingextension_examplanning'));
        plan_rollback::rollback($this->planid);
    }

    /**
     * Is the user of a clerk in the course?
     *
     * @param string $name a, b or c
     * @return bool
     */
    private function clerk_in_course(string $name): bool {
        global $DB;
        $userid = (int)$DB->get_field('local_entities', 'userid', ['id' => $this->clerks[$name]]);
        return is_enrolled(\context_course::instance($this->courseid), $userid);
    }

    /**
     * Booking enrols the clerks as non-editing teachers, taking back unenrols them again.
     *
     * @return void
     */
    public function test_clerks_get_access_to_the_course(): void {
        global $DB;
        $this->assertFalse($this->clerk_in_course('a'));

        plan_confirmer::confirm($this->planid, 2);

        $this->assertTrue($this->clerk_in_course('a'));
        $this->assertTrue($this->clerk_in_course('b'));
        $userid = (int)$DB->get_field('local_entities', 'userid', ['id' => $this->clerks['a']]);
        $roles = get_user_roles(\context_course::instance($this->courseid), $userid, false);
        $this->assertSame(['teacher'], array_values(array_map(static fn($r) => $r->shortname, $roles)));
        $this->assertSame(2, $DB->count_records(occupations::TABLE, ['enrolled' => 1]), 'Only the clerks, not the examiner.');

        plan_rollback::rollback($this->planid);

        $this->assertFalse($this->clerk_in_course('a'));
        $this->assertFalse($this->clerk_in_course('b'));
    }

    /**
     * A clerk who was in the course before stays in it after a cancellation.
     *
     * @return void
     */
    public function test_clerk_who_was_in_the_course_stays(): void {
        global $DB;
        $userid = (int)$DB->get_field('local_entities', 'userid', ['id' => $this->clerks['a']]);
        self::getDataGenerator()->enrol_user($userid, $this->courseid, 'editingteacher');

        plan_confirmer::confirm($this->planid, 2);
        plan_rollback::rollback($this->planid);

        $this->assertTrue($this->clerk_in_course('a'));
        $this->assertFalse($this->clerk_in_course('b'));
    }

    /**
     * A clerk with two booked exams in the course keeps the access when one of them is cancelled.
     *
     * @return void
     */
    public function test_clerk_with_another_exam_keeps_the_access(): void {
        global $DB;
        // Both participants get clerk A.
        $DB->set_field(plan_store::TABLE_ITEM, 'clerkentityid', $this->clerks['a'], ['planid' => $this->planid]);
        plan_confirmer::confirm($this->planid, 2);
        $settings = singleton_service::get_instance_of_booking_option_settings($this->optionid);
        $option = singleton_service::get_instance_of_booking_option((int)$settings->cmid, $this->optionid);

        $option->user_delete_response($this->users['anna']);
        $this->assertTrue($this->clerk_in_course('a'), 'Ben still needs clerk A.');

        $option->user_delete_response($this->users['ben']);
        $this->assertFalse($this->clerk_in_course('a'));
    }
}
