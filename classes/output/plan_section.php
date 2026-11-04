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

namespace bookingextension_examplanning\output;

use bookingextension_examplanning\local\plan_store;
use local_entities\entities;

/**
 * The distribution on the page of the exam planning: the button, the state of the newest run and its draft plan.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_section implements \renderable, \templatable {
    /** @var array<int, string> entity id => name, read once per request */
    private array $entitynames = [];

    /**
     * Constructor.
     *
     * @param int $optionid
     * @param \moodle_url $actionurl where the button sends to
     */
    public function __construct(
        /** @var int The option. */
        private int $optionid,
        /** @var \moodle_url Where the button sends to. */
        private \moodle_url $actionurl
    ) {
    }

    /**
     * Exports the context for the template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;

        $plan = plan_store::latest($this->optionid);
        $status = $plan->status ?? null;
        $data = [
            'actionurl' => $this->actionurl->out(false),
            'sesskey' => sesskey(),
            'queued' => $status === plan_store::QUEUED,
            'running' => $status === plan_store::RUNNING,
            'busy' => in_array($status, [plan_store::QUEUED, plan_store::RUNNING], true),
            'error' => $status === plan_store::FAILED ? (string)$plan->error : '',
            'hasplan' => in_array($status, [plan_store::READY, plan_store::BOOKED], true),
            'editable' => $status === plan_store::READY,
            'booked' => $status === plan_store::BOOKED,
            'planid' => (int)($plan->id ?? 0),
            'computed' => in_array($status, [plan_store::READY, plan_store::BOOKED], true)
                ? userdate((int)$plan->timemodified) : '',
            'planned' => [],
            'unplanned' => [],
        ];
        if (!$data['hasplan']) {
            return $data;
        }

        $items = plan_store::items((int)$plan->id);
        $namefields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
        $users = $items ? $DB->get_records_list('user', 'id', array_keys($items), '', 'id, ' . $namefields) : [];
        $timeformat = get_string('strftimedatetimeshort', 'langconfig');
        $endformat = get_string('strftimetime', 'langconfig');

        foreach ($items as $userid => $item) {
            $participant = isset($users[$userid]) ? fullname($users[$userid]) : '';
            if ($item->slotstart !== null) {
                $data['planned'][] = [
                    'userid' => (int)$userid,
                    'time' => userdate((int)$item->slotstart, $timeformat) . '–' . userdate((int)$item->slotend, $endformat),
                    'participant' => $participant,
                    'clerk' => $this->entity_name((int)$item->clerkentityid),
                ];
                continue;
            }
            $names = [
                'examiner' => $this->entity_name((int)$item->examinerentityid),
                'clerk' => $this->entity_name((int)$item->clerkentityid),
            ];
            $data['unplanned'][] = [
                'participant' => $participant,
                'reasons' => array_map(fn(array $reason): string => self::reason_text($reason, $names), $item->reasons),
            ];
        }
        return $data;
    }

    /**
     * The sentence of a reason, e.g. "Clerk Billy Teachy is absent in 8 slots."
     *
     * @param array $reason reason, role, count
     * @param array $names role => name of the person
     * @return string
     */
    private static function reason_text(array $reason, array $names): string {
        $role = (string)($reason['role'] ?? '');
        return get_string('reason_' . $reason['reason'], 'bookingextension_examplanning', (object)[
            'role' => $role !== '' ? get_string('role_' . $role, 'bookingextension_examplanning') : '',
            'name' => $names[$role] ?? '',
            'count' => (int)($reason['count'] ?? 0),
        ]);
    }

    /**
     * The name of an entity.
     *
     * @param int $entityid
     * @return string empty for none
     */
    private function entity_name(int $entityid): string {
        global $DB;
        if ($entityid <= 0) {
            return '';
        }
        if (!isset($this->entitynames[$entityid])) {
            $record = $DB->get_record('local_entities', ['id' => $entityid]);
            $this->entitynames[$entityid] = $record ? entities::get_display_name($record) : '';
        }
        return $this->entitynames[$entityid];
    }
}
