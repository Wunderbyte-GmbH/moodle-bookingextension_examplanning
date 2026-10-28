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

use mod_booking\local\interfaces\bookingextension\bookingrestriction_interface;

/**
 * Stops participants from booking a slot of an option that the trainer plans.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bookingrestriction implements bookingrestriction_interface {
    /**
     * Why the user may not book the option, or null if the option is not planned by the trainer.
     *
     * @param int $optionid
     * @param int $userid
     * @return string|null
     */
    public static function get_booking_restriction(int $optionid, int $userid): ?string {
        if (!planned_options::is_planned($optionid)) {
            return null;
        }
        return get_string('bookingrestricted', 'bookingextension_examplanning');
    }
}
