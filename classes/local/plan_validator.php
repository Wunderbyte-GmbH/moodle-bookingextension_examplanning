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

use bookingextension_examplanning\local\distribution\distribution_result;

/**
 * Tells whether a change of a draft plan is possible, and if not, why. Works only on what it is given, without queries.
 *
 * A problem is ['role' => examiner or clerk, 'entityid' => person, 'reason' => code of availability], for a swap
 * also 'userid' of the participant whose new slot does not work.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_validator {
    /**
     * Constructor.
     *
     * @param exam_calendar $base availability without the exams of the draft plan
     * @param array $items user id => ['examiner' => id, 'clerk' => id, 'start' => int|null, 'end' => int|null]
     */
    public function __construct(
        /** @var exam_calendar Availability without the exams of the draft plan. */
        private exam_calendar $base,
        /** @var array The participants of the draft plan. */
        private array $items
    ) {
    }

    /**
     * Can the participant move to the slot?
     *
     * @param int $userid
     * @param int $start
     * @param int $end
     * @return array|null the problem, null if it works
     */
    public function check_move(int $userid, int $start, int $end): ?array {
        $item = $this->planned_item($userid);
        return self::problem($this->calendar_without([$userid]), $item['examiner'], $item['clerk'], $start, $end);
    }

    /**
     * All slots the participant could move to, each with its problem.
     *
     * @param int $userid
     * @param int[][] $slots
     * @return array[] ['start', 'end', 'current' => the slot of the participant now, 'problem' => array|null]
     */
    public function move_targets(int $userid, array $slots): array {
        $item = $this->planned_item($userid);
        $calendar = $this->calendar_without([$userid]);
        $targets = [];
        foreach ($slots as [$start, $end]) {
            $targets[] = [
                'start' => (int)$start,
                'end' => (int)$end,
                'current' => (int)$start === $item['start'],
                'problem' => self::problem($calendar, $item['examiner'], $item['clerk'], (int)$start, (int)$end),
            ];
        }
        return $targets;
    }

    /**
     * Can two participants swap their slots?
     *
     * @param int $userid
     * @param int $otheruserid
     * @return array|null the problem with the user id whose new slot does not work, null if it works
     */
    public function check_swap(int $userid, int $otheruserid): ?array {
        $first = $this->planned_item($userid);
        $second = $this->planned_item($otheruserid);
        $calendar = $this->calendar_without([$userid, $otheruserid]);

        $problem = self::problem($calendar, $first['examiner'], $first['clerk'], $second['start'], $second['end']);
        if ($problem !== null) {
            return $problem + ['userid' => $userid];
        }
        $calendar->reserve($first['examiner'], $second['start'], $second['end']);
        $calendar->reserve($first['clerk'], $second['start'], $second['end']);
        $problem = self::problem($calendar, $second['examiner'], $second['clerk'], $first['start'], $first['end']);
        return $problem === null ? null : $problem + ['userid' => $otheruserid];
    }

    /**
     * All participants the participant could swap with, each with its problem.
     *
     * @param int $userid
     * @return array user id => problem or null
     */
    public function swap_targets(int $userid): array {
        $this->planned_item($userid);
        $targets = [];
        foreach ($this->items as $otheruserid => $item) {
            if ((int)$otheruserid !== $userid && $item['start'] !== null) {
                $targets[(int)$otheruserid] = $this->check_swap($userid, (int)$otheruserid);
            }
        }
        return $targets;
    }

    /**
     * Can the person be the clerk of the participant in the slot the participant has?
     *
     * @param int $userid
     * @param int $clerkentityid
     * @return array|null the problem, null if it works
     */
    public function check_clerk(int $userid, int $clerkentityid): ?array {
        $item = $this->planned_item($userid);
        return self::problem($this->calendar_without([$userid]), $item['examiner'], $clerkentityid, $item['start'], $item['end']);
    }

    /**
     * All persons who could be the clerk of the participant, each with its problem.
     *
     * @param int $userid
     * @param int[] $candidates entity ids
     * @return array entity id => problem or null
     */
    public function clerk_targets(int $userid, array $candidates): array {
        $targets = [];
        foreach ($candidates as $entityid) {
            $targets[(int)$entityid] = $this->check_clerk($userid, (int)$entityid);
        }
        return $targets;
    }

    /**
     * A copy of the availability with all exams of the draft plan but those of the participants given.
     *
     * @param int[] $userids
     * @return exam_calendar
     */
    private function calendar_without(array $userids): exam_calendar {
        $calendar = clone $this->base;
        foreach ($this->items as $userid => $item) {
            if ($item['start'] === null || in_array((int)$userid, $userids, true)) {
                continue;
            }
            $calendar->reserve($item['examiner'], $item['start'], $item['end']);
            $calendar->reserve($item['clerk'], $item['start'], $item['end']);
        }
        return $calendar;
    }

    /**
     * The first person who is not free in the slot.
     *
     * @param exam_calendar $calendar
     * @param int $examiner
     * @param int $clerk
     * @param int $start
     * @param int $end
     * @return array|null
     */
    private static function problem(exam_calendar $calendar, int $examiner, int $clerk, int $start, int $end): ?array {
        $persons = [distribution_result::ROLE_EXAMINER => $examiner, distribution_result::ROLE_CLERK => $clerk];
        foreach ($persons as $role => $entityid) {
            $reason = $calendar->why($entityid, $start, $end);
            if ($reason !== null) {
                return ['role' => $role, 'entityid' => $entityid, 'reason' => $reason];
            }
        }
        return null;
    }

    /**
     * A participant of the draft plan who has a slot.
     *
     * @param int $userid
     * @return array
     * @throws \invalid_parameter_exception if the participant has no slot in the draft plan
     */
    private function planned_item(int $userid): array {
        $item = $this->items[$userid] ?? null;
        if ($item === null || $item['start'] === null) {
            throw new \invalid_parameter_exception("User $userid has no slot in the draft plan.");
        }
        return $item;
    }
}
