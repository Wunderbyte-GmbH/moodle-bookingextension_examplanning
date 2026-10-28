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
use local_entities\pool\pool_facade;

/**
 * The persons that can be clerks in a planned option.
 *
 * They come from the pools and the single persons of the option. Only persons count, and the examiner can not be the
 * own clerk.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class clerk_candidates {
    /**
     * The possible clerks of an option.
     *
     * @param int $optionid
     * @return int[] entity ids of persons, ascending, each once
     */
    public static function for_option(int $optionid): array {
        global $DB;

        $sources = clerk_sources::get($optionid);
        $entityids = array_merge(pool_facade::get_entityids_for_pools($sources['pools']), $sources['entities']);
        if (empty($entityids)) {
            return [];
        }

        // Only persons of users that still exist can be clerks.
        [$insql, $params] = $DB->get_in_or_equal(array_unique($entityids), SQL_PARAMS_NAMED);
        $params['persontype'] = persons::TYPE;
        $candidates = array_map('intval', $DB->get_fieldset_sql(
            "SELECT e.id
               FROM {local_entities} e
               JOIN {user} u ON u.id = e.userid AND u.deleted = 0
              WHERE e.id $insql
                AND e.entitytype = :persontype",
            $params
        ));

        // The examiner is not the own clerk.
        $examiners = array_values(examiner::existing_entityids($optionid));
        $candidates = array_values(array_diff($candidates, $examiners));
        sort($candidates);
        return $candidates;
    }
}
