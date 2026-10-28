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

namespace bookingextension_examplanning\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The form of the assignment page: one choice of a clerk per participant.
 *
 * Custom data: id (course module), optionid, examiner (name or problem), participants (user id => name),
 * candidates (entity id => name).
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_form extends \moodleform {
    /**
     * Name of the field of a participant.
     *
     * @param int $userid
     * @return string
     */
    public static function field(int $userid): string {
        return 'clerk_' . $userid;
    }

    /**
     * Defines the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('hidden', 'id', (int)$data['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'optionid', (int)$data['optionid']);
        $mform->setType('optionid', PARAM_INT);

        $mform->addElement('static', 'examiner', get_string('examiner', 'bookingextension_examplanning'), s($data['examiner']));

        if (empty($data['participants'])) {
            $text = get_string('assign_noparticipants', 'bookingextension_examplanning');
            $mform->addElement('static', 'noparticipants', '', $text);
            return;
        }
        if (empty($data['candidates'])) {
            $mform->addElement('static', 'nocandidates', '', get_string('assign_nocandidates', 'bookingextension_examplanning'));
        }

        $mform->addElement('header', 'participantsheader', get_string('assign_participants', 'bookingextension_examplanning'));
        $choices = [0 => get_string('assign_noclerk', 'bookingextension_examplanning')] + $data['candidates'];
        foreach ($data['participants'] as $userid => $name) {
            $mform->addElement('select', self::field((int)$userid), s($name), $choices);
            $mform->setType(self::field((int)$userid), PARAM_INT);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * The chosen clerks.
     *
     * @return array|null user id => entity id of the clerk, 0 for none; null if the form was not sent
     */
    public function get_clerks(): ?array {
        $data = $this->get_data();
        if (!$data) {
            return null;
        }
        $clerks = [];
        foreach (array_keys($this->_customdata['participants']) as $userid) {
            $clerks[(int)$userid] = (int)($data->{self::field((int)$userid)} ?? 0);
        }
        return $clerks;
    }
}
