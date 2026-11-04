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

namespace bookingextension_examplanning\local\distribution;

use bookingextension_examplanning\local\availability;
use bookingextension_examplanning\local\exam_calendar;

/**
 * Plans the exams as early as possible. The participants with the fewest slots in which both persons are free come
 * first, so that they still get one.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compact_strategy implements distribution_strategy {
    /**
     * Finds a slot for each participant in which the examiner and the clerk of the participant are free.
     *
     * @param int|null $examinerentityid
     * @param array $clerks user id => entity id of the clerk, 0 for none
     * @param int[][] $slots
     * @param int $gap
     * @param availability $availability
     * @param int|null $seed
     * @return distribution_result
     */
    public function distribute(
        ?int $examinerentityid,
        array $clerks,
        array $slots,
        int $gap,
        availability $availability,
        ?int $seed = null
    ): distribution_result {
        $result = new distribution_result();
        $calendar = new exam_calendar($availability, $gap);
        usort($slots, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $candidates = [];
        foreach ($clerks as $userid => $clerkentityid) {
            if (!$slots) {
                $result->fail((int)$userid, [self::reason(distribution_result::NO_SLOTS)]);
            } else if ($examinerentityid === null) {
                $result->fail((int)$userid, [self::reason(distribution_result::NO_EXAMINER)]);
            } else if ((int)$clerkentityid <= 0) {
                $result->fail((int)$userid, [self::reason(distribution_result::NO_CLERK)]);
            } else {
                $candidates[(int)$userid] = (int)$clerkentityid;
            }
        }

        foreach (self::order($candidates, (int)$examinerentityid, $slots, $calendar, $seed) as $userid) {
            $clerk = $candidates[$userid];
            $slot = self::first_free_slot((int)$examinerentityid, $clerk, $slots, $calendar);
            if ($slot === null) {
                $result->fail($userid, self::reasons((int)$examinerentityid, $clerk, $slots, $calendar));
                continue;
            }
            [$start, $end] = $slot;
            $calendar->reserve((int)$examinerentityid, $start, $end);
            $calendar->reserve($clerk, $start, $end);
            $result->plan($userid, $start, $end);
        }
        return $result;
    }

    /**
     * The participants in the order they are planned: fewest free slots first, at a tie by user id or shuffled.
     *
     * @param array $candidates user id => entity id of the clerk
     * @param int $examiner
     * @param int[][] $slots
     * @param exam_calendar $calendar
     * @param int|null $seed
     * @return int[] user ids
     */
    private static function order(array $candidates, int $examiner, array $slots, exam_calendar $calendar, ?int $seed): array {
        $keys = [];
        foreach ($candidates as $userid => $clerk) {
            $free = 0;
            foreach ($slots as [$start, $end]) {
                if (self::both_free($examiner, $clerk, $start, $end, $calendar)) {
                    $free++;
                }
            }
            $keys[$userid] = [$free, $seed === null ? $userid : crc32($seed . '-' . $userid), $userid];
        }
        uasort($keys, static fn(array $a, array $b): int => $a <=> $b);
        return array_keys($keys);
    }

    /**
     * The earliest slot in which both persons are free.
     *
     * @param int $examiner
     * @param int $clerk
     * @param int[][] $slots ordered by start
     * @param exam_calendar $calendar
     * @return int[]|null [start, end]
     */
    private static function first_free_slot(int $examiner, int $clerk, array $slots, exam_calendar $calendar): ?array {
        foreach ($slots as $slot) {
            if (self::both_free($examiner, $clerk, $slot[0], $slot[1], $calendar)) {
                return $slot;
            }
        }
        return null;
    }

    /**
     * Are the examiner and the clerk both free in the slot?
     *
     * @param int $examiner
     * @param int $clerk
     * @param int $start
     * @param int $end
     * @param exam_calendar $calendar
     * @return bool
     */
    private static function both_free(int $examiner, int $clerk, int $start, int $end, exam_calendar $calendar): bool {
        return $calendar->why($examiner, $start, $end) === null && $calendar->why($clerk, $start, $end) === null;
    }

    /**
     * Why no slot was found: for each person and reason, in how many slots.
     *
     * @param int $examiner
     * @param int $clerk
     * @param int[][] $slots
     * @param exam_calendar $calendar
     * @return array[] most often first
     */
    private static function reasons(int $examiner, int $clerk, array $slots, exam_calendar $calendar): array {
        $counts = [];
        foreach ($slots as [$start, $end]) {
            foreach ([distribution_result::ROLE_EXAMINER => $examiner, distribution_result::ROLE_CLERK => $clerk] as $role => $id) {
                $reason = $calendar->why($id, $start, $end);
                if ($reason !== null) {
                    $counts["$role/$reason"] = ($counts["$role/$reason"] ?? 0) + 1;
                }
            }
        }
        arsort($counts);
        $reasons = [];
        foreach ($counts as $key => $count) {
            [$role, $reason] = explode('/', $key);
            $reasons[] = self::reason($reason, $role, $count);
        }
        return $reasons;
    }

    /**
     * One reason.
     *
     * @param string $reason
     * @param string $role
     * @param int $count
     * @return array
     */
    private static function reason(string $reason, string $role = '', int $count = 0): array {
        return ['reason' => $reason, 'role' => $role, 'count' => $count];
    }
}
