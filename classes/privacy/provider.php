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
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy of the exam planning: which clerk is assigned to which participant, and who changed it last.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Table of the assignments. */
    private const TABLE = 'bookingextension_examplanning_assign';

    /** @var string Joins an assignment to the context of its booking instance. */
    private const JOIN = "FROM {bookingextension_examplanning_assign} a
                          JOIN {booking_options} bo ON bo.id = a.optionid
                          JOIN {course_modules} cm ON cm.instance = bo.bookingid
                          JOIN {modules} m ON m.id = cm.module AND m.name = 'booking'
                          JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel";

    /**
     * The personal data the plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'optionid' => 'privacy:metadata:assign:optionid',
            'userid' => 'privacy:metadata:assign:userid',
            'clerkentityid' => 'privacy:metadata:assign:clerkentityid',
            'usermodified' => 'privacy:metadata:assign:usermodified',
            'timemodified' => 'privacy:metadata:assign:timemodified',
        ], 'privacy:metadata:assign');
        return $collection;
    }

    /**
     * The contexts in which the user is a participant or changed an assignment.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT ctx.id ' . self::JOIN . ' WHERE a.userid = :userid OR a.usermodified = :usermodified',
            ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid, 'usermodified' => $userid]
        );
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
        $userlist->add_from_sql('userid', 'SELECT a.userid ' . self::JOIN . ' WHERE ctx.id = :contextid', $params);
        $userlist->add_from_sql('usermodified', 'SELECT a.usermodified ' . self::JOIN . ' WHERE ctx.id = :contextid', $params);
    }

    /**
     * Exports the clerks assigned to the user.
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
            $records = $DB->get_records_sql(
                'SELECT a.* ' . self::JOIN . ' WHERE ctx.id = :contextid AND a.userid = :userid',
                ['contextlevel' => CONTEXT_MODULE, 'contextid' => $context->id, 'userid' => $userid]
            );
            if (!$records) {
                continue;
            }
            $data = array_map(static fn($r) => (object)[
                'optionid' => $r->optionid,
                'clerkentityid' => $r->clerkentityid,
                'timemodified' => \core_privacy\local\request\transform::datetime($r->timemodified),
            ], array_values($records));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'bookingextension_examplanning')],
                (object)['assignments' => $data]
            );
        }
    }

    /**
     * Deletes all assignments in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        self::delete_where($context, '', []);
    }

    /**
     * Deletes the assignments of the user in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_where($context, 'AND a.userid = :userid', ['userid' => $userid]);
        }
    }

    /**
     * Deletes the assignments of the users in the approved list.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_userids()) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        self::delete_where($userlist->get_context(), "AND a.userid $insql", $inparams);
    }

    /**
     * Deletes the assignments in a module context that match the extra condition.
     *
     * @param \context $context
     * @param string $where extra condition on the assignment a, starting with AND
     * @param array $params
     * @return void
     */
    private static function delete_where(\context $context, string $where, array $params): void {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $params += ['contextlevel' => CONTEXT_MODULE, 'contextid' => $context->id];
        $ids = $DB->get_fieldset_sql('SELECT a.id ' . self::JOIN . " WHERE ctx.id = :contextid $where", $params);
        if ($ids) {
            $DB->delete_records_list(self::TABLE, 'id', $ids);
        }
    }
}
