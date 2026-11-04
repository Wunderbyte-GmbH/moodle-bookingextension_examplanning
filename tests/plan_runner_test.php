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
use bookingextension_examplanning\local\distribution\distribution_strategy;
use bookingextension_examplanning\local\plan_runner;
use bookingextension_examplanning\local\plan_store;
use bookingextension_examplanning\local\planned_options;
use local_entities\persons;
use local_entities\planning_period;
use mod_booking\singleton_service;
use mod_booking\teachers_handler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests that a run gathers the participants, clerks and slots of an option and stores what the strategy found.
 *
 * The strategy is replaced by one that remembers what it got, so the test does not depend on the slot grid.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\local\plan_runner
 * @covers      \bookingextension_examplanning\local\plan_store
 */
final class plan_runner_test extends \advanced_testcase {
    /** @var int Id of the booking option. */
    private int $optionid = 0;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var int Course module id. */
    private int $cmid = 0;

    /**
     * Creates a planned option with a trainer.
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
        set_config(planning_period::CONFIG_START, '', 'local_entities');
        set_config(planning_period::CONFIG_END, '', 'local_entities');
    }

    /**
     * A strategy that remembers its input and plans everybody in a fixed slot, or fails.
     *
     * @param bool $fail throw instead of planning
     * @return distribution_strategy
     */
    private static function strategy(bool $fail = false): distribution_strategy {
        return new class ($fail) implements distribution_strategy {
            /** @var array What the strategy got. */
            public array $got = [];

            /**
             * Constructor.
             *
             * @param bool $fail
             */
            public function __construct(
                /** @var bool Throw instead of planning. */
                private bool $fail
            ) {
            }

            /**
             * Plans everybody with a clerk at 1000.
             *
             * @param int|null $examinerentityid
             * @param array $clerks
             * @param array $slots
             * @param int $gap
             * @param \bookingextension_examplanning\local\availability $availability
             * @param int|null $seed
             * @return distribution_result
             */
            public function distribute(
                ?int $examinerentityid,
                array $clerks,
                array $slots,
                int $gap,
                \bookingextension_examplanning\local\availability $availability,
                ?int $seed = null
            ): distribution_result {
                if ($this->fail) {
                    throw new \moodle_exception('error');
                }
                $this->got = ['examiner' => $examinerentityid, 'clerks' => $clerks, 'seed' => $seed];
                $result = new distribution_result();
                foreach ($clerks as $userid => $clerk) {
                    if ($clerk) {
                        $result->plan((int)$userid, 1000, 2800);
                    } else {
                        $result->fail((int)$userid, [['reason' => distribution_result::NO_CLERK, 'role' => '', 'count' => 0]]);
                    }
                }
                return $result;
            }
        };
    }

    /**
     * The run gets examiner, participants with clerks and seed, skips who is booked, and stores the result.
     *
     * @return void
     */
    public function test_run_gathers_and_stores(): void {
        global $DB;
        $trainer = (int)self::getDataGenerator()->create_user()->id;
        (new teachers_handler($this->optionid))->subscribe_teacher_to_booking_option($trainer, $this->optionid, $this->cmid);
        $withclerk = (int)self::getDataGenerator()->create_user()->id;
        $withoutclerk = (int)self::getDataGenerator()->create_user()->id;
        $booked = (int)self::getDataGenerator()->create_user()->id;
        foreach ([$withclerk, $withoutclerk, $booked] as $userid) {
            self::getDataGenerator()->enrol_user($userid, $this->course->id, 'student');
        }
        $clerk = persons::get_or_create_for_user((int)self::getDataGenerator()->create_user()->id);
        clerk_sources::set($this->optionid, clerk_sources::ENTITY, [$clerk]);
        assignments::save($this->optionid, [$withclerk => $clerk, $booked => $clerk], 2);
        $DB->insert_record('booking_answers', (object)[
            'bookingid' => singleton_service::get_instance_of_booking_option_settings($this->optionid)->bookingid,
            'optionid' => $this->optionid,
            'userid' => $booked,
            'waitinglist' => MOD_BOOKING_STATUSPARAM_BOOKED,
            'timemodified' => time(),
            'timecreated' => time(),
        ]);

        $planid = plan_store::create($this->optionid, 7, 2);
        $this->assertSame(plan_store::QUEUED, plan_store::get($planid)->status);
        $strategy = self::strategy();
        plan_runner::run($planid, $strategy);

        $this->assertSame(persons::get_entityid_for_user($trainer), $strategy->got['examiner']);
        $this->assertEquals([$withclerk => $clerk, $withoutclerk => 0], $strategy->got['clerks']);
        $this->assertSame(7, (int)$strategy->got['seed']);

        $this->assertSame(plan_store::READY, plan_store::get($planid)->status);
        $items = plan_store::items($planid);
        $this->assertSame([$withclerk, $withoutclerk], array_keys($items), 'Planned first, then the others.');
        $this->assertSame(1000, (int)$items[$withclerk]->slotstart);
        $this->assertSame($clerk, (int)$items[$withclerk]->clerkentityid);
        $this->assertNull($items[$withoutclerk]->slotstart);
        $this->assertSame(distribution_result::NO_CLERK, $items[$withoutclerk]->reasons[0]['reason']);
        $this->assertSame($planid, (int)plan_store::latest($this->optionid)->id);
    }

    /**
     * Without trainer the examiner is null, and a run that breaks is marked as failed.
     *
     * @return void
     */
    public function test_no_examiner_and_failure(): void {
        $strategy = self::strategy();
        plan_runner::run(plan_store::create($this->optionid, null, 2), $strategy);
        $this->assertNull($strategy->got['examiner']);

        $planid = plan_store::create($this->optionid, null, 2);
        try {
            plan_runner::run($planid, self::strategy(true));
            $this->fail('The run must throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame(plan_store::FAILED, plan_store::get($planid)->status);
            $this->assertNotEmpty(plan_store::get($planid)->error);
        }
    }
}
