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

/**
 * Changes a draft plan: moves a participant, swaps two, changes a clerk. Every change is checked again before it is
 * stored, whatever the page offered.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_editor {
    /**
     * A run that can be changed.
     *
     * @param int $planid
     * @return \stdClass
     * @throws \moodle_exception if the run is not ready
     */
    public static function ready_plan(int $planid): \stdClass {
        $plan = plan_store::get($planid);
        if ($plan === null || $plan->status !== plan_store::READY) {
            throw new \moodle_exception('change_notready', 'bookingextension_examplanning');
        }
        return $plan;
    }

    /**
     * The validator of a run, with the availability of its persons loaded from the database.
     *
     * @param \stdClass $plan
     * @param int[] $extraentityids persons who are not in the run yet, e.g. possible clerks
     * @return plan_validator
     */
    public static function validator(\stdClass $plan, array $extraentityids = []): plan_validator {
        $items = [];
        $entityids = $extraentityids;
        foreach (plan_store::items((int)$plan->id) as $userid => $item) {
            $items[(int)$userid] = [
                'examiner' => (int)$item->examinerentityid,
                'clerk' => (int)$item->clerkentityid,
                'start' => $item->slotstart === null ? null : (int)$item->slotstart,
                'end' => $item->slotend === null ? null : (int)$item->slotend,
            ];
            $entityids[] = (int)$item->examinerentityid;
            $entityids[] = (int)$item->clerkentityid;
        }
        [$from, $until] = option_slots::range();
        $optionid = (int)$plan->optionid;
        $availability = availability_loader::load(array_filter($entityids), $from, max($from, $until), $optionid);
        return new plan_validator(new exam_calendar($availability, option_slots::gap($optionid)), $items);
    }

    /**
     * Moves a participant to another slot of the option.
     *
     * @param int $planid
     * @param int $userid
     * @param int $start start of the slot
     * @return void
     */
    public static function move(int $planid, int $userid, int $start): void {
        $plan = self::ready_plan($planid);
        $slot = null;
        foreach (option_slots::slots((int)$plan->optionid) as $candidate) {
            if ($candidate[0] === $start) {
                $slot = $candidate;
                break;
            }
        }
        if ($slot === null) {
            throw new \moodle_exception('change_noslot', 'bookingextension_examplanning');
        }
        self::require_no_problem(self::validator($plan)->check_move($userid, $slot[0], $slot[1]));
        self::set_slot($planid, $userid, $slot[0], $slot[1]);
    }

    /**
     * Swaps the slots of two participants.
     *
     * @param int $planid
     * @param int $userid
     * @param int $otheruserid
     * @return void
     */
    public static function swap(int $planid, int $userid, int $otheruserid): void {
        global $DB;

        $plan = self::ready_plan($planid);
        self::require_no_problem(self::validator($plan)->check_swap($userid, $otheruserid));
        $items = plan_store::items($planid);
        $transaction = $DB->start_delegated_transaction();
        self::set_slot($planid, $userid, (int)$items[$otheruserid]->slotstart, (int)$items[$otheruserid]->slotend);
        self::set_slot($planid, $otheruserid, (int)$items[$userid]->slotstart, (int)$items[$userid]->slotend);
        $transaction->allow_commit();
    }

    /**
     * Gives a participant another clerk, in the draft plan and in the assignment.
     *
     * @param int $planid
     * @param int $userid
     * @param int $clerkentityid
     * @param int $usermodified
     * @return void
     */
    public static function change_clerk(int $planid, int $userid, int $clerkentityid, int $usermodified): void {
        global $DB;

        $plan = self::ready_plan($planid);
        if (!in_array($clerkentityid, clerk_candidates::for_option((int)$plan->optionid), true)) {
            throw new \moodle_exception('change_noclerk', 'bookingextension_examplanning');
        }
        self::require_no_problem(self::validator($plan, [$clerkentityid])->check_clerk($userid, $clerkentityid));
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field(plan_store::TABLE_ITEM, 'clerkentityid', $clerkentityid, ['planid' => $planid, 'userid' => $userid]);
        assignments::save((int)$plan->optionid, [$userid => $clerkentityid], $usermodified);
        $transaction->allow_commit();
    }

    /**
     * Stops a change that does not work, with the reason.
     *
     * @param array|null $problem
     * @return void
     * @throws \moodle_exception
     */
    private static function require_no_problem(?array $problem): void {
        if ($problem !== null) {
            throw new \moodle_exception('change_invalid', 'bookingextension_examplanning', '', problem_text::describe($problem));
        }
    }

    /**
     * Stores the slot of a participant in the run.
     *
     * @param int $planid
     * @param int $userid
     * @param int $start
     * @param int $end
     * @return void
     */
    private static function set_slot(int $planid, int $userid, int $start, int $end): void {
        global $DB;
        $id = $DB->get_field(plan_store::TABLE_ITEM, 'id', ['planid' => $planid, 'userid' => $userid], MUST_EXIST);
        $DB->update_record(plan_store::TABLE_ITEM, (object)['id' => $id, 'slotstart' => $start, 'slotend' => $end]);
    }
}
