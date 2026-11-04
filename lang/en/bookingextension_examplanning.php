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

$string['assign_intro'] = 'Assign a clerk to every participant and save. Then Distribute plans the exams as a draft below; nothing is booked yet.';
$string['assign_link'] = 'Exam planning';
$string['assign_nocandidates'] = 'There are no clerks yet. Choose pools or single persons of clerks in the settings of the option.';
$string['assign_noclerk'] = 'No clerk yet';
$string['assign_noparticipants'] = 'The course has no participants yet.';
$string['assign_participants'] = 'Participants and their clerks';
$string['assign_saved'] = 'The clerks were saved.';
$string['assign_title'] = 'Exam planning';
$string['bookingrestricted'] = 'The trainer plans the exam dates. You will see your date as soon as it is booked.';
$string['change_actions'] = 'Change';
$string['change_clerk'] = 'Change clerk…';
$string['change_current'] = '(now)';
$string['change_day'] = 'Day';
$string['change_invalid'] = 'The change is not possible: {$a}';
$string['change_move'] = 'Move to…';
$string['change_noclerk'] = 'This person can not be a clerk of the option.';
$string['change_nofree'] = 'no free slot';
$string['change_noslot'] = 'The slot does not exist.';
$string['change_notargets'] = 'There is nothing to choose.';
$string['change_notready'] = 'The draft plan can not be changed now.';
$string['change_save'] = 'Save';
$string['change_saved'] = 'The draft plan is changed.';
$string['change_swap'] = 'Swap with…';
$string['clerkpersons'] = 'Single persons as clerks';
$string['clerkpersons_help'] = 'Persons of the Entity Manager that can be assigned as clerk, in addition to the members of the pools. Only visible to users who may manage the unavailability of all entities.';
$string['clerkpersons_none'] = 'No single persons';
$string['clerkpools'] = 'Pools of clerks';
$string['clerkpools_help'] = 'The members of these pools of the Entity Manager can be assigned as clerk. The trainer is never the own clerk.';
$string['clerkpools_none'] = 'No pools';
$string['conditionselectclerkinbo'] = 'Clerk of the exam';
$string['conditionselectclerkinbo_desc'] = 'The clerks of the booked exams of the option. If the rule reacts on an event about a participant, only the clerk of that participant.';
$string['confirm_bookingfailed'] = 'The booking of {$a} failed. Nothing is booked.';
$string['confirm_busy'] = 'The draft plan is being booked right now.';
$string['confirm_empty'] = 'The draft plan has no participants.';
$string['confirm_problems'] = 'Nothing is booked, because something has changed: {$a}';
$string['confirm_unplanned'] = 'Nothing is booked, because these participants could not be planned: {$a}. Clarify it with the clerks and distribute again.';
$string['examiner'] = 'Examiner (trainer)';
$string['examiner_many'] = 'This option has more than one trainer. The trainer is the examiner, so the exams can only be planned with exactly one trainer.';
$string['examiner_none'] = 'This option has no trainer yet. The trainer is the examiner, so the exams can only be planned once the option has exactly one trainer.';
$string['examplanning'] = 'Exam planning by the trainer';
$string['examplanningheader'] = 'Exam planning by the trainer';
$string['messageprovider:planready'] = 'The draft plan of the exams is computed';
$string['myexam'] = 'Your exam';
$string['myexam_text'] = '{$a->time} · Examiner: {$a->examiner} · Clerk: {$a->clerk}';
$string['notplanned'] = 'The exams of this option are not planned by the trainer.';
$string['occupation'] = 'Participant: {$a->participant} · Exam: {$a->option} · Examiner: {$a->examiner} · Clerk: {$a->clerk}';
$string['plan_allplanned'] = 'All participants are planned.';
$string['plan_booked'] = 'Booked plan of {$a}';
$string['plan_clerk'] = 'Clerk';
$string['plan_computed'] = 'Draft plan of {$a}';
$string['plan_confirm'] = 'Confirm and book';
$string['plan_confirm_help'] = 'Checks everything again and books every participant into the slot. Only if all participants are planned and nothing has changed.';
$string['plan_confirmed'] = '{$a} exams are booked.';
$string['plan_confirmq'] = 'Book all exams of the draft plan now?';
$string['plan_distribute'] = 'Distribute';
$string['plan_distribute_help'] = 'Plans every participant with a clerk in the earliest slot in which the examiner and the clerk are free. Nothing is booked yet.';
$string['plan_failed'] = 'The distribution could not be computed: {$a}';
$string['plan_heading'] = 'Distribution';
$string['plan_none'] = 'There is no draft plan yet.';
$string['plan_participant'] = 'Participant';
$string['plan_queued'] = 'The distribution is computed in the background. You get a message when it is done.';
$string['plan_running'] = 'The distribution is being computed…';
$string['plan_shuffle'] = 'Shuffle';
$string['plan_shuffle_help'] = 'Distributes again in another order. Who gets which slot may change.';
$string['plan_time'] = 'Time';
$string['plan_unplanned'] = 'Could not be planned';
$string['plannedbytrainer'] = 'Planned by the trainer';
$string['plannedbytrainer_help'] = 'The trainer assigns a clerk to every participant, plans the slots and books them. Participants can not book a slot themselves. Only for slot booking.';
$string['plannedbytrainer_label'] = 'The trainer plans and books the exams of all participants';
$string['planready_body'] = 'The draft plan for {$a->option} is computed: {$a->planned} planned, {$a->unplanned} could not be planned. {$a->url}';
$string['planready_failed'] = 'The draft plan for {$a->option} could not be computed: {$a->error}';
$string['planready_subject'] = 'Draft plan for {$a} is ready';
$string['pluginname'] = 'Booking extension: Exam planning by the trainer';
$string['privacy:metadata:assign'] = 'Which clerk is assigned to which participant of a planned option.';
$string['privacy:metadata:assign:clerkentityid'] = 'The person in Entities who is the clerk of the participant.';
$string['privacy:metadata:assign:optionid'] = 'The booking option whose exams are planned.';
$string['privacy:metadata:assign:timemodified'] = 'When the assignment was changed last.';
$string['privacy:metadata:assign:userid'] = 'The participant.';
$string['privacy:metadata:assign:usermodified'] = 'Who changed the assignment last.';
$string['privacy:metadata:plan'] = 'A run of the distribution of the exams of an option.';
$string['privacy:metadata:plan:optionid'] = 'The booking option.';
$string['privacy:metadata:plan:timecreated'] = 'When the run was started.';
$string['privacy:metadata:plan:usermodified'] = 'Who started the run.';
$string['privacy:metadata:planitem'] = 'A participant in a draft plan.';
$string['privacy:metadata:planitem:clerkentityid'] = 'The clerk of the participant in the draft plan.';
$string['privacy:metadata:planitem:slotstart'] = 'When the exam of the participant is planned.';
$string['privacy:metadata:planitem:userid'] = 'The participant.';
$string['privacy:metadata:staff'] = 'Who is examiner and who is clerk at a booked exam of a participant.';
$string['privacy:metadata:staff:entityid'] = 'The person in Entities who is examiner or clerk.';
$string['privacy:metadata:staff:optionid'] = 'The booking option of the exam.';
$string['privacy:metadata:staff:role'] = 'Examiner or clerk.';
$string['privacy:metadata:staff:slotstart'] = 'When the exam starts.';
$string['privacy:metadata:staff:userid'] = 'The participant who is examined.';
$string['privacy:metadata:staff:usermodified'] = 'Who booked the exam.';
$string['problem_absent'] = '{$a->role} {$a->name} is absent.';
$string['problem_dailylimit'] = '{$a->role} {$a->name} has reached the daily limit.';
$string['problem_occupied'] = '{$a->role} {$a->name} is occupied.';
$string['problem_outsideperiod'] = 'The slot is outside the planning period.';
$string['problem_swap'] = 'For {$a->participant}: {$a->problem}';
$string['problem_teaching'] = '{$a->role} {$a->name} teaches at another option.';
$string['reason_absent'] = '{$a->role} {$a->name} is absent in {$a->count} slots.';
$string['reason_dailylimit'] = '{$a->role} {$a->name} has reached the daily limit in {$a->count} slots.';
$string['reason_noclerk'] = 'No clerk is assigned.';
$string['reason_noexaminer'] = 'The option has not exactly one trainer.';
$string['reason_noslots'] = 'The option has no slots in the planning period.';
$string['reason_occupied'] = '{$a->role} {$a->name} is occupied in {$a->count} slots.';
$string['reason_outsideperiod'] = '{$a->count} slots are outside the planning period.';
$string['reason_teaching'] = '{$a->role} {$a->name} teaches in {$a->count} slots.';
$string['role_clerk'] = 'Clerk';
$string['role_examiner'] = 'Examiner';
$string['rollback'] = 'Take back the plan';
$string['rollback_done'] = '{$a} bookings are cancelled. The plan is a draft again.';
$string['rollback_failed'] = 'The booking of {$a} could not be cancelled. Nothing is cancelled.';
$string['rollback_help'] = 'Cancels all bookings of this plan. The plan is a draft again: change it, confirm it again or distribute again. Only before the first exam.';
$string['rollback_notbooked'] = 'The plan is not booked.';
$string['rollback_started'] = 'The plan can not be taken back, the first exam has begun.';
$string['rollbackq'] = 'Cancel all bookings of this plan?';
$string['selectclerkinbo'] = 'Clerk of the exam';
$string['settings_enabled'] = 'Activate exam planning by the trainer';
$string['settings_enabled_desc'] = 'Slot booking options get the setting "Planned by the trainer".';
$string['settings_heading'] = 'Exam planning by the trainer';
$string['settings_heading_desc'] = 'The trainer of a slot booking option is the examiner. The trainer assigns a clerk to every participant, lets the slots be planned, adjusts the plan and books it.';
