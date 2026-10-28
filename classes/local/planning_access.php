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

/**
 * Who may plan the exams of an option: its trainers and whoever may edit the booking options.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class planning_access {
    /**
     * May the user plan the exams of the option?
     *
     * @param int $optionid
     * @param int|null $userid null for the current user
     * @return bool
     */
    public static function can_plan(int $optionid, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int)$USER->id;
        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }
        if (in_array($userid, examiner::trainer_userids($optionid), true)) {
            return true;
        }
        $settings = \mod_booking\singleton_service::get_instance_of_booking_option_settings($optionid);
        return has_capability('mod/booking:updatebooking', \context_module::instance((int)$settings->cmid), $userid);
    }

    /**
     * Throws an exception if the current user may not plan the exams of the option.
     *
     * @param int $optionid
     * @return void
     * @throws \required_capability_exception
     */
    public static function require_plan(int $optionid): void {
        if (self::can_plan($optionid)) {
            return;
        }
        $settings = \mod_booking\singleton_service::get_instance_of_booking_option_settings($optionid);
        throw new \required_capability_exception(
            \context_module::instance((int)$settings->cmid),
            'mod/booking:updatebooking',
            'nopermissions',
            ''
        );
    }
}
