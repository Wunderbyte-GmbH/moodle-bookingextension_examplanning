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

use bookingextension_examplanning\local\distribution\distribution_result;

/**
 * Stores the runs of the distribution and what each run found for the participants.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_store {
    /** @var string Table of the runs. */
    public const TABLE_PLAN = 'bookingextension_examplanning_plan';
    /** @var string Table of the participants of a run. */
    public const TABLE_ITEM = 'bookingextension_examplanning_plan_item';

    /** @var string The run waits for the task. */
    public const QUEUED = 'queued';
    /** @var string The task computes the run. */
    public const RUNNING = 'running';
    /** @var string The run is computed and can be looked at. */
    public const READY = 'ready';
    /** @var string The task could not compute the run. */
    public const FAILED = 'failed';
    /** @var string The run is being booked; nobody else may change or confirm it now. */
    public const CONFIRMING = 'confirming';
    /** @var string The run is booked. */
    public const BOOKED = 'booked';

    /**
     * Starts a new run of an option.
     *
     * @param int $optionid
     * @param int|null $seed null for the plain order, a number for a shuffle
     * @param int $userid who started it
     * @return int id of the run
     */
    public static function create(int $optionid, ?int $seed, int $userid): int {
        global $DB;
        $now = time();
        return (int)$DB->insert_record(self::TABLE_PLAN, (object)[
            'optionid' => $optionid,
            'status' => self::QUEUED,
            'seed' => $seed,
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * A run.
     *
     * @param int $planid
     * @return \stdClass|null
     */
    public static function get(int $planid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE_PLAN, ['id' => $planid]) ?: null;
    }

    /**
     * The newest run of an option.
     *
     * @param int $optionid
     * @return \stdClass|null
     */
    public static function latest(int $optionid): ?\stdClass {
        global $DB;
        $records = $DB->get_records(self::TABLE_PLAN, ['optionid' => $optionid], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Changes the status of a run.
     *
     * @param int $planid
     * @param string $status
     * @param string|null $error why the run failed
     * @return void
     */
    public static function set_status(int $planid, string $status, ?string $error = null): void {
        global $DB;
        $DB->update_record(self::TABLE_PLAN, (object)[
            'id' => $planid,
            'status' => $status,
            'error' => $error,
            'timemodified' => time(),
        ]);
    }

    /**
     * Stores what the distribution found and marks the run as ready.
     *
     * @param int $planid
     * @param int|null $examinerentityid
     * @param array $clerks user id => entity id of the clerk, 0 for none
     * @param distribution_result $result
     * @return void
     */
    public static function save_result(int $planid, ?int $examinerentityid, array $clerks, distribution_result $result): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::TABLE_ITEM, ['planid' => $planid]);
        $planned = $result->get_planned();
        $unplanned = $result->get_unplanned();
        foreach ($clerks as $userid => $clerkentityid) {
            $slot = $planned[(int)$userid] ?? null;
            $DB->insert_record(self::TABLE_ITEM, (object)[
                'planid' => $planid,
                'userid' => (int)$userid,
                'examinerentityid' => $examinerentityid,
                'clerkentityid' => (int)$clerkentityid ?: null,
                'slotstart' => $slot[0] ?? null,
                'slotend' => $slot[1] ?? null,
                'reasons' => isset($unplanned[(int)$userid]) ? json_encode($unplanned[(int)$userid]) : null,
            ]);
        }
        self::set_status($planid, self::READY);
        $transaction->allow_commit();
    }

    /**
     * The participants of a run, the planned ones first by time, then the others.
     *
     * @param int $planid
     * @return \stdClass[] keyed by user id, reasons decoded to an array
     */
    public static function items(int $planid): array {
        global $DB;
        $items = [];
        $records = $DB->get_records(self::TABLE_ITEM, ['planid' => $planid], 'slotstart IS NULL, slotstart, id');
        foreach ($records as $record) {
            $record->reasons = $record->reasons ? json_decode($record->reasons, true) : [];
            $items[(int)$record->userid] = $record;
        }
        return $items;
    }
}
