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

/**
 * What a distribution found: a slot for each participant that could be planned, the reasons for the others.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class distribution_result {
    /** @var string The participant has no clerk. */
    public const NO_CLERK = 'noclerk';
    /** @var string The option has not exactly one trainer. */
    public const NO_EXAMINER = 'noexaminer';
    /** @var string The option has no slots in the planning period. */
    public const NO_SLOTS = 'noslots';

    /** @var string Role of the examiner in a reason. */
    public const ROLE_EXAMINER = 'examiner';
    /** @var string Role of the clerk in a reason. */
    public const ROLE_CLERK = 'clerk';

    /** @var array<int, int[]> user id => [start, end] */
    private array $planned = [];

    /** @var array<int, array[]> user id => reasons: ['reason' => code, 'role' => role or '', 'count' => slots] */
    private array $unplanned = [];

    /**
     * The participant gets this slot.
     *
     * @param int $userid
     * @param int $start
     * @param int $end
     * @return void
     */
    public function plan(int $userid, int $start, int $end): void {
        $this->planned[$userid] = [$start, $end];
    }

    /**
     * The participant could not be planned.
     *
     * @param int $userid
     * @param array[] $reasons ['reason' => code, 'role' => role or '', 'count' => number of slots], most often first
     * @return void
     */
    public function fail(int $userid, array $reasons): void {
        $this->unplanned[$userid] = $reasons;
    }

    /**
     * The slots of the participants that could be planned, ordered by time.
     *
     * @return array<int, int[]> user id => [start, end]
     */
    public function get_planned(): array {
        $planned = $this->planned;
        uasort($planned, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        return $planned;
    }

    /**
     * Why the other participants could not be planned.
     *
     * @return array<int, array[]> user id => reasons
     */
    public function get_unplanned(): array {
        return $this->unplanned;
    }
}
