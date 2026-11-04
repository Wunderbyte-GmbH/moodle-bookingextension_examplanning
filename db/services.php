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

/**
 * Web services of the exam planning.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'bookingextension_examplanning_get_targets' => [
        'classname' => \bookingextension_examplanning\external\get_targets::class,
        'description' => 'The possible targets of a change in a draft plan, with the reason why each does not work.',
        'type' => 'read',
        'ajax' => true,
    ],
    'bookingextension_examplanning_change_plan' => [
        'classname' => \bookingextension_examplanning\external\change_plan::class,
        'description' => 'Moves, swaps or gives another clerk to a participant in a draft plan.',
        'type' => 'write',
        'ajax' => true,
    ],
];
