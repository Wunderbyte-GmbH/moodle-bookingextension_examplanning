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

use bookingextension_examplanning\local\planned_options;
use local_entities\persons;

/**
 * Reacts on events of Booking.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A trainer was added to an option. In a planned option the trainer is the examiner and needs a person.
     *
     * @param \mod_booking\event\teacher_added $event
     * @return void
     */
    public static function teacher_added(\mod_booking\event\teacher_added $event): void {
        if (!examplanning::is_globally_enabled() || !planned_options::is_planned((int)$event->objectid)) {
            return;
        }
        $userid = (int)$event->relateduserid;
        if ($userid > 0) {
            persons::get_or_create_for_user($userid);
        }
    }
}
