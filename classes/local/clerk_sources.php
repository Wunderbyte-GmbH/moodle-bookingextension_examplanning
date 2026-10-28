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
 * Where the clerks of a planned option come from: pools of persons and single persons of local_entities.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class clerk_sources {
    /** @var string Table of the sources. */
    public const TABLE = 'bookingextension_examplanning_source';

    /** @var string A pool of local_entities. */
    public const POOL = 'pool';

    /** @var string A single entity of local_entities. */
    public const ENTITY = 'entity';

    /**
     * The sources of an option.
     *
     * @param int $optionid
     * @return array ['pools' => int[], 'entities' => int[]], each ascending
     */
    public static function get(int $optionid): array {
        global $DB;

        $sources = ['pools' => [], 'entities' => []];
        foreach ($DB->get_records(self::TABLE, ['optionid' => $optionid], 'sourceid') as $record) {
            $key = $record->sourcetype === self::POOL ? 'pools' : 'entities';
            $sources[$key][] = (int)$record->sourceid;
        }
        return $sources;
    }

    /**
     * Replaces the sources of one type for an option.
     *
     * @param int $optionid
     * @param string $type POOL or ENTITY
     * @param int[] $ids
     * @return void
     */
    public static function set(int $optionid, string $type, array $ids): void {
        global $DB;

        if (!in_array($type, [self::POOL, self::ENTITY], true)) {
            throw new \coding_exception('Unknown type of source: ' . $type);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::TABLE, ['optionid' => $optionid, 'sourcetype' => $type]);
        $now = time();
        foreach ($ids as $id) {
            $DB->insert_record(self::TABLE, (object)[
                'optionid' => $optionid,
                'sourcetype' => $type,
                'sourceid' => $id,
                'timemodified' => $now,
            ]);
        }
        $transaction->allow_commit();
    }

    /**
     * Forgets all sources of an option.
     *
     * @param int $optionid
     * @return void
     */
    public static function delete(int $optionid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['optionid' => $optionid]);
    }
}
