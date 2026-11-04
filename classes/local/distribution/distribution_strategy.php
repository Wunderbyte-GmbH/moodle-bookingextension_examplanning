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

/**
 * Distributes the participants of an option over its slots. Works only on what it is given, without queries.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface distribution_strategy {
    /**
     * Finds a slot for each participant in which the examiner and the clerk of the participant are free.
     *
     * Every planned exam is reserved in the availability, so it counts for the next participants.
     *
     * @param int|null $examinerentityid null if the option has not exactly one trainer
     * @param array<int, int> $clerks user id of the participant => entity id of the clerk, 0 for none
     * @param int[][] $slots [start, end] of the slots in the planning period
     * @param int $gap seconds that must be free after an exam of a person, the buffer of the option
     * @param availability $availability
     * @param int|null $seed null for the same result every time, a number to shuffle the order at a tie
     * @return distribution_result
     */
    public function distribute(
        ?int $examinerentityid,
        array $clerks,
        array $slots,
        int $gap,
        availability $availability,
        ?int $seed = null
    ): distribution_result;
}
