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
 * Which booking options are planned by the trainer.
 *
 * In a planned option the trainer plans and books the exams of all participants, the participants can not
 * book a slot themselves. Only slot booking options can be planned.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class planned_options {
    /** @var string Table of the planned options. */
    public const TABLE = 'bookingextension_examplanning_option';

    /** @var array Planned state per option id, for this request. */
    private static array $cache = [];

    /**
     * Is the option planned by the trainer?
     *
     * @param int $optionid
     * @return bool
     */
    public static function is_planned(int $optionid): bool {
        global $DB;

        if ($optionid <= 0) {
            return false;
        }
        if (!array_key_exists($optionid, self::$cache)) {
            self::$cache[$optionid] = (bool)$DB->get_field(self::TABLE, 'enabled', ['optionid' => $optionid]);
        }
        return self::$cache[$optionid];
    }

    /**
     * Switches the planning by the trainer on or off.
     *
     * @param int $optionid
     * @param bool $planned
     * @return void
     */
    public static function set_planned(int $optionid, bool $planned): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['optionid' => $optionid]);
        if ($record) {
            if ((bool)$record->enabled !== $planned) {
                $DB->update_record(self::TABLE, (object)[
                    'id' => $record->id,
                    'enabled' => (int)$planned,
                    'timemodified' => time(),
                ]);
            }
        } else if ($planned) {
            $DB->insert_record(self::TABLE, (object)[
                'optionid' => $optionid,
                'enabled' => 1,
                'timemodified' => time(),
            ]);
        }
        self::$cache[$optionid] = $planned;
    }

    /**
     * Forgets an option, for example when it is deleted.
     *
     * @param int $optionid
     * @return void
     */
    public static function delete(int $optionid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['optionid' => $optionid]);
        unset(self::$cache[$optionid]);
    }

    /**
     * Forgets what was read in this request.
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }
}
