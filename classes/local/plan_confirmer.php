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

namespace bookingextension_examplanning\local;

use mod_booking\booking_option;
use mod_booking\booking_option_settings;
use mod_booking\local\mobile\slotbookingstore;
use mod_booking\singleton_service;

/**
 * Books a draft plan: all or nothing.
 *
 * Only when every participant is planned and every exam still works with the data of now, each participant is booked
 * into the slot in the normal way of booking for others. Examiner and clerk are stored with the run. If one booking
 * fails, nothing is booked.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_confirmer {
    /**
     * Books a run.
     *
     * @param int $planid
     * @param int $usermodified who confirms
     * @return int number of booked participants
     * @throws \moodle_exception with the reasons, when nothing is booked
     */
    public static function confirm(int $planid, int $usermodified): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/booking/lib.php');

        $lock = \core\lock\lock_config::get_lock_factory('bookingextension_examplanning')->get_lock('confirm_' . $planid, 0);
        if (!$lock) {
            throw new \moodle_exception('confirm_busy', 'bookingextension_examplanning');
        }
        try {
            return self::confirm_locked($planid, $usermodified);
        } finally {
            $lock->release();
        }
    }

    /**
     * Books a run while holding the lock.
     *
     * @param int $planid
     * @param int $usermodified
     * @return int number of booked participants
     */
    private static function confirm_locked(int $planid, int $usermodified): int {
        global $DB;

        $plan = plan_editor::ready_plan($planid);
        $items = plan_store::items($planid);
        if (!$items) {
            throw new \moodle_exception('confirm_empty', 'bookingextension_examplanning');
        }
        $users = $DB->get_records_list('user', 'id', array_keys($items));

        // Every participant needs a slot. Otherwise the trainer clarifies it with the clerks first.
        $unplanned = array_filter($items, static fn($item) => $item->slotstart === null);
        if ($unplanned) {
            $names = array_map(static fn($item) => fullname($users[$item->userid]), $unplanned);
            throw new \moodle_exception('confirm_unplanned', 'bookingextension_examplanning', '', implode(', ', $names));
        }

        // Every exam must still work with the data of now.
        $validator = plan_editor::validator($plan);
        $problems = [];
        foreach ($items as $userid => $item) {
            $problem = $validator->check_move((int)$userid, (int)$item->slotstart, (int)$item->slotend);
            if ($problem !== null) {
                $problems[] = fullname($users[$userid]) . ': ' . problem_text::describe($problem);
            }
        }
        if ($problems) {
            throw new \moodle_exception('confirm_problems', 'bookingextension_examplanning', '', implode(' ', $problems));
        }

        $optionid = (int)$plan->optionid;
        $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
        $option = singleton_service::get_instance_of_booking_option((int)$settings->cmid, $optionid);

        plan_store::set_status($planid, plan_store::CONFIRMING);
        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($items as $item) {
                self::book($option, $settings, $item, $planid, $usermodified, fullname($users[$item->userid]));
            }
            plan_store::set_status($planid, plan_store::BOOKED);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $rolledback) {
                // The rollback throws the error again; it is thrown below after the run is open again.
                unset($rolledback);
            }
            plan_store::set_status($planid, plan_store::READY);
            throw $e;
        }
        return count($items);
    }

    /**
     * Books one participant into the slot and stores examiner and clerk.
     *
     * The persons are stored before the booking, so a rule that reacts on the booking finds the clerk.
     *
     * @param booking_option $option
     * @param booking_option_settings $settings
     * @param \stdClass $item
     * @param int $planid
     * @param int $usermodified
     * @param string $name name of the participant, for the error
     * @return void
     */
    private static function book(
        booking_option $option,
        booking_option_settings $settings,
        \stdClass $item,
        int $planid,
        int $usermodified,
        string $name
    ): void {
        global $DB;

        $userid = (int)$item->userid;
        $staffids = [];
        foreach (['examiner' => $item->examinerentityid, 'clerk' => $item->clerkentityid] as $role => $entityid) {
            $staffids[] = $DB->insert_record(occupations::TABLE, (object)[
                'optionid' => (int)$settings->id,
                'userid' => $userid,
                'entityid' => (int)$entityid,
                'role' => $role,
                'slotstart' => (int)$item->slotstart,
                'slotend' => (int)$item->slotend,
                'runid' => $planid,
                'usermodified' => $usermodified,
                'timemodified' => time(),
            ]);
        }

        $store = new slotbookingstore($userid, (int)$settings->id);
        $store->set_slotbooking_data((object)[
            'slot_selection' => $item->slotstart . ':' . $item->slotend,
            'slot_teacher_selection' => '{}',
        ]);
        $user = singleton_service::get_instance_of_user($userid);
        $booked = $option->user_submit_response($user, 0, 0, MOD_BOOKING_BO_SUBMIT_STATUS_DEFAULT, MOD_BOOKING_VERIFIED);
        $answerid = $booked ? $DB->get_field('booking_answers', 'id', [
            'optionid' => (int)$settings->id,
            'userid' => $userid,
            'waitinglist' => MOD_BOOKING_STATUSPARAM_BOOKED,
        ], IGNORE_MULTIPLE) : false;
        if (!$answerid) {
            throw new \moodle_exception('confirm_bookingfailed', 'bookingextension_examplanning', '', $name);
        }
        [$insql, $params] = $DB->get_in_or_equal($staffids);
        $DB->set_field_select(occupations::TABLE, 'answerid', $answerid, "id $insql", $params);
        foreach ($staffids as $staffid) {
            clerk_enrolment::enrol((int)$staffid);
        }
    }
}
