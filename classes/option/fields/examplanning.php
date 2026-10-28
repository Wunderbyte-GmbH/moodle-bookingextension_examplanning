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

namespace bookingextension_examplanning\option\fields;

use bookingextension_examplanning\examplanning as extension;
use bookingextension_examplanning\local\examiner;
use bookingextension_examplanning\local\planned_options;
use mod_booking\booking_option_settings;
use mod_booking\option\field_base;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/bookingextension/examplanning/lib.php');

/**
 * Field of the option form: the trainer plans the exams of this slot booking option.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class examplanning extends field_base {
    /** @var string Name of the form element. */
    public const ELEMENT = 'examplanning_enabled';

    /** @var string */
    public static $subplugin = 'bookingextension_examplanning';

    /** @var int */
    public static $id = MOD_BOOKING_OPTION_FIELD_EXAMPLANNING;

    /** @var int The option needs its id, so the value is saved after the option. */
    public static $save = MOD_BOOKING_EXECUTION_POSTSAVE;

    /** @var string */
    public static $header = MOD_BOOKING_HEADER_EXAMPLANNING;

    /** @var string */
    public static $headericon = '<i class="fa fa-calendar-check-o" aria-hidden="true"></i>&nbsp;';

    /** @var array */
    public static $fieldcategories = [MOD_BOOKING_OPTION_FIELD_STANDARD];

    /** @var array */
    public static $alternativeimportidentifiers = [];

    /** @var array */
    public static $incompatiblefields = [];

    /**
     * Nothing goes into the option record, the value has a table of its own.
     *
     * @param stdClass $formdata
     * @param stdClass $newoption
     * @param int $updateparam
     * @param mixed $returnvalue
     * @return array
     */
    public static function prepare_save_field(
        stdClass &$formdata,
        stdClass &$newoption,
        int $updateparam,
        $returnvalue = null
    ): array {
        return [];
    }

    /**
     * Adds the checkbox, shown only for slot booking options.
     *
     * @param MoodleQuickForm $mform
     * @param array $formdata
     * @param array $optionformconfig
     * @param array $fieldstoinstanciate
     * @param bool $applyheader
     * @return void
     */
    public static function instance_form_definition(
        MoodleQuickForm &$mform,
        array &$formdata,
        array $optionformconfig,
        $fieldstoinstanciate = [],
        $applyheader = true
    ): void {
        if (!extension::is_globally_enabled()) {
            return;
        }

        if ($applyheader && !$mform->elementExists(self::$header)) {
            $mform->addElement('header', self::$header, self::$headericon . get_string(self::$header, self::$subplugin));
            $mform->hideIf(self::$header, 'optiontype', 'neq', MOD_BOOKING_OPTIONTYPE_SLOTBOOKING);
        }

        $mform->addElement(
            'advcheckbox',
            self::ELEMENT,
            get_string('plannedbytrainer', self::$subplugin),
            get_string('plannedbytrainer_label', self::$subplugin)
        );
        $mform->addHelpButton(self::ELEMENT, 'plannedbytrainer', self::$subplugin);
        $mform->hideIf(self::ELEMENT, 'optiontype', 'neq', MOD_BOOKING_OPTIONTYPE_SLOTBOOKING);
    }

    /**
     * Fills the checkbox with the stored value.
     *
     * @param stdClass $data
     * @param booking_option_settings $settings
     * @return void
     */
    public static function set_data(stdClass &$data, booking_option_settings $settings): void {
        if (!extension::is_globally_enabled() || isset($data->{self::ELEMENT})) {
            return;
        }
        $data->{self::ELEMENT} = (int)planned_options::is_planned((int)($settings->id ?? 0));
    }

    /**
     * Stores the value after the option was saved. Only slot booking options can be planned.
     *
     * @param stdClass $formdata
     * @param stdClass $option
     * @return array
     */
    public static function save_data(stdClass &$formdata, stdClass &$option): array {
        if (!extension::is_globally_enabled() || !property_exists($formdata, self::ELEMENT)) {
            return [];
        }
        $optionid = (int)($option->id ?? 0);
        if ($optionid <= 0) {
            return [];
        }
        $isslotbooking = (int)($formdata->optiontype ?? $option->type ?? 0) === MOD_BOOKING_OPTIONTYPE_SLOTBOOKING;
        $planned = $isslotbooking && !empty($formdata->{self::ELEMENT});
        planned_options::set_planned($optionid, $planned);
        if (!$planned) {
            return [];
        }

        // The trainers are the examiners. Trainers that were added before the planning was switched on get their
        // person now. Without exactly one trainer the planning can not start, which the trainer is told here.
        examiner::ensure_persons($optionid);
        $problem = examiner::get_problem($optionid);
        if ($problem !== null) {
            \core\notification::warning(examiner::describe_problem($problem));
        }
        return [];
    }
}
