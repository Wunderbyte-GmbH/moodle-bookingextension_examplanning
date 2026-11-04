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
use bookingextension_examplanning\rules\conditions\select_clerk_in_bo;
use local_entities\persons;
use mod_booking\booking_rules\conditions_info;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests that the rule condition finds the clerks of the booked exams as recipients.
 *
 * @package     bookingextension_examplanning
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \bookingextension_examplanning\rules\conditions\select_clerk_in_bo
 */
final class select_clerk_in_bo_test extends \advanced_testcase {
    /** @var int The option. */
    private int $optionid = 0;

    /** @var array<string, int> user ids */
    private array $users = [];

    /**
     * An option with two booked exams: Anna with clerk A, Ben with clerk B. The examiner is no recipient.
     */
    protected function setUp(): void {
        global $DB;
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

        foreach (['anna', 'ben', 'clerka', 'clerkb', 'examiner'] as $name) {
            $this->users[$name] = (int)self::getDataGenerator()->create_user()->id;
        }
        $exams = [
            ['anna', 'clerka', 'clerk'], ['anna', 'examiner', 'examiner'],
            ['ben', 'clerkb', 'clerk'], ['ben', 'examiner', 'examiner'],
        ];
        foreach ($exams as [$participant, $person, $role]) {
            $DB->insert_record(occupations::TABLE, (object)[
                'optionid' => $this->optionid,
                'userid' => $this->users[$participant],
                'entityid' => persons::get_or_create_for_user($this->users[$person]),
                'role' => $role,
                'slotstart' => 1000,
                'slotend' => 2800,
            ]);
        }
    }

    /**
     * The recipients the condition finds for the option.
     *
     * @param string $json json of the rule
     * @return int[] user ids, sorted
     */
    private function recipients(string $json): array {
        global $DB;
        $condition = new select_clerk_in_bo();
        $condition->set_conditiondata_from_json($json);
        $sql = (object)['select' => 'bo.id optionid', 'from' => '{booking_options} bo', 'where' => 'bo.id = :optionid'];
        $params = ['optionid' => $this->optionid];
        $condition->execute($sql, $params);
        $userids = array_map(
            static fn($r) => (int)$r->userid,
            $DB->get_records_sql("SELECT {$sql->select} FROM {$sql->from} WHERE {$sql->where}", $params)
        );
        sort($userids);
        return $userids;
    }

    /**
     * Booking finds the condition in the extension.
     *
     * @return void
     */
    public function test_condition_is_offered(): void {
        $names = array_map(static fn($c) => $c->conditionname ?? '', conditions_info::get_conditions());
        $this->assertContains('select_clerk_in_bo', $names);
        $this->assertInstanceOf(select_clerk_in_bo::class, conditions_info::get_condition('select_clerk_in_bo'));
    }

    /**
     * Without an event all clerks of the option are recipients, the examiner is not.
     *
     * @return void
     */
    public function test_all_clerks_of_the_option(): void {
        $expected = [$this->users['clerka'], $this->users['clerkb']];
        sort($expected);
        $this->assertSame($expected, $this->recipients('{}'));
    }

    /**
     * An event about a participant narrows to the clerk of that participant.
     *
     * @return void
     */
    public function test_only_the_clerk_of_the_participant_of_the_event(): void {
        $json = json_encode(['datafromevent' => ['relateduserid' => $this->users['ben']]]);
        $this->assertSame([$this->users['clerkb']], $this->recipients($json));
    }

    /**
     * Without an event a cancelled exam has no recipient; with an event the newest exam of the participant counts,
     * also when it is cancelled.
     *
     * @return void
     */
    public function test_cancelled_and_newest_exam(): void {
        global $DB;
        $DB->set_field(occupations::TABLE, 'timecancelled', time(), ['userid' => $this->users['ben']]);
        $this->assertSame([$this->users['clerka']], $this->recipients('{}'), 'Ben is cancelled.');

        $json = json_encode(['datafromevent' => ['relateduserid' => $this->users['ben']]]);
        $this->assertSame([$this->users['clerkb']], $this->recipients($json), 'The cancelled exam of Ben.');

        // Ben is booked again, now with clerk A: the newest exam counts.
        $DB->insert_record(occupations::TABLE, (object)[
            'optionid' => $this->optionid,
            'userid' => $this->users['ben'],
            'entityid' => persons::get_entityid_for_user($this->users['clerka']),
            'role' => 'clerk',
            'slotstart' => 5000,
            'slotend' => 6800,
        ]);
        $this->assertSame([$this->users['clerka']], $this->recipients($json));
    }
}
