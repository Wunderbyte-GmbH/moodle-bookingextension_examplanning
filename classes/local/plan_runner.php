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

use bookingextension_examplanning\local\distribution\compact_strategy;
use bookingextension_examplanning\local\distribution\distribution_strategy;

/**
 * Computes a run: gathers participants, clerks, slots and availability, lets the strategy distribute and stores it.
 *
 * Participants who already have a booking in the option keep it and are not distributed again.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_runner {
    /**
     * Computes a run and stores what it found.
     *
     * @param int $planid
     * @param distribution_strategy|null $strategy null for the compact one
     * @return void
     * @throws \Throwable after the run was marked as failed
     */
    public static function run(int $planid, ?distribution_strategy $strategy = null): void {
        $plan = plan_store::get($planid);
        if ($plan === null) {
            return;
        }
        plan_store::set_status($planid, plan_store::RUNNING);
        try {
            self::compute($plan, $strategy ?? new compact_strategy());
        } catch (\Throwable $e) {
            plan_store::set_status($planid, plan_store::FAILED, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Does the work of a run.
     *
     * @param \stdClass $plan
     * @param distribution_strategy $strategy
     * @return void
     */
    private static function compute(\stdClass $plan, distribution_strategy $strategy): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/booking/lib.php');

        $optionid = (int)$plan->optionid;

        $examinerid = examiner::get_problem($optionid) === null ? examiner::get_entityid($optionid) : null;

        $assigned = assignments::get($optionid);
        $booked = $DB->get_fieldset_select(
            'booking_answers',
            'userid',
            'optionid = :optionid AND waitinglist = :booked',
            ['optionid' => $optionid, 'booked' => MOD_BOOKING_STATUSPARAM_BOOKED]
        );
        $booked = array_flip(array_map('intval', $booked));
        $clerks = [];
        foreach (array_keys(participants::for_option($optionid)) as $userid) {
            if (!isset($booked[$userid])) {
                $clerks[$userid] = $assigned[$userid] ?? 0;
            }
        }

        [$from, $until] = option_slots::range();
        $slots = option_slots::slots($optionid);

        $entityids = array_filter(array_merge([$examinerid], array_values($clerks)));
        $availability = availability_loader::load($entityids, $from, max($from, $until), $optionid);

        $gap = option_slots::gap($optionid);
        $result = $strategy->distribute($examinerid, $clerks, $slots, $gap, $availability, $plan->seed ?? null);
        plan_store::save_result((int)$plan->id, $examinerid, $clerks, $result);
    }
}
