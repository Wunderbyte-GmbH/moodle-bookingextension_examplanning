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
 * The trainer assigns a clerk to every participant of a planned option. No dates are booked here.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use bookingextension_examplanning\form\assign_form;
use bookingextension_examplanning\local\assignments;
use bookingextension_examplanning\local\clerk_candidates;
use bookingextension_examplanning\local\examiner;
use bookingextension_examplanning\local\participants;
use bookingextension_examplanning\local\planned_options;
use bookingextension_examplanning\local\planning_access;
use bookingextension_examplanning\local\plan_store;
use bookingextension_examplanning\output\plan_section;
use bookingextension_examplanning\task\compute_plan;
use bookingextension_examplanning\local\plan_confirmer;
use bookingextension_examplanning\local\plan_rollback;
use local_entities\entities;

$cmid = required_param('id', PARAM_INT);
$optionid = required_param('optionid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'booking');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
$settings = \mod_booking\singleton_service::get_instance_of_booking_option_settings($optionid);
if ((int)$settings->cmid !== (int)$cm->id || !planned_options::is_planned($optionid)) {
    throw new moodle_exception('notplanned', 'bookingextension_examplanning');
}
planning_access::require_plan($optionid);

$url = new moodle_url('/mod/booking/bookingextension/examplanning/assign.php', ['id' => $cmid, 'optionid' => $optionid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('assign_title', 'bookingextension_examplanning'));
$PAGE->set_heading(format_string($course->fullname));
$optionurl = new moodle_url('/mod/booking/optionview.php', ['cmid' => $cmid, 'optionid' => $optionid]);

// The trainer is the examiner. Trainers added before the planning was switched on get their person now.
examiner::ensure_persons($optionid);
$problem = examiner::get_problem($optionid);
if ($problem === null) {
    $examinername = entities::get_display_name($DB->get_record('local_entities', ['id' => examiner::get_entityid($optionid)]));
} else {
    $examinername = examiner::describe_problem($problem);
}

$participantnames = array_map('fullname', participants::for_option($optionid));
$candidatenames = [];
foreach (clerk_candidates::for_option($optionid) as $entityid) {
    $candidatenames[$entityid] = entities::get_display_name($DB->get_record('local_entities', ['id' => $entityid]));
}
core_collator::asort($candidatenames);

// The buttons Distribute and Shuffle start a run in the background, unless one is already waiting or being computed.
// Shuffle gets a random seed, which mixes the order of the participants at a tie.
$action = optional_param('action', '', PARAM_ALPHA);
if (in_array($action, ['distribute', 'shuffle'], true) && confirm_sesskey()) {
    $latest = plan_store::latest($optionid);
    if ($latest === null || !in_array($latest->status, [plan_store::QUEUED, plan_store::RUNNING], true)) {
        $seed = $action === 'shuffle' ? random_int(1, 2147483647) : null;
        compute_plan::queue(plan_store::create($optionid, $seed, (int)$USER->id), (int)$USER->id);
    }
    redirect($url);
}

// The button Confirm books the draft plan, all or nothing. If it does not work, the page tells why.
if ($action === 'confirm' && confirm_sesskey()) {
    $latest = plan_store::latest($optionid);
    try {
        $count = plan_confirmer::confirm((int)($latest->id ?? 0), (int)$USER->id);
        $message = get_string('plan_confirmed', 'bookingextension_examplanning', $count);
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// The button Take back cancels all bookings of the booked plan, only before its first exam.
if ($action === 'rollback' && confirm_sesskey()) {
    $latest = plan_store::latest($optionid);
    try {
        $count = plan_rollback::rollback((int)($latest->id ?? 0));
        $message = get_string('rollback_done', 'bookingextension_examplanning', $count);
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$form = new assign_form($url, [
    'id' => $cmid,
    'optionid' => $optionid,
    'examiner' => $examinername,
    'participants' => $participantnames,
    'candidates' => $candidatenames,
]);

if ($form->is_cancelled()) {
    redirect($optionurl);
}
$clerks = $form->get_clerks();
if ($clerks !== null) {
    assignments::save($optionid, $clerks, (int)$USER->id);
    redirect($url, get_string('assign_saved', 'bookingextension_examplanning'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$current = [];
foreach (assignments::get($optionid) as $userid => $clerkentityid) {
    $current[assign_form::field((int)$userid)] = $clerkentityid;
}
$form->set_data($current);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assign_title', 'bookingextension_examplanning'));
echo html_writer::tag('p', html_writer::link($optionurl, format_string($settings->get_title_with_prefix())));
echo html_writer::tag('p', get_string('assign_intro', 'bookingextension_examplanning'), ['class' => 'text-muted']);
$form->display();
echo $OUTPUT->render_from_template(
    'bookingextension_examplanning/plan_section',
    (new plan_section($optionid, $url))->export_for_template($OUTPUT)
);
$PAGE->requires->js_call_amd('bookingextension_examplanning/plan_section', 'init');
echo $OUTPUT->footer();
