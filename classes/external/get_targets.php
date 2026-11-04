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

namespace bookingextension_examplanning\external;

use bookingextension_examplanning\local\clerk_candidates;
use bookingextension_examplanning\local\option_slots;
use bookingextension_examplanning\local\plan_editor;
use bookingextension_examplanning\local\plan_store;
use bookingextension_examplanning\local\planning_access;
use bookingextension_examplanning\local\problem_text;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_entities\entities;

/**
 * The possible targets of a change of a participant in a draft plan, each with the reason why it does not work.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_targets extends external_api {
    /** @var string Move to another slot: the value is the start of the slot. */
    public const MOVE = 'move';
    /** @var string Swap with another participant: the value is the user id. */
    public const SWAP = 'swap';
    /** @var string Change the clerk: the value is the entity id. */
    public const CLERK = 'clerk';

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'planid' => new external_value(PARAM_INT, 'Id of the run'),
            'userid' => new external_value(PARAM_INT, 'The participant'),
            'kind' => new external_value(PARAM_ALPHA, 'move, swap or clerk'),
        ]);
    }

    /**
     * The targets.
     *
     * @param int $planid
     * @param int $userid
     * @param string $kind
     * @return array targets
     */
    public static function execute(int $planid, int $userid, string $kind): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'planid' => $planid,
            'userid' => $userid,
            'kind' => $kind,
        ]);
        $plan = self::require_plan($params['planid']);
        $optionid = (int)$plan->optionid;
        $items = plan_store::items((int)$plan->id);
        $timeformat = get_string('strftimetime', 'langconfig');
        $dayformat = get_string('strftimedaydate', 'langconfig');
        $reason = static fn(?array $problem): string => $problem === null ? '' : problem_text::describe($problem);

        $targets = [];
        switch ($params['kind']) {
            case self::MOVE:
                $validator = plan_editor::validator($plan);
                foreach ($validator->move_targets($params['userid'], option_slots::slots($optionid)) as $target) {
                    $targets[] = [
                        'value' => $target['start'],
                        'label' => userdate($target['start'], $timeformat) . '–' . userdate($target['end'], $timeformat),
                        'group' => userdate($target['start'], $dayformat),
                        'current' => $target['current'],
                        'reason' => $reason($target['problem']),
                    ];
                }
                break;
            case self::SWAP:
                $validator = plan_editor::validator($plan);
                $users = $DB->get_records_list('user', 'id', array_keys($items));
                foreach ($validator->swap_targets($params['userid']) as $otheruserid => $problem) {
                    $start = (int)$items[$otheruserid]->slotstart;
                    $targets[] = [
                        'value' => $otheruserid,
                        'label' => fullname($users[$otheruserid]) . ' (' . userdate($start, $dayformat) . ', '
                            . userdate($start, $timeformat) . ')',
                        'group' => '',
                        'current' => false,
                        'reason' => $reason($problem),
                    ];
                }
                break;
            case self::CLERK:
                $candidates = clerk_candidates::for_option($optionid);
                $validator = plan_editor::validator($plan, $candidates);
                $current = (int)($items[$params['userid']]->clerkentityid ?? 0);
                foreach ($validator->clerk_targets($params['userid'], $candidates) as $entityid => $problem) {
                    $record = $DB->get_record('local_entities', ['id' => $entityid]);
                    $targets[] = [
                        'value' => $entityid,
                        'label' => $record ? entities::get_display_name($record) : '',
                        'group' => '',
                        'current' => $entityid === $current,
                        'reason' => $reason($problem),
                    ];
                }
                break;
            default:
                throw new \invalid_parameter_exception('Unknown kind ' . $params['kind']);
        }
        return ['targets' => $targets];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'targets' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_INT, 'Start of the slot, user id or entity id'),
                'label' => new external_value(PARAM_TEXT, 'What is shown'),
                'group' => new external_value(PARAM_TEXT, 'The day of a slot, empty otherwise'),
                'current' => new external_value(PARAM_BOOL, 'What the participant has now'),
                'reason' => new external_value(PARAM_TEXT, 'Why it does not work, empty if it does'),
            ])),
        ]);
    }

    /**
     * A ready run that the current user may plan.
     *
     * @param int $planid
     * @return \stdClass
     */
    public static function require_plan(int $planid): \stdClass {
        $plan = plan_store::get($planid);
        if ($plan === null) {
            throw new \invalid_parameter_exception('Unknown run ' . $planid);
        }
        $settings = \mod_booking\singleton_service::get_instance_of_booking_option_settings((int)$plan->optionid);
        self::validate_context(\context_module::instance((int)$settings->cmid));
        planning_access::require_plan((int)$plan->optionid);
        return plan_editor::ready_plan($planid);
    }
}
