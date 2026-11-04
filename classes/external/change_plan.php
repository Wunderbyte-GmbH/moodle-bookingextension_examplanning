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

use bookingextension_examplanning\local\plan_editor;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Changes a participant in a draft plan: moves, swaps or gives another clerk. The change is checked again.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class change_plan extends external_api {
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
            'value' => new external_value(PARAM_INT, 'Start of the slot, user id or entity id'),
        ]);
    }

    /**
     * Changes the participant.
     *
     * @param int $planid
     * @param int $userid
     * @param string $kind
     * @param int $value
     * @return array success
     */
    public static function execute(int $planid, int $userid, string $kind, int $value): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'planid' => $planid,
            'userid' => $userid,
            'kind' => $kind,
            'value' => $value,
        ]);
        get_targets::require_plan($params['planid']);

        switch ($params['kind']) {
            case get_targets::MOVE:
                plan_editor::move($params['planid'], $params['userid'], $params['value']);
                break;
            case get_targets::SWAP:
                plan_editor::swap($params['planid'], $params['userid'], $params['value']);
                break;
            case get_targets::CLERK:
                plan_editor::change_clerk($params['planid'], $params['userid'], $params['value'], (int)$USER->id);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown kind ' . $params['kind']);
        }
        return ['success' => true];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'The change is stored'),
        ]);
    }
}
