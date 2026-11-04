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

use bookingextension_examplanning\local\plan_store;
use bookingextension_examplanning\local\planned_options;
use bookingextension_examplanning\task\compute_plan;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests that the task computes a run in the background and tells who started it.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\task\compute_plan
 */
final class compute_plan_test extends \advanced_testcase {
    /** @var int Id of the booking option. */
    private int $optionid = 0;

    /** @var int Who starts the run. */
    private int $trainer = 0;

    /**
     * Creates a planned option.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        /** @var \mod_booking_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $booking = $generator->create_instance(['course' => $course->id]);
        $this->optionid = (int)$generator->create_option((object)[
            'bookingid' => $booking->id,
            'text' => 'Exam',
            'course' => $course->id,
        ])->id;
        set_config(examplanning::CONFIG_ENABLED, 1, 'bookingextension_examplanning');
        planned_options::set_planned($this->optionid, true);
        $this->trainer = (int)self::getDataGenerator()->create_user()->id;
    }

    /**
     * The queued task computes the run and sends one message to who started it.
     *
     * @return void
     */
    public function test_task_computes_and_tells(): void {
        $planid = plan_store::create($this->optionid, null, $this->trainer);
        compute_plan::queue($planid, $this->trainer);

        $sink = $this->redirectMessages();
        ob_start();
        $this->runAdhocTasks(compute_plan::class);
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame(plan_store::READY, plan_store::get($planid)->status);
        $this->assertCount(1, $messages);
        $this->assertSame($this->trainer, (int)$messages[0]->useridto);
        $this->assertSame('planready', $messages[0]->eventtype);
        $this->assertStringContainsString('Exam', $messages[0]->subject);
    }

    /**
     * A run that is gone sends nothing and is not tried again.
     *
     * @return void
     */
    public function test_run_that_is_gone(): void {
        global $DB;
        $planid = plan_store::create($this->optionid, null, $this->trainer);
        compute_plan::queue($planid, $this->trainer);
        $DB->delete_records(plan_store::TABLE_PLAN, ['id' => $planid]);

        $sink = $this->redirectMessages();
        ob_start();
        $this->runAdhocTasks(compute_plan::class);
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages);
        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\' . compute_plan::class]));
    }
}
