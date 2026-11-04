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

use local_entities\planning_period;
use mod_booking\local\slotbooking\buffer_math;
use mod_booking\local\slotbooking\slot_availability;
use mod_booking\singleton_service;

/**
 * The slots in which the exams of an option can be planned: from now within the planning period, with the buffer of
 * the slot configuration as gap after an exam.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class option_slots {
    /** @var int Without a planning period, slots up to this many days ahead are used. */
    private const DAYS_WITHOUT_PERIOD = 365;

    /**
     * The time range in which is planned: from now to the end of the planning period.
     *
     * @return int[] [from, until], until may be before from if the period is over
     */
    public static function range(): array {
        $period = planning_period::get();
        $from = max(time(), $period->starttime ?? 0);
        $until = $period->endtime ?? time() + self::DAYS_WITHOUT_PERIOD * DAYSECS;
        return [$from, $until];
    }

    /**
     * The slots of the option in the range.
     *
     * @param int $optionid
     * @return int[][] [start, end]
     */
    public static function slots(int $optionid): array {
        [$from, $until] = self::range();
        if ($until <= $from) {
            return [];
        }
        return array_map(
            static fn(array $slot): array => [(int)$slot[0], (int)$slot[1]],
            slot_availability::get_slots_for_range($optionid, $from, $until)
        );
    }

    /**
     * Seconds that must be free after an exam: the buffer of the slot configuration of the option.
     *
     * @param int $optionid
     * @return int
     */
    public static function gap(int $optionid): int {
        $config = singleton_service::get_instance_of_booking_option_settings($optionid)->slotconfig ?? null;
        if (empty($config)) {
            return 0;
        }
        $mode = (string)($config->buffer_combination_mode ?? buffer_math::MODE_SUMMED);
        if (!in_array($mode, [buffer_math::MODE_SUMMED, buffer_math::MODE_OVERLAP], true)) {
            $mode = buffer_math::MODE_SUMMED;
        }
        $cooldown = max(0, (int)($config->buffer_cooldown_minutes ?? 0));
        $warmup = max(0, (int)($config->buffer_warmup_minutes ?? 0));
        return buffer_math::create_strategy($mode)->required_gap($cooldown, $warmup) * MINSECS;
    }
}
