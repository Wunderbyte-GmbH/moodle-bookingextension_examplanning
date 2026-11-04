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

use local_entities\unavailability\time_range;

/**
 * Tells whether a person is free at a time, and if not, why. Works only on what was loaded before, without queries.
 *
 * The checks in this order: planning period, absence, occupation (also booked exams), teaching dates of the person,
 * daily limit (only exams count). Exams that are planned while this object lives are added with reserve().
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class availability {
    /** @var string The time is outside the planning period. */
    public const OUTSIDE_PERIOD = 'outsideperiod';
    /** @var string The person is absent. */
    public const ABSENT = 'absent';
    /** @var string The person is occupied, for example at another exam. */
    public const OCCUPIED = 'occupied';
    /** @var string The person teaches at another booking option then. */
    public const TEACHING = 'teaching';
    /** @var string The person has as many exams on that day as allowed. */
    public const DAILY_LIMIT = 'dailylimit';

    /** @var array<int, int[][]> entity id => [start, end] of the absences */
    private array $absences;

    /** @var array<int, int[][]> entity id => [start, end] of the occupations */
    private array $occupations;

    /** @var array<int, int[][]> entity id => [start, end] of the teaching dates */
    private array $teaching;

    /** @var array<int, array<string, int>> entity id => day (Y-m-d) => number of exams */
    private array $examsperday = [];

    /** @var array<int, int|null> entity id => highest number of exams per day, null for no limit */
    private array $limits;

    /** @var time_range|null The planning period, null for no limit. */
    private ?time_range $period;

    /** @var \DateTimeZone In which time zone a day starts. */
    private \DateTimeZone $timezone;

    /**
     * Constructor.
     *
     * @param array $absences entity id => [start, end][]
     * @param array $occupations entity id => [start, end][]
     * @param array $teaching entity id => [start, end][]
     * @param array $exams entity id => [start, end][] of the booked exams, for the daily limit
     * @param array $limits entity id => highest number of exams per day, null for no limit
     * @param time_range|null $period
     * @param \DateTimeZone $timezone
     */
    public function __construct(
        array $absences,
        array $occupations,
        array $teaching,
        array $exams,
        array $limits,
        ?time_range $period,
        \DateTimeZone $timezone
    ) {
        $this->absences = $absences;
        $this->occupations = $occupations;
        $this->teaching = $teaching;
        $this->limits = $limits;
        $this->period = $period;
        $this->timezone = $timezone;
        foreach ($exams as $entityid => $ranges) {
            foreach ($ranges as [$start]) {
                $this->count_exam((int)$entityid, (int)$start);
            }
        }
    }

    /**
     * Why the person is not free at the time.
     *
     * @param int $entityid
     * @param int $start
     * @param int $end
     * @return string|null one of the constants, null if the person is free
     */
    public function reason(int $entityid, int $start, int $end): ?string {
        if ($this->period !== null && ($start < $this->period->starttime || $end > $this->period->endtime)) {
            return self::OUTSIDE_PERIOD;
        }
        if (self::overlaps($this->absences[$entityid] ?? [], $start, $end)) {
            return self::ABSENT;
        }
        if (self::overlaps($this->occupations[$entityid] ?? [], $start, $end)) {
            return self::OCCUPIED;
        }
        if (self::overlaps($this->teaching[$entityid] ?? [], $start, $end)) {
            return self::TEACHING;
        }
        $limit = $this->limits[$entityid] ?? null;
        if ($limit !== null && ($this->examsperday[$entityid][$this->day($start)] ?? 0) >= $limit) {
            return self::DAILY_LIMIT;
        }
        return null;
    }

    /**
     * Counts an exam that is planned now: the person is occupied then and it counts for the daily limit.
     *
     * @param int $entityid
     * @param int $start
     * @param int $end
     * @return void
     */
    public function reserve(int $entityid, int $start, int $end): void {
        $this->occupations[$entityid][] = [$start, $end];
        $this->count_exam($entityid, $start);
    }

    /**
     * Counts an exam for the daily limit.
     *
     * @param int $entityid
     * @param int $start
     * @return void
     */
    private function count_exam(int $entityid, int $start): void {
        $day = $this->day($start);
        $this->examsperday[$entityid][$day] = ($this->examsperday[$entityid][$day] ?? 0) + 1;
    }

    /**
     * The day of a time.
     *
     * @param int $time
     * @return string Y-m-d
     */
    private function day(int $time): string {
        return (new \DateTimeImmutable('@' . $time))->setTimezone($this->timezone)->format('Y-m-d');
    }

    /**
     * Does one of the ranges overlap the time? Ranges that only touch do not.
     *
     * @param int[][] $ranges
     * @param int $start
     * @param int $end
     * @return bool
     */
    private static function overlaps(array $ranges, int $start, int $end): bool {
        foreach ($ranges as [$from, $until]) {
            if ($start < $until && $end > $from) {
                return true;
            }
        }
        return false;
    }
}
