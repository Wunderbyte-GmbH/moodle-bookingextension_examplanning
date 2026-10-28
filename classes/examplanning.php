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

namespace bookingextension_examplanning;

use admin_setting_configcheckbox;
use admin_setting_heading;
use admin_settingpage;
use mod_booking\plugininfo\bookingextension;
use mod_booking\plugininfo\bookingextension_interface;
use bookingextension_examplanning\local\planned_options;
use bookingextension_examplanning\local\planning_access;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/bookingextension/examplanning/lib.php');

/**
 * Exam planning by the trainer: the trainer assigns clerks to the participants, plans the slots and books them.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class examplanning extends bookingextension implements bookingextension_interface {
    /** @var string Setting that switches the extension on. Booking reads it as <name without _>enabled. */
    public const CONFIG_ENABLED = 'examplanningenabled';

    /**
     * Is the extension switched on in the settings?
     *
     * @return bool
     */
    public static function is_globally_enabled(): bool {
        return !empty(get_config('bookingextension_examplanning', self::CONFIG_ENABLED));
    }

    /**
     * Name of the plugin.
     *
     * @return string
     */
    public function get_plugin_name(): string {
        return get_string('pluginname', 'bookingextension_examplanning');
    }

    /**
     * The extension adds a field to the option form when it is switched on.
     *
     * @return bool
     */
    public function contains_option_fields(): bool {
        return self::is_globally_enabled();
    }

    /**
     * The field of the option form.
     *
     * @return array
     */
    public function get_option_fields_info_array(): array {
        return [
            'examplanning' => [
                'name' => 'examplanning',
                'class' => 'bookingextension_examplanning\\option\\fields\\examplanning',
                'id' => MOD_BOOKING_OPTION_FIELD_EXAMPLANNING,
            ],
        ];
    }

    /**
     * The settings page in the folder of Booking.
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig
     * @return void
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig): void {
        $page = new admin_settingpage(
            'bookingextension_examplanning_settings',
            get_string('pluginname', 'bookingextension_examplanning'),
            'moodle/site:config'
        );

        // The navigation tree needs the page only; its settings are built for the full tree alone.
        if ($adminroot->fulltree) {
            $page->add(new admin_setting_heading(
                'bookingextension_examplanning/heading',
                get_string('settings_heading', 'bookingextension_examplanning'),
                get_string('settings_heading_desc', 'bookingextension_examplanning')
            ));
            $page->add(new admin_setting_configcheckbox(
                'bookingextension_examplanning/' . self::CONFIG_ENABLED,
                get_string('settings_enabled', 'bookingextension_examplanning'),
                get_string('settings_enabled_desc', 'bookingextension_examplanning'),
                0
            ));
        }

        $adminroot->add('modbookingfolder', $page);
    }

    /**
     * Nothing is added to the settings of an option yet.
     *
     * @param int $optionid
     * @return object
     */
    public static function load_data_for_settings_singleton(int $optionid): object {
        return (object)[];
    }

    /**
     * Nothing is shown on the option page yet.
     *
     * @param object $settings
     * @return array
     */
    public static function set_template_data_for_optionview(object $settings): array {
        return [];
    }

    /**
     * Link to the assignment page in the menu of a planned option, for whoever may plan its exams.
     *
     * @param object $settings
     * @param mixed $context
     * @return string
     */
    public static function add_options_to_col_actions(object $settings, mixed $context): string {
        global $OUTPUT;

        $optionid = (int)($settings->id ?? 0);
        if (!self::is_globally_enabled() || !planned_options::is_planned($optionid) || !planning_access::can_plan($optionid)) {
            return '';
        }
        $url = new \moodle_url('/mod/booking/bookingextension/examplanning/assign.php', [
            'id' => (int)$settings->cmid,
            'optionid' => $optionid,
        ]);
        return '<div class="dropdown-item">' . \html_writer::link(
            $url,
            $OUTPUT->pix_icon('i/users', '') . get_string('assign_link', 'bookingextension_examplanning')
        ) . '</div>';
    }
}
