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
 * The participants of a planned option: everybody with a student role in the course of the booking instance.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participants {
    /**
     * The participants of an option, ordered by name.
     *
     * @param int $optionid
     * @return \stdClass[] user records with id and the name fields, keyed by user id
     */
    public static function for_option(int $optionid): array {
        $settings = \mod_booking\singleton_service::get_instance_of_booking_option_settings($optionid);
        $context = \context_module::instance((int)$settings->cmid);
        $roleids = array_keys(get_archetype_roles('student'));
        if (empty($roleids)) {
            return [];
        }

        // With several roles Moodle wants the role assignment as first field, as a user can have more than one.
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $fields = 'ra.id, u.id AS userid, ' . $namefields;
        $users = get_role_users($roleids, $context, true, $fields, 'u.lastname, u.firstname, u.id');
        $participants = [];
        foreach ($users as $user) {
            $user->id = (int)$user->userid;
            unset($user->userid);
            $participants[$user->id] = $user;
        }
        return $participants;
    }
}
