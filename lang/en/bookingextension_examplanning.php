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
 * Language strings of the exam planning by the trainer.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['assign_intro'] = 'Assign a clerk to every participant. No dates are booked here; the exams are planned in the next step.';
$string['assign_link'] = 'Assign clerks';
$string['assign_nocandidates'] = 'There are no clerks yet. Choose pools or single persons of clerks in the settings of the option.';
$string['assign_noclerk'] = 'No clerk yet';
$string['assign_noparticipants'] = 'The course has no participants yet.';
$string['assign_participants'] = 'Participants and their clerks';
$string['assign_saved'] = 'The clerks were saved.';
$string['assign_title'] = 'Assign clerks';
$string['bookingrestricted'] = 'The trainer plans the exam dates. You will see your date as soon as it is booked.';
$string['clerkpersons'] = 'Single persons as clerks';
$string['clerkpersons_help'] = 'Persons of the Entity Manager that can be assigned as clerk, in addition to the members of the pools. Only visible to users who may manage the unavailability of all entities.';
$string['clerkpersons_none'] = 'No single persons';
$string['clerkpools'] = 'Pools of clerks';
$string['clerkpools_help'] = 'The members of these pools of the Entity Manager can be assigned as clerk. The trainer is never the own clerk.';
$string['clerkpools_none'] = 'No pools';
$string['examiner'] = 'Examiner (trainer)';
$string['examiner_many'] = 'This option has more than one trainer. The trainer is the examiner, so the exams can only be planned with exactly one trainer.';
$string['examiner_none'] = 'This option has no trainer yet. The trainer is the examiner, so the exams can only be planned once the option has exactly one trainer.';
$string['examplanning'] = 'Exam planning by the trainer';
$string['examplanningheader'] = 'Exam planning by the trainer';
$string['notplanned'] = 'The exams of this option are not planned by the trainer.';
$string['plannedbytrainer'] = 'Planned by the trainer';
$string['plannedbytrainer_help'] = 'The trainer assigns a clerk to every participant, plans the slots and books them. Participants can not book a slot themselves. Only for slot booking.';
$string['plannedbytrainer_label'] = 'The trainer plans and books the exams of all participants';
$string['pluginname'] = 'Booking extension: Exam planning by the trainer';
$string['privacy:metadata:assign'] = 'Which clerk is assigned to which participant of a planned option.';
$string['privacy:metadata:assign:clerkentityid'] = 'The person in Entities who is the clerk of the participant.';
$string['privacy:metadata:assign:optionid'] = 'The booking option whose exams are planned.';
$string['privacy:metadata:assign:timemodified'] = 'When the assignment was changed last.';
$string['privacy:metadata:assign:userid'] = 'The participant.';
$string['privacy:metadata:assign:usermodified'] = 'Who changed the assignment last.';
$string['settings_enabled'] = 'Activate exam planning by the trainer';
$string['settings_enabled_desc'] = 'Slot booking options get the setting "Planned by the trainer".';
$string['settings_heading'] = 'Exam planning by the trainer';
$string['settings_heading_desc'] = 'The trainer of a slot booking option is the examiner. The trainer assigns a clerk to every participant, lets the slots be planned, adjusts the plan and books it.';
