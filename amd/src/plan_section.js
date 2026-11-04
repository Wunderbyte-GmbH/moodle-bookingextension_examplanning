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

/**
 * While a run of the distribution is computed, the page loads itself again now and then, so the plan shows when it is
 * ready.
 *
 * @module     bookingextension_examplanning/plan_section
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init as initChange} from 'bookingextension_examplanning/plan_change';

/** @var {number} Seconds between two looks. */
const INTERVAL = 5;

/**
 * Starts the menus and the question before booking, and looks again and again if a run is being computed.
 */
export const init = () => {
    initChange();
    const section = document.querySelector('[data-region="plan-section"]');
    const confirmform = section ? section.querySelector('[data-action="plan-confirm"]') : null;
    if (confirmform) {
        // Booking can not be undone with one click, so it asks first.
        confirmform.addEventListener('submit', (event) => {
            if (!window.confirm(confirmform.dataset.question)) {
                event.preventDefault();
            }
        });
    }
    if (!section || !section.dataset.busy) {
        return;
    }
    window.setTimeout(() => window.location.reload(), INTERVAL * 1000);
};
