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

namespace bookingextension_examplanning\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy of the exam planning: the clerks of the participants, the draft plans and the persons of the booked exams.
 *
 * Every table leads to the booking option and from there to the context of the booking instance.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var array table => [alias, option id column, user columns, columns of the export] */
    private const TABLES = [
        'bookingextension_examplanning_assign' => ['t', 't.optionid', ['userid', 'usermodified'],
            ['optionid', 'clerkentityid', 'timemodified']],
        'bookingextension_examplanning_staff' => ['t', 't.optionid', ['userid', 'usermodified'],
            ['optionid', 'entityid', 'role', 'slotstart', 'slotend', 'timemodified']],
        'bookingextension_examplanning_plan' => ['t', 't.optionid', ['usermodified'],
            ['optionid', 'status', 'timecreated', 'timemodified']],
        'bookingextension_examplanning_plan_item' => ['t', 'p.optionid', ['userid'],
            ['planid', 'clerkentityid', 'slotstart', 'slotend']],
    ];

    /**
     * The personal data the plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('bookingextension_examplanning_assign', [
            'optionid' => 'privacy:metadata:assign:optionid',
            'userid' => 'privacy:metadata:assign:userid',
            'clerkentityid' => 'privacy:metadata:assign:clerkentityid',
            'usermodified' => 'privacy:metadata:assign:usermodified',
            'timemodified' => 'privacy:metadata:assign:timemodified',
        ], 'privacy:metadata:assign');
        $collection->add_database_table('bookingextension_examplanning_staff', [
            'optionid' => 'privacy:metadata:staff:optionid',
            'userid' => 'privacy:metadata:staff:userid',
            'entityid' => 'privacy:metadata:staff:entityid',
            'role' => 'privacy:metadata:staff:role',
            'slotstart' => 'privacy:metadata:staff:slotstart',
            'usermodified' => 'privacy:metadata:staff:usermodified',
        ], 'privacy:metadata:staff');
        $collection->add_database_table('bookingextension_examplanning_plan', [
            'optionid' => 'privacy:metadata:plan:optionid',
            'usermodified' => 'privacy:metadata:plan:usermodified',
            'timecreated' => 'privacy:metadata:plan:timecreated',
        ], 'privacy:metadata:plan');
        $collection->add_database_table('bookingextension_examplanning_plan_item', [
            'userid' => 'privacy:metadata:planitem:userid',
            'clerkentityid' => 'privacy:metadata:planitem:clerkentityid',
            'slotstart' => 'privacy:metadata:planitem:slotstart',
        ], 'privacy:metadata:planitem');
        return $collection;
    }

    /**
     * FROM and JOIN of a table to the context of the booking instance.
     *
     * @param string $table
     * @return string
     */
    private static function from(string $table): string {
        [$alias, $optioncolumn] = self::TABLES[$table];
        $plan = $table === 'bookingextension_examplanning_plan_item'
            ? "JOIN {bookingextension_examplanning_plan} p ON p.id = $alias.planid"
            : '';
        return "FROM {{$table}} $alias
                $plan
                JOIN {booking_options} bo ON bo.id = $optioncolumn
                JOIN {course_modules} cm ON cm.instance = bo.bookingid
                JOIN {modules} m ON m.id = cm.module AND m.name = 'booking'
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel";
    }

    /**
     * The contexts in which the user has data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::TABLES as $table => [$alias, , $usercolumns]) {
            $where = implode(' OR ', array_map(static fn($c) => "$alias.$c = :$c", $usercolumns));
            $params = ['contextlevel' => CONTEXT_MODULE];
            foreach ($usercolumns as $column) {
                $params[$column] = $userid;
            }
            $contextlist->add_from_sql('SELECT ctx.id ' . self::from($table) . " WHERE $where", $params);
        }
        return $contextlist;
    }

    /**
     * The users with data in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = ['contextlevel' => CONTEXT_MODULE, 'contextid' => $context->id];
        foreach (self::TABLES as $table => [$alias, , $usercolumns]) {
            foreach ($usercolumns as $column) {
                $sql = "SELECT $alias.$column " . self::from($table) . ' WHERE ctx.id = :contextid';
                $userlist->add_from_sql($column, $sql, $params);
            }
        }
    }

    /**
     * Exports the data of the user, where the user is the participant.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $export = [];
            foreach (self::TABLES as $table => [$alias, , $usercolumns, $columns]) {
                if (!in_array('userid', $usercolumns, true)) {
                    continue;
                }
                $records = $DB->get_records_sql(
                    "SELECT $alias.* " . self::from($table) . " WHERE ctx.id = :contextid AND $alias.userid = :userid",
                    ['contextlevel' => CONTEXT_MODULE, 'contextid' => $context->id, 'userid' => $userid]
                );
                foreach ($records as $record) {
                    $row = [];
                    foreach ($columns as $column) {
                        $row[$column] = str_starts_with($column, 'time') || str_starts_with($column, 'slot')
                            ? transform::datetime((int)$record->$column)
                            : $record->$column;
                    }
                    $export[$table][] = (object)$row;
                }
            }
            if ($export) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'bookingextension_examplanning')],
                    (object)$export
                );
            }
        }
    }

    /**
     * Deletes all data in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        foreach (array_keys(self::TABLES) as $table) {
            self::delete_where($table, $context, '', []);
        }
    }

    /**
     * Deletes the data of the user, where the user is the participant, in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            foreach (self::TABLES as $table => [$alias, , $usercolumns]) {
                if (in_array('userid', $usercolumns, true)) {
                    self::delete_where($table, $context, "AND $alias.userid = :userid", ['userid' => $userid]);
                }
            }
        }
    }

    /**
     * Deletes the data of the users in the approved list, where they are the participants.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_userids()) {
            return;
        }
        foreach (self::TABLES as $table => [$alias, , $usercolumns]) {
            if (in_array('userid', $usercolumns, true)) {
                [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
                self::delete_where($table, $userlist->get_context(), "AND $alias.userid $insql", $inparams);
            }
        }
    }

    /**
     * Deletes the rows of a table in a module context that match the extra condition.
     *
     * @param string $table
     * @param \context $context
     * @param string $where extra condition, starting with AND
     * @param array $params
     * @return void
     */
    private static function delete_where(string $table, \context $context, string $where, array $params): void {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $alias = self::TABLES[$table][0];
        $params += ['contextlevel' => CONTEXT_MODULE, 'contextid' => $context->id];
        $ids = $DB->get_fieldset_sql("SELECT $alias.id " . self::from($table) . " WHERE ctx.id = :contextid $where", $params);
        if ($ids) {
            $DB->delete_records_list($table, 'id', $ids);
        }
    }
}
