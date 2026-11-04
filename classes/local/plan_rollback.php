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

use mod_booking\singleton_service;

/**
 * Takes back a booked plan: cancels all its bookings and makes it a draft plan again. Only before its first exam.
 *
 * Each booking is cancelled in the normal way of Booking, so the event of the cancellation marks the exams as
 * cancelled and rules on it can tell the clerks.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_rollback {
    /**
     * Takes back a booked plan.
     *
     * @param int $planid
     * @return int number of cancelled bookings
     * @throws \moodle_exception if the plan is not booked, its first exam has begun or a cancellation fails
     */
    public static function rollback(int $planid): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/booking/lib.php');

        // The same lock as for booking, so taking back and booking never run at the same time.
        $lock = \core\lock\lock_config::get_lock_factory('bookingextension_examplanning')->get_lock('confirm_' . $planid, 0);
        if (!$lock) {
            throw new \moodle_exception('confirm_busy', 'bookingextension_examplanning');
        }
        try {
            return self::rollback_locked($planid);
        } finally {
            $lock->release();
        }
    }

    /**
     * Takes back a booked plan while holding the lock.
     *
     * @param int $planid
     * @return int number of cancelled bookings
     */
    private static function rollback_locked(int $planid): int {
        global $DB;

        $plan = plan_store::get($planid);
        if ($plan === null || $plan->status !== plan_store::BOOKED) {
            throw new \moodle_exception('rollback_notbooked', 'bookingextension_examplanning');
        }
        $staff = $DB->get_records_select(
            occupations::TABLE,
            'runid = :runid AND timecancelled IS NULL',
            ['runid' => $planid]
        );
        $first = $staff ? min(array_map(static fn($row) => (int)$row->slotstart, $staff)) : PHP_INT_MAX;
        if ($first <= time()) {
            throw new \moodle_exception('rollback_started', 'bookingextension_examplanning');
        }

        $optionid = (int)$plan->optionid;
        $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
        $option = singleton_service::get_instance_of_booking_option((int)$settings->cmid, $optionid);
        $userids = array_values(array_unique(array_map(static fn($row) => (int)$row->userid, $staff)));

        $transaction = $DB->start_delegated_transaction();
        foreach ($userids as $userid) {
            if (!$option->user_delete_response($userid)) {
                $user = \core_user::get_user($userid);
                $transaction->rollback(new \moodle_exception(
                    'rollback_failed',
                    'bookingextension_examplanning',
                    '',
                    $user ? fullname($user) : $userid
                ));
            }
        }
        // The event of each cancellation marked the exams already. This also covers a cancellation without event.
        $DB->set_field_select(
            occupations::TABLE,
            'timecancelled',
            time(),
            'runid = :runid AND timecancelled IS NULL',
            ['runid' => $planid]
        );
        plan_store::set_status($planid, plan_store::READY);
        $transaction->allow_commit();
        return count($userids);
    }
}
