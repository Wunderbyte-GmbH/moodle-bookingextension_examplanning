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

use local_entities\persons;

/**
 * The examiner of a planned option: always the one trainer of the option.
 *
 * The examiner has to be a person in Entities, so that absences and the slots per day can be checked. If the
 * trainer has no person yet, it is created here. This is the one case in which Booking creates entities.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class examiner {
    /** @var string The option has no trainer. */
    public const PROBLEM_NONE = 'examiner_none';

    /** @var string The option has more than one trainer. */
    public const PROBLEM_MANY = 'examiner_many';

    /**
     * The trainers of an option.
     *
     * @param int $optionid
     * @return int[] user ids, in ascending order
     */
    public static function trainer_userids(int $optionid): array {
        global $DB;

        $sql = "SELECT DISTINCT bt.userid
                  FROM {booking_teachers} bt
                  JOIN {user} u ON u.id = bt.userid AND u.deleted = 0
                 WHERE bt.optionid = :optionid
              ORDER BY bt.userid";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['optionid' => $optionid]));
    }

    /**
     * What stops the option from having an examiner.
     *
     * @param int $optionid
     * @return string|null PROBLEM_NONE or PROBLEM_MANY, null if the option has exactly one trainer
     */
    public static function get_problem(int $optionid): ?string {
        $count = count(self::trainer_userids($optionid));
        if ($count === 0) {
            return self::PROBLEM_NONE;
        }
        return $count > 1 ? self::PROBLEM_MANY : null;
    }

    /**
     * The problem as a sentence for the trainer.
     *
     * @param string $problem PROBLEM_NONE or PROBLEM_MANY
     * @return string
     */
    public static function describe_problem(string $problem): string {
        return get_string($problem, 'bookingextension_examplanning');
    }

    /**
     * The person of the one trainer, created if it does not exist yet.
     *
     * @param int $optionid
     * @return int entity id of the examiner
     * @throws \moodle_exception if the option has no trainer or more than one
     */
    public static function get_entityid(int $optionid): int {
        $problem = self::get_problem($optionid);
        if ($problem !== null) {
            throw new \moodle_exception($problem, 'bookingextension_examplanning');
        }
        return persons::get_or_create_for_user(self::trainer_userids($optionid)[0]);
    }

    /**
     * The persons of the trainers of an option that exist already, without creating any.
     *
     * @param int $optionid
     * @return int[] user id => entity id
     */
    public static function existing_entityids(int $optionid): array {
        $entityids = [];
        foreach (self::trainer_userids($optionid) as $userid) {
            $entityid = persons::get_entityid_for_user($userid);
            if ($entityid !== null) {
                $entityids[$userid] = $entityid;
            }
        }
        return $entityids;
    }

    /**
     * Creates the persons of all trainers of an option that have none yet.
     *
     * @param int $optionid
     * @return int[] user id => entity id
     */
    public static function ensure_persons(int $optionid): array {
        $entityids = [];
        foreach (self::trainer_userids($optionid) as $userid) {
            $entityids[$userid] = persons::get_or_create_for_user($userid);
        }
        return $entityids;
    }
}
