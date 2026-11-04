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

use bookingextension_examplanning\examplanning;
use local_entities\hook\collect_occupations;
use local_entities\local\entities\entitydate;
use mod_booking\singleton_service;

/**
 * Tells Entities at which booked exams a person is examiner or clerk, so that they show in the calendar of the person.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class occupations {
    /** @var string Table of the examiners and clerks of the booked exams. */
    public const TABLE = 'bookingextension_examplanning_staff';

    /**
     * Adds the booked exams of the entity to the hook.
     *
     * @param collect_occupations $hook
     * @return void
     */
    public static function collect(collect_occupations $hook): void {
        global $DB;

        if (!examplanning::is_globally_enabled()) {
            return;
        }
        $records = $DB->get_records_select(
            self::TABLE,
            'entityid = :entityid AND timecancelled IS NULL',
            ['entityid' => $hook->entityid],
            'slotstart'
        );
        if (!$records) {
            return;
        }

        // Examiner and clerk of an exam share option, participant and start, so both are found for every entry.
        $optionids = array_unique(array_map(static fn($r) => (int)$r->optionid, $records));
        [$insql, $params] = $DB->get_in_or_equal($optionids, SQL_PARAMS_NAMED);
        $staff = $DB->get_records_sql(
            "SELECT s.id, s.optionid, s.userid, s.slotstart, s.role, e.userid AS staffuserid
               FROM {" . self::TABLE . "} s
               JOIN {local_entities} e ON e.id = s.entityid
              WHERE s.optionid $insql AND s.timecancelled IS NULL",
            $params
        );

        $userids = array_merge(
            array_map(static fn($r) => (int)$r->userid, $records),
            array_map(static fn($s) => (int)$s->staffuserid, $staff)
        );
        $namefields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
        $users = $DB->get_records_list('user', 'id', array_unique($userids), '', 'id, ' . $namefields);
        $name = static fn(int $userid): string => isset($users[$userid]) ? fullname($users[$userid]) : '–';

        $people = [];
        foreach ($staff as $s) {
            $people["$s->optionid-$s->userid-$s->slotstart"][$s->role] = $name((int)$s->staffuserid);
        }

        foreach ($records as $record) {
            $settings = singleton_service::get_instance_of_booking_option_settings((int)$record->optionid);
            if (empty($settings->id)) {
                continue;
            }
            $exam = $people["$record->optionid-$record->userid-$record->slotstart"] ?? [];
            $text = get_string('occupation', 'bookingextension_examplanning', (object)[
                'participant' => $name((int)$record->userid),
                'option' => $settings->get_title_with_prefix(),
                'examiner' => $exam['examiner'] ?? '–',
                'clerk' => $exam['clerk'] ?? '–',
            ]);
            $link = new \moodle_url('/mod/booking/optionview.php', [
                'optionid' => (int)$record->optionid,
                'cmid' => (int)$settings->cmid,
            ]);
            $hook->add(new entitydate(
                (int)$record->id,
                'bookingextension_examplanning',
                'exam',
                $text,
                (int)$record->slotstart,
                (int)$record->slotend,
                0,
                $link
            ));
        }
    }
}
