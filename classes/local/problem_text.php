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

use local_entities\entities;

/**
 * The sentence of a problem of the plan validator, e.g. "Clerk Billy Teachy is absent."
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class problem_text {
    /** @var array<int, string> entity id => name, read once per request */
    private static array $names = [];

    /**
     * The sentence of a problem.
     *
     * @param array $problem role, entityid, reason, for a swap also userid
     * @return string
     */
    public static function describe(array $problem): string {
        $text = get_string('problem_' . $problem['reason'], 'bookingextension_examplanning', (object)[
            'role' => get_string('role_' . $problem['role'], 'bookingextension_examplanning'),
            'name' => self::name((int)$problem['entityid']),
        ]);
        if (!empty($problem['userid'])) {
            $user = \core_user::get_user((int)$problem['userid']);
            $text = get_string('problem_swap', 'bookingextension_examplanning', (object)[
                'participant' => $user ? fullname($user) : '',
                'problem' => $text,
            ]);
        }
        return $text;
    }

    /**
     * The name of an entity.
     *
     * @param int $entityid
     * @return string
     */
    private static function name(int $entityid): string {
        global $DB;
        if (!isset(self::$names[$entityid])) {
            $record = $DB->get_record('local_entities', ['id' => $entityid]);
            self::$names[$entityid] = $record ? entities::get_display_name($record) : '';
        }
        return self::$names[$entityid];
    }
}
