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
 * The availability of the persons plus the exams planned in a run, with the gap after each of them.
 *
 * A copy (clone) starts with the same exams and can then be changed without touching the original, so a change of a
 * draft plan can be tried out.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exam_calendar {
    /** @var array<int, int[][]> entity id => [start, end] of the exams planned in this run */
    private array $reserved = [];

    /**
     * Constructor.
     *
     * @param availability $availability what was loaded before
     * @param int $gap seconds that must be free after an exam of a person
     */
    public function __construct(
        /** @var availability What was loaded before. */
        private availability $availability,
        /** @var int Seconds that must be free after an exam of a person. */
        private int $gap = 0
    ) {
        $this->gap = max(0, $gap);
    }

    /**
     * Why a person is not free in a slot: the availability, or an exam of this run without the gap after it.
     *
     * @param int $entityid
     * @param int $start
     * @param int $end
     * @return string|null one of the constants of availability, null if free
     */
    public function why(int $entityid, int $start, int $end): ?string {
        $reason = $this->availability->reason($entityid, $start, $end);
        if ($reason !== null) {
            return $reason;
        }
        foreach ($this->reserved[$entityid] ?? [] as [$from, $until]) {
            if ($start < $until + $this->gap && $end + $this->gap > $from) {
                return availability::OCCUPIED;
            }
        }
        return null;
    }

    /**
     * Counts an exam of this run for the person.
     *
     * @param int $entityid
     * @param int $start
     * @param int $end
     * @return void
     */
    public function reserve(int $entityid, int $start, int $end): void {
        $this->availability->reserve($entityid, $start, $end);
        $this->reserved[$entityid][] = [$start, $end];
    }

    /**
     * A copy has its own availability, so reserving in the copy leaves the original as it is.
     *
     * @return void
     */
    public function __clone() {
        $this->availability = clone $this->availability;
    }
}
