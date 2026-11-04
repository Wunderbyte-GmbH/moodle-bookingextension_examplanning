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
 * The dialog of a change of a participant in the draft plan: move, swap or change the clerk.
 *
 * Targets that do not work are shown greyed out with their reason. A move first asks for the day, then for the slot
 * of that day. The server checks the change again when it is saved.
 *
 * @module     bookingextension_examplanning/plan_change
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';
import Notification from 'core/notification';
import {getStrings} from 'core/str';

const COMPONENT = 'bookingextension_examplanning';

/**
 * Escapes text for HTML.
 *
 * @param {string} text
 * @returns {string}
 */
const escape = (text) => {
    const element = document.createElement('div');
    element.textContent = text;
    return element.innerHTML;
};

/**
 * The radio buttons of the targets.
 *
 * @param {Object[]} targets value, label, current, reason
 * @param {string} nowtext text after the current target
 * @returns {string}
 */
const choices = (targets, nowtext) => targets.map((target, index) => {
    const id = `examplanning-target-${index}`;
    const disabled = target.reason !== '' && !target.current;
    return `<div class="form-check${disabled ? ' text-muted' : ''}">
        <input class="form-check-input" type="radio" name="examplanning-target" id="${id}" value="${target.value}"
            ${target.current ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
        <label class="form-check-label" for="${id}">
            ${escape(target.label)} ${target.current ? escape(nowtext) : ''}
            ${disabled ? `<small class="d-block">${escape(target.reason)}</small>` : ''}
        </label>
    </div>`;
}).join('');

/**
 * The targets grouped by day, in their order.
 *
 * @param {Object[]} targets
 * @returns {Map<string, Object[]>}
 */
const byDay = (targets) => {
    const days = new Map();
    targets.forEach((target) => {
        if (!days.has(target.group)) {
            days.set(target.group, []);
        }
        days.get(target.group).push(target);
    });
    return days;
};

/**
 * The body of a move: the day and the slots of the chosen day.
 *
 * @param {Object[]} targets
 * @param {Object} strings day, nofree, now
 * @returns {{html: string, days: Map}}
 */
const moveBody = (targets, strings) => {
    const days = byDay(targets);
    const free = (list) => list.some((target) => target.reason === '' || target.current);
    const chosen = [...days.keys()].find((day) => days.get(day).some((t) => t.current))
        || [...days.keys()].find((day) => free(days.get(day)))
        || [...days.keys()][0];
    const options = [...days.entries()].map(([day, list]) => {
        const label = free(list) ? day : `${day} (${list[0].reason || strings.nofree})`;
        return `<option value="${escape(day)}" ${day === chosen ? 'selected' : ''} ${free(list) ? '' : 'disabled'}>
            ${escape(label)}</option>`;
    }).join('');
    return {
        days,
        html: `<div class="mb-3">
                <label for="examplanning-day" class="form-label">${escape(strings.day)}</label>
                <select id="examplanning-day" class="form-select custom-select">${options}</select>
            </div>
            <div data-region="examplanning-slots">${choices(days.get(chosen) || [], strings.now)}</div>`,
    };
};

/**
 * Opens the dialog of a change.
 *
 * @param {number} planid
 * @param {number} userid
 * @param {string} kind move, swap or clerk
 * @param {string} participant name of the participant
 */
const openDialog = async(planid, userid, kind, participant) => {
    const [title, save, day, nofree, now, none, saved] = await getStrings([
        {key: `change_${kind}`, component: COMPONENT},
        {key: 'change_save', component: COMPONENT},
        {key: 'change_day', component: COMPONENT},
        {key: 'change_nofree', component: COMPONENT},
        {key: 'change_current', component: COMPONENT},
        {key: 'change_notargets', component: COMPONENT},
        {key: 'change_saved', component: COMPONENT},
    ]);
    const response = await Ajax.call([{
        methodname: 'bookingextension_examplanning_get_targets',
        args: {planid, userid, kind},
    }])[0];

    let body;
    let days = null;
    if (!response.targets.length) {
        body = `<p>${escape(none)}</p>`;
    } else if (kind === 'move') {
        ({html: body, days} = moveBody(response.targets, {day, nofree, now}));
    } else {
        body = choices(response.targets, now);
    }

    const modal = await ModalSaveCancel.create({
        title: `${participant}: ${title}`,
        body,
        buttons: {save},
        show: true,
        removeOnClose: true,
    });
    const root = modal.getRoot()[0];

    if (days) {
        root.addEventListener('change', (event) => {
            if (event.target.id === 'examplanning-day') {
                root.querySelector('[data-region="examplanning-slots"]').innerHTML =
                    choices(days.get(event.target.value) || [], now);
            }
        });
    }

    modal.getRoot().on(ModalEvents.save, (event) => {
        event.preventDefault();
        const checked = root.querySelector('input[name="examplanning-target"]:checked');
        const target = checked ? response.targets.find((t) => String(t.value) === checked.value) : null;
        if (!target || target.current) {
            modal.destroy();
            return;
        }
        Ajax.call([{
            methodname: 'bookingextension_examplanning_change_plan',
            args: {planid, userid, kind, value: target.value},
        }])[0].then(() => {
            modal.destroy();
            Notification.addNotification({message: saved, type: 'success'});
            window.location.reload();
            return null;
        }).catch(Notification.exception);
    });
};

/**
 * Opens the dialog when an entry of the menu of a participant is clicked.
 */
export const init = () => {
    const section = document.querySelector('[data-region="plan-section"]');
    if (!section || !section.dataset.planid) {
        return;
    }
    section.addEventListener('click', (event) => {
        const link = event.target.closest('[data-action="plan-change"]');
        if (!link) {
            return;
        }
        event.preventDefault();
        openDialog(
            Number(section.dataset.planid),
            Number(link.dataset.userid),
            link.dataset.kind,
            link.dataset.participant
        ).catch(Notification.exception);
    });
};
