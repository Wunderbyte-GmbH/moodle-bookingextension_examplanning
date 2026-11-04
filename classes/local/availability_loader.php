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

use local_entities\daily_limit;
use local_entities\entities;
use local_entities\planning_period;
use local_entities\unavailability;

/**
 * Loads once from the database what the availability of some persons in a time range needs.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class availability_loader {
    /**
     * Loads the availability of persons.
     *
     * The range is widened to whole days, so that the daily limit sees all exams of the first and the last day.
     *
     * @param int[] $entityids the persons
     * @param int $from
     * @param int $until
     * @param int $optionid the option that is planned: its own dates are no teaching dates of its trainer
     * @return availability
     */
    public static function load(array $entityids, int $from, int $until, int $optionid): availability {
        global $DB;

        $entityids = array_values(array_unique(array_map('intval', $entityids)));
        $timezone = \core_date::get_server_timezone_object();
        $from = (new \DateTimeImmutable('@' . $from))->setTimezone($timezone)->setTime(0, 0)->getTimestamp();
        $until = (new \DateTimeImmutable('@' . $until))->setTimezone($timezone)->setTime(0, 0)->modify('+1 day')
            ->getTimestamp();

        $absences = [];
        $occupations = [];
        foreach ($entityids as $entityid) {
            foreach (unavailability::get_blocks($entityid, $from, $until) as $block) {
                $absences[$entityid][] = [(int)$block->starttime, (int)$block->endtime];
            }
            foreach (entities::get_all_dates_for_entity($entityid, true) as $date) {
                if ((int)$date->status !== 1 && $date->starttime < $until && $date->endtime > $from) {
                    $occupations[$entityid][] = [(int)$date->starttime, (int)$date->endtime];
                }
            }
        }

        return new availability(
            $absences,
            $occupations,
            self::load_teaching($entityids, $from, $until, $optionid),
            self::load_exams($entityids, $from, $until),
            daily_limit::get($entityids),
            planning_period::get(),
            $timezone
        );
    }

    /**
     * The dates at which the persons teach at other booking options.
     *
     * @param int[] $entityids
     * @param int $from
     * @param int $until
     * @param int $optionid
     * @return array entity id => [start, end][]
     */
    private static function load_teaching(array $entityids, int $from, int $until, int $optionid): array {
        global $DB;

        if (!$entityids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($entityids, SQL_PARAMS_NAMED);
        $records = $DB->get_recordset_sql(
            "SELECT t.id, e.id AS entityid, d.coursestarttime, d.courseendtime
               FROM {local_entities} e
               JOIN {booking_optiondates_teachers} t ON t.userid = e.userid
               JOIN {booking_optiondates} d ON d.id = t.optiondateid
              WHERE e.id $insql
                AND d.optionid <> :optionid
                AND d.coursestarttime < :until
                AND d.courseendtime > :from",
            $params + ['optionid' => $optionid, 'from' => $from, 'until' => $until]
        );
        $teaching = [];
        foreach ($records as $record) {
            $teaching[(int)$record->entityid][] = [(int)$record->coursestarttime, (int)$record->courseendtime];
        }
        $records->close();
        return $teaching;
    }

    /**
     * The booked exams of the persons, for the daily limit.
     *
     * @param int[] $entityids
     * @param int $from
     * @param int $until
     * @return array entity id => [start, end][]
     */
    private static function load_exams(array $entityids, int $from, int $until): array {
        global $DB;

        if (!$entityids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($entityids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            occupations::TABLE,
            "entityid $insql AND slotstart < :until AND slotend > :from AND timecancelled IS NULL",
            $params + ['from' => $from, 'until' => $until],
            '',
            'id, entityid, slotstart, slotend'
        );
        $exams = [];
        foreach ($records as $record) {
            $exams[(int)$record->entityid][] = [(int)$record->slotstart, (int)$record->slotend];
        }
        return $exams;
    }
}
