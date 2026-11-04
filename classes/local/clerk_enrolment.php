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

use mod_booking\singleton_service;

/**
 * Gives the clerk of a booked exam access to the course, so the clerk can attend the online exam, and takes it back.
 *
 * The clerk is enrolled as non-editing teacher in the course of the option, or in the course of the booking instance
 * if the option has none. Only whom the extension enrolled is unenrolled again, and only when no other booked exam
 * in the same course needs it.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class clerk_enrolment {
    /**
     * The course of the exams of an option.
     *
     * @param int $optionid
     * @return int course id, 0 for none
     */
    public static function course_of_option(int $optionid): int {
        global $DB;
        $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
        if (!empty($settings->courseid)) {
            return (int)$settings->courseid;
        }
        return (int)$DB->get_field('booking', 'course', ['id' => (int)$settings->bookingid]);
    }

    /**
     * Enrols the clerk of a booked exam, if the clerk is not in the course yet.
     *
     * @param int $staffid the row of the clerk in the table of the booked exams
     * @return void
     */
    public static function enrol(int $staffid): void {
        global $DB;

        $row = $DB->get_record(occupations::TABLE, ['id' => $staffid]);
        if (!$row || $row->role !== 'clerk') {
            return;
        }
        $userid = (int)$DB->get_field('local_entities', 'userid', ['id' => (int)$row->entityid]);
        $courseid = self::course_of_option((int)$row->optionid);
        [$plugin, $instance] = self::manual_enrolment($courseid);
        if ($userid <= 0 || $instance === null) {
            return;
        }
        if (is_enrolled(\context_course::instance($courseid), $userid)) {
            // Already in the course, maybe by another exam of the extension: keep it as it is.
            return;
        }
        $roles = get_archetype_roles('teacher');
        $plugin->enrol_user($instance, $userid, $roles ? (int)reset($roles)->id : null);
        $DB->set_field(occupations::TABLE, 'enrolled', 1, ['id' => $staffid]);
    }

    /**
     * After a cancellation: unenrols the clerks whom the extension enrolled for the cancelled exams of the participant,
     * unless another booked exam in the same course needs them; then that exam keeps the mark.
     *
     * @param int $optionid
     * @param int $participantid
     * @return void
     */
    public static function release(int $optionid, int $participantid): void {
        global $DB;

        $rows = $DB->get_records_select(
            occupations::TABLE,
            "optionid = :optionid AND userid = :userid AND role = 'clerk' AND enrolled = 1 AND timecancelled IS NOT NULL",
            ['optionid' => $optionid, 'userid' => $participantid]
        );
        foreach ($rows as $row) {
            $DB->set_field(occupations::TABLE, 'enrolled', 0, ['id' => $row->id]);
            $courseid = self::course_of_option($optionid);

            // Another booked exam of the same clerk in the same course keeps the access and takes over the mark.
            $others = $DB->get_records_select(
                occupations::TABLE,
                "entityid = :entityid AND role = 'clerk' AND timecancelled IS NULL",
                ['entityid' => (int)$row->entityid]
            );
            foreach ($others as $other) {
                if (self::course_of_option((int)$other->optionid) === $courseid) {
                    $DB->set_field(occupations::TABLE, 'enrolled', 1, ['id' => $other->id]);
                    continue 2;
                }
            }

            [$plugin, $instance] = self::manual_enrolment($courseid);
            $userid = (int)$DB->get_field('local_entities', 'userid', ['id' => (int)$row->entityid]);
            if ($instance !== null && $userid > 0) {
                $plugin->unenrol_user($instance, $userid);
            }
        }
    }

    /**
     * The manual enrolment of a course.
     *
     * @param int $courseid
     * @return array [plugin, instance], instance null if there is none
     */
    private static function manual_enrolment(int $courseid): array {
        global $DB;
        $plugin = enrol_get_plugin('manual');
        if ($courseid <= 0 || !$plugin || !enrol_is_enabled('manual')) {
            return [$plugin, null];
        }
        $instances = $DB->get_records(
            'enrol',
            ['enrol' => 'manual', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED],
            'sortorder, id'
        );
        return [$plugin, $instances ? reset($instances) : null];
    }
}
