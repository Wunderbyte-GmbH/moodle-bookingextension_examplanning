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

namespace bookingextension_examplanning\rules\conditions;

use bookingextension_examplanning\local\occupations;
use mod_booking\booking_rules\booking_rule_condition;
use MoodleQuickForm;
use stdClass;

/**
 * Rule condition: the clerks of the booked exams of the option. If the rule reacts on an event about a participant,
 * only the clerk of that participant.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_clerk_in_bo implements booking_rule_condition {
    /** @var string Name of the condition. */
    public $conditionname = 'select_clerk_in_bo';

    /** @var string The json of the rule. */
    public $rulejson = '';

    /** @var int The participant the event is about, 0 for all participants. */
    public $relateduserid = 0;

    /**
     * The condition works with every rule.
     *
     * @param string $bookingruletype
     * @return bool
     */
    public function can_be_combined_with_bookingruletype(string $bookingruletype): bool {
        return true;
    }

    /**
     * Loads the data from the database.
     *
     * @param stdClass $record
     * @return void
     */
    public function set_conditiondata(stdClass $record) {
        $this->set_conditiondata_from_json($record->rulejson);
    }

    /**
     * Loads the data from the json; an event about a participant narrows to the clerk of the participant.
     *
     * @param string $json
     * @return void
     */
    public function set_conditiondata_from_json(string $json) {
        $this->rulejson = $json;
        $ruleobj = json_decode($json);
        $this->relateduserid = (int)($ruleobj->datafromevent->relateduserid ?? 0);
    }

    /**
     * Explains the condition in the form.
     *
     * @param MoodleQuickForm $mform
     * @param ?array $ajaxformdata
     * @return void
     */
    public function add_condition_to_mform(MoodleQuickForm &$mform, ?array &$ajaxformdata = null) {
        $mform->addElement(
            'static',
            'condition_select_clerk_in_bo',
            '',
            get_string('conditionselectclerkinbo_desc', 'bookingextension_examplanning')
        );
    }

    /**
     * The name of the condition.
     *
     * @param bool $localized
     * @return string
     */
    public function get_name_of_condition($localized = true) {
        return $localized ? get_string('conditionselectclerkinbo', 'bookingextension_examplanning') : $this->conditionname;
    }

    /**
     * Stores the name of the condition in the json of the rule.
     *
     * @param stdClass $data
     * @return void
     */
    public function save_condition(stdClass &$data): void {
        $jsonobject = isset($data->rulejson) ? json_decode($data->rulejson) : new stdClass();
        $jsonobject->conditionname = $this->conditionname;
        // The list of the rules looks up the name of the condition in this component.
        $jsonobject->conditioncomponent = 'bookingextension_examplanning';
        $data->rulejson = json_encode($jsonobject);
    }

    /**
     * Sets the defaults of the form.
     *
     * @param stdClass $data
     * @param stdClass $record
     * @return void
     */
    public function set_defaults(stdClass &$data, stdClass $record) {
        $data->bookingruleconditiontype = $this->conditionname;
    }

    /**
     * Adds the clerks to the query of the rule: their users are the recipients.
     *
     * @param stdClass $sql
     * @param array $params
     * @return void
     */
    public function execute(stdClass &$sql, array &$params): void {
        global $DB;

        // An event about a participant means the newest exam of the participant, booked or just cancelled.
        // Without an event, the clerks of the exams that are booked now.
        if ($this->relateduserid > 0) {
            $params['examplanningparticipant'] = $this->relateduserid;
            $andparticipant = 'AND eps.userid = :examplanningparticipant AND eps.id = (SELECT MAX(epn.id) FROM {'
                . occupations::TABLE . "} epn WHERE epn.optionid = bo.id AND epn.userid = eps.userid AND epn.role = 'clerk')";
        } else {
            $andparticipant = 'AND eps.timecancelled IS NULL';
        }
        $andrecipient = '';
        if (!empty($params['userid'])) {
            $params['examplanningrecipient'] = $params['userid'];
            $andrecipient = 'AND epe.userid = :examplanningrecipient';
        }

        if (strpos($sql->select, 'optiondate') !== false) {
            $concat = $DB->sql_concat('bo.id', "'-'", 'bod.id', "'-'", 'epe.userid', "'-'", 'eps.id');
        } else {
            $concat = $DB->sql_concat('bo.id', "'-'", 'epe.userid', "'-'", 'eps.id');
        }
        $sql->select = " $concat uniqueid, " . $sql->select;
        $sql->select .= ', epe.userid userid ';
        $sql->from .= ' JOIN {' . occupations::TABLE . "} eps ON eps.optionid = bo.id AND eps.role = 'clerk' $andparticipant"
            . ' JOIN {local_entities} epe ON epe.id = eps.entityid ';
        $sql->where .= " $andrecipient ";
    }
}
