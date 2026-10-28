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
 * Which clerk is assigned to which participant of a planned option. There is no date yet.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignments {
    /** @var string Table of the assignments. */
    public const TABLE = 'bookingextension_examplanning_assign';

    /**
     * The clerks of the participants of an option.
     *
     * @param int $optionid
     * @return int[] user id of the participant => entity id of the clerk
     */
    public static function get(int $optionid): array {
        global $DB;
        return array_map('intval', $DB->get_records_menu(self::TABLE, ['optionid' => $optionid], '', 'userid, clerkentityid'));
    }

    /**
     * Stores the clerks of the participants of an option. 0 removes the clerk of a participant.
     *
     * Only participants of the option and possible clerks of the option are accepted.
     *
     * @param int $optionid
     * @param array $clerks user id of the participant => entity id of the clerk, 0 for none
     * @param int $usermodified
     * @return void
     * @throws \invalid_parameter_exception if a participant or a clerk is not allowed
     */
    public static function save(int $optionid, array $clerks, int $usermodified): void {
        global $DB;

        $participants = participants::for_option($optionid);
        $candidates = array_flip(clerk_candidates::for_option($optionid));
        foreach ($clerks as $userid => $clerkentityid) {
            if (!isset($participants[(int)$userid])) {
                throw new \invalid_parameter_exception("User $userid is no participant of the option.");
            }
            if ((int)$clerkentityid !== 0 && !isset($candidates[(int)$clerkentityid])) {
                throw new \invalid_parameter_exception("Entity $clerkentityid can not be a clerk of the option.");
            }
        }

        $existing = $DB->get_records(self::TABLE, ['optionid' => $optionid], '', 'userid, id, clerkentityid');
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        foreach ($clerks as $userid => $clerkentityid) {
            $userid = (int)$userid;
            $clerkentityid = (int)$clerkentityid;
            $record = $existing[$userid] ?? null;
            if ($clerkentityid === 0) {
                if ($record) {
                    $DB->delete_records(self::TABLE, ['id' => $record->id]);
                }
            } else if (!$record) {
                $DB->insert_record(self::TABLE, (object)[
                    'optionid' => $optionid,
                    'userid' => $userid,
                    'clerkentityid' => $clerkentityid,
                    'usermodified' => $usermodified,
                    'timemodified' => $now,
                ]);
            } else if ((int)$record->clerkentityid !== $clerkentityid) {
                $DB->update_record(self::TABLE, (object)[
                    'id' => $record->id,
                    'clerkentityid' => $clerkentityid,
                    'usermodified' => $usermodified,
                    'timemodified' => $now,
                ]);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Forgets all assignments of an option.
     *
     * @param int $optionid
     * @return void
     */
    public static function delete(int $optionid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['optionid' => $optionid]);
    }
}
