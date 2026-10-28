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

/**
 * Language strings of the exam planning by the trainer.
 *
 * @package     bookingextension_examplanning
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['assign_intro'] = 'Ordnen Sie jedem Prüfling einen Protokollanten zu. Hier werden noch keine Termine gebucht; die Prüfungen werden im nächsten Schritt geplant.';
$string['assign_link'] = 'Protokollanten zuordnen';
$string['assign_nocandidates'] = 'Es gibt noch keine Protokollanten. Wählen Sie in den Einstellungen der Option Pools oder Einzelpersonen der Protokollanten.';
$string['assign_noclerk'] = 'Noch kein Protokollant';
$string['assign_noparticipants'] = 'Der Kurs hat noch keine Teilnehmenden.';
$string['assign_participants'] = 'Prüflinge und ihre Protokollanten';
$string['assign_saved'] = 'Die Protokollanten wurden gespeichert.';
$string['assign_title'] = 'Protokollanten zuordnen';
$string['bookingrestricted'] = 'Die Prüfungstermine plant Ihr Trainer. Sie sehen Ihren Termin, sobald er gebucht ist.';
$string['clerkpersons'] = 'Einzelpersonen als Protokollanten';
$string['clerkpersons_help'] = 'Personen des Entity Managers, die zusätzlich zu den Mitgliedern der Pools als Protokollant zugeordnet werden können. Nur sichtbar für Benutzer, die die Abwesenheiten aller Entities verwalten dürfen.';
$string['clerkpersons_none'] = 'Keine Einzelpersonen';
$string['clerkpools'] = 'Pools der Protokollanten';
$string['clerkpools_help'] = 'Die Mitglieder dieser Pools des Entity Managers können als Protokollant zugeordnet werden. Der Trainer ist nie sein eigener Protokollant.';
$string['clerkpools_none'] = 'Keine Pools';
$string['examiner'] = 'Prüfer (Trainer)';
$string['examiner_many'] = 'Diese Option hat mehr als einen Trainer. Der Trainer ist der Prüfer, deshalb können die Prüfungen nur mit genau einem Trainer geplant werden.';
$string['examiner_none'] = 'Diese Option hat noch keinen Trainer. Der Trainer ist der Prüfer, deshalb können die Prüfungen erst geplant werden, wenn die Option genau einen Trainer hat.';
$string['examplanning'] = 'Prüfungsplanung durch den Trainer';
$string['examplanningheader'] = 'Prüfungsplanung durch den Trainer';
$string['notplanned'] = 'Die Prüfungen dieser Option werden nicht vom Trainer geplant.';
$string['plannedbytrainer'] = 'Vom Trainer geplant';
$string['plannedbytrainer_help'] = 'Der Trainer ordnet jedem Prüfling einen Protokollanten zu, plant die Slots und bucht sie. Die Teilnehmenden können keinen Slot selbst buchen. Nur für Slot-Buchung.';
$string['plannedbytrainer_label'] = 'Der Trainer plant und bucht die Prüfungen aller Teilnehmenden';
$string['pluginname'] = 'Booking-Erweiterung: Prüfungsplanung durch den Trainer';
$string['privacy:metadata:assign'] = 'Welcher Protokollant welchem Teilnehmer einer geplanten Buchungsoption zugeordnet ist.';
$string['privacy:metadata:assign:clerkentityid'] = 'Die Person in Entities, die Protokollant des Teilnehmers ist.';
$string['privacy:metadata:assign:optionid'] = 'Die Buchungsoption, deren Prüfungen geplant werden.';
$string['privacy:metadata:assign:timemodified'] = 'Wann die Zuordnung zuletzt geändert wurde.';
$string['privacy:metadata:assign:userid'] = 'Der Teilnehmer.';
$string['privacy:metadata:assign:usermodified'] = 'Wer die Zuordnung zuletzt geändert hat.';
$string['settings_enabled'] = 'Prüfungsplanung durch den Trainer aktivieren';
$string['settings_enabled_desc'] = 'Slot-Buchungsoptionen bekommen die Einstellung „Vom Trainer geplant".';
$string['settings_heading'] = 'Prüfungsplanung durch den Trainer';
$string['settings_heading_desc'] = 'Der Trainer einer Slot-Buchungsoption ist der Prüfer. Er ordnet jedem Prüfling einen Protokollanten zu, lässt die Slots planen, passt den Plan an und bucht ihn.';
