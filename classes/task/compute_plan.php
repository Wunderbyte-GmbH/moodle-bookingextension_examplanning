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

namespace bookingextension_examplanning\task;

use bookingextension_examplanning\local\plan_runner;
use bookingextension_examplanning\local\plan_store;
use mod_booking\singleton_service;

/**
 * Computes a run of the distribution in the background and tells who started it.
 *
 * A run that breaks is marked as failed and not tried again, as the same data would break it again.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compute_plan extends \core\task\adhoc_task {
    /**
     * Queues the computation of a run.
     *
     * @param int $planid
     * @param int $userid who started it, gets the message
     * @return void
     */
    public static function queue(int $planid, int $userid): void {
        $task = new self();
        $task->set_custom_data(['planid' => $planid]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Computes the run and sends the message.
     *
     * @return void
     */
    public function execute(): void {
        $planid = (int)($this->get_custom_data()->planid ?? 0);
        try {
            plan_runner::run($planid);
        } catch (\Throwable $e) {
            // The run is marked as failed with the error, the message tells it.
            mtrace('Exam plan ' . $planid . ' failed: ' . $e->getMessage());
        }
        self::notify($planid);
    }

    /**
     * Tells who started the run that it is computed, or why not.
     *
     * @param int $planid
     * @return void
     */
    private static function notify(int $planid): void {
        $plan = plan_store::get($planid);
        if ($plan === null || empty($plan->usermodified)) {
            return;
        }
        $settings = singleton_service::get_instance_of_booking_option_settings((int)$plan->optionid);
        $option = $settings->get_title_with_prefix();
        $url = new \moodle_url('/mod/booking/bookingextension/examplanning/assign.php', [
            'id' => (int)$settings->cmid,
            'optionid' => (int)$plan->optionid,
        ]);

        if ($plan->status === plan_store::READY) {
            $items = plan_store::items($planid);
            $planned = count(array_filter($items, static fn($item) => $item->slotstart !== null));
            $text = get_string('planready_body', 'bookingextension_examplanning', (object)[
                'option' => $option,
                'planned' => $planned,
                'unplanned' => count($items) - $planned,
                'url' => $url->out(false),
            ]);
        } else {
            $text = get_string('planready_failed', 'bookingextension_examplanning', (object)[
                'option' => $option,
                'error' => (string)$plan->error,
            ]);
        }

        $message = new \core\message\message();
        $message->component = 'bookingextension_examplanning';
        $message->name = 'planready';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = (int)$plan->usermodified;
        $message->subject = get_string('planready_subject', 'bookingextension_examplanning', $option);
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $text;
        $message->notification = 1;
        $message->contexturl = $url;
        $message->contexturlname = $option;
        message_send($message);
    }
}
