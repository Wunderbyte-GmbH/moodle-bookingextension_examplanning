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

$string['assign_intro'] = 'Ordnen Sie jedem Prüfling einen Protokollanten zu und speichern Sie. Danach plant „Verteilen“ die Prüfungen unten als Vorab-Plan; gebucht wird noch nichts.';
$string['assign_link'] = 'Prüfungsplanung';
$string['assign_nocandidates'] = 'Es gibt noch keine Protokollanten. Wählen Sie in den Einstellungen der Option Pools oder Einzelpersonen der Protokollanten.';
$string['assign_noclerk'] = 'Noch kein Protokollant';
$string['assign_noparticipants'] = 'Der Kurs hat noch keine Teilnehmenden.';
$string['assign_participants'] = 'Prüflinge und ihre Protokollanten';
$string['assign_saved'] = 'Die Protokollanten wurden gespeichert.';
$string['assign_title'] = 'Prüfungsplanung';
$string['bookingrestricted'] = 'Die Prüfungstermine plant Ihr Trainer. Sie sehen Ihren Termin, sobald er gebucht ist.';
$string['change_actions'] = 'Ändern';
$string['change_clerk'] = 'Protokollant ändern…';
$string['change_current'] = '(jetzt)';
$string['change_day'] = 'Tag';
$string['change_invalid'] = 'Die Änderung ist nicht möglich: {$a}';
$string['change_move'] = 'Verschieben nach…';
$string['change_noclerk'] = 'Diese Person kann nicht Protokollant der Option sein.';
$string['change_nofree'] = 'kein freier Slot';
$string['change_noslot'] = 'Diesen Slot gibt es nicht.';
$string['change_notargets'] = 'Es gibt nichts zur Auswahl.';
$string['change_notready'] = 'Der Vorab-Plan kann gerade nicht geändert werden.';
$string['change_save'] = 'Speichern';
$string['change_saved'] = 'Der Vorab-Plan ist geändert.';
$string['change_swap'] = 'Tauschen mit…';
$string['clerkpersons'] = 'Einzelpersonen als Protokollanten';
$string['clerkpersons_help'] = 'Personen des Entity Managers, die zusätzlich zu den Mitgliedern der Pools als Protokollant zugeordnet werden können. Nur sichtbar für Benutzer, die die Abwesenheiten aller Entities verwalten dürfen.';
$string['clerkpersons_none'] = 'Keine Einzelpersonen';
$string['clerkpools'] = 'Pools der Protokollanten';
$string['clerkpools_help'] = 'Die Mitglieder dieser Pools des Entity Managers können als Protokollant zugeordnet werden. Der Trainer ist nie sein eigener Protokollant.';
$string['clerkpools_none'] = 'Keine Pools';
$string['conditionselectclerkinbo'] = 'Protokollant der Prüfung';
$string['conditionselectclerkinbo_desc'] = 'Die Protokollanten der gebuchten Prüfungen der Option. Reagiert die Regel auf ein Ereignis zu einem Prüfling, nur dessen Protokollant.';
$string['confirm_bookingfailed'] = 'Die Buchung von {$a} ist fehlgeschlagen. Es wurde nichts gebucht.';
$string['confirm_busy'] = 'Der Vorab-Plan wird gerade gebucht.';
$string['confirm_empty'] = 'Der Vorab-Plan hat keine Prüflinge.';
$string['confirm_problems'] = 'Es wurde nichts gebucht, weil sich etwas geändert hat: {$a}';
$string['confirm_unplanned'] = 'Es wurde nichts gebucht, weil diese Prüflinge nicht planbar sind: {$a}. Klären Sie das mit den Protokollanten und verteilen Sie neu.';
$string['examiner'] = 'Prüfer (Trainer)';
$string['examiner_many'] = 'Diese Option hat mehr als einen Trainer. Der Trainer ist der Prüfer, deshalb können die Prüfungen nur mit genau einem Trainer geplant werden.';
$string['examiner_none'] = 'Diese Option hat noch keinen Trainer. Der Trainer ist der Prüfer, deshalb können die Prüfungen erst geplant werden, wenn die Option genau einen Trainer hat.';
$string['examplanning'] = 'Prüfungsplanung durch den Trainer';
$string['examplanningheader'] = 'Prüfungsplanung durch den Trainer';
$string['messageprovider:planready'] = 'Der Vorab-Plan der Prüfungen ist berechnet';
$string['myexam'] = 'Ihre Prüfung';
$string['myexam_text'] = '{$a->time} · Prüfer: {$a->examiner} · Protokollant: {$a->clerk}';
$string['notplanned'] = 'Die Prüfungen dieser Option werden nicht vom Trainer geplant.';
$string['occupation'] = 'Teilnehmer: {$a->participant} · Prüfung: {$a->option} · Prüfer: {$a->examiner} · Protokollant: {$a->clerk}';
$string['plan_allplanned'] = 'Alle Prüflinge sind geplant.';
$string['plan_booked'] = 'Gebuchter Plan vom {$a}';
$string['plan_clerk'] = 'Protokollant';
$string['plan_computed'] = 'Vorab-Plan vom {$a}';
$string['plan_confirm'] = 'Bestätigen und buchen';
$string['plan_confirm_help'] = 'Prüft alles noch einmal und bucht jeden Prüfling in seinen Slot. Nur wenn alle geplant sind und sich nichts geändert hat.';
$string['plan_confirmed'] = '{$a} Prüfungen sind gebucht.';
$string['plan_confirmq'] = 'Alle Prüfungen des Vorab-Plans jetzt buchen?';
$string['plan_distribute'] = 'Verteilen';
$string['plan_distribute_help'] = 'Plant jeden Prüfling mit Protokollant in den frühesten Slot, in dem Prüfer und Protokollant frei sind. Gebucht wird noch nichts.';
$string['plan_failed'] = 'Die Verteilung konnte nicht berechnet werden: {$a}';
$string['plan_heading'] = 'Verteilung';
$string['plan_none'] = 'Es gibt noch keinen Vorab-Plan.';
$string['plan_participant'] = 'Prüfling';
$string['plan_queued'] = 'Die Verteilung wird im Hintergrund berechnet. Sie bekommen eine Nachricht, wenn sie fertig ist.';
$string['plan_running'] = 'Die Verteilung wird berechnet…';
$string['plan_shuffle'] = 'Neu mischen';
$string['plan_shuffle_help'] = 'Verteilt noch einmal in anderer Reihenfolge. Wer welchen Slot bekommt, kann sich ändern.';
$string['plan_time'] = 'Zeit';
$string['plan_unplanned'] = 'Nicht planbar';
$string['plannedbytrainer'] = 'Vom Trainer geplant';
$string['plannedbytrainer_help'] = 'Der Trainer ordnet jedem Prüfling einen Protokollanten zu, plant die Slots und bucht sie. Die Teilnehmenden können keinen Slot selbst buchen. Nur für Slot-Buchung.';
$string['plannedbytrainer_label'] = 'Der Trainer plant und bucht die Prüfungen aller Teilnehmenden';
$string['planready_body'] = 'Der Vorab-Plan für {$a->option} ist berechnet: {$a->planned} geplant, {$a->unplanned} nicht planbar. {$a->url}';
$string['planready_failed'] = 'Der Vorab-Plan für {$a->option} konnte nicht berechnet werden: {$a->error}';
$string['planready_subject'] = 'Vorab-Plan für {$a} ist fertig';
$string['pluginname'] = 'Booking-Erweiterung: Prüfungsplanung durch den Trainer';
$string['privacy:metadata:assign'] = 'Welcher Protokollant welchem Teilnehmer einer geplanten Buchungsoption zugeordnet ist.';
$string['privacy:metadata:assign:clerkentityid'] = 'Die Person in Entities, die Protokollant des Teilnehmers ist.';
$string['privacy:metadata:assign:optionid'] = 'Die Buchungsoption, deren Prüfungen geplant werden.';
$string['privacy:metadata:assign:timemodified'] = 'Wann die Zuordnung zuletzt geändert wurde.';
$string['privacy:metadata:assign:userid'] = 'Der Teilnehmer.';
$string['privacy:metadata:assign:usermodified'] = 'Wer die Zuordnung zuletzt geändert hat.';
$string['privacy:metadata:plan'] = 'Ein Lauf der Verteilung der Prüfungen einer Option.';
$string['privacy:metadata:plan:optionid'] = 'Die Buchungsoption.';
$string['privacy:metadata:plan:timecreated'] = 'Wann der Lauf gestartet wurde.';
$string['privacy:metadata:plan:usermodified'] = 'Wer den Lauf gestartet hat.';
$string['privacy:metadata:planitem'] = 'Ein Prüfling in einem Vorab-Plan.';
$string['privacy:metadata:planitem:clerkentityid'] = 'Der Protokollant des Prüflings im Vorab-Plan.';
$string['privacy:metadata:planitem:slotstart'] = 'Wann die Prüfung des Prüflings geplant ist.';
$string['privacy:metadata:planitem:userid'] = 'Der Prüfling.';
$string['privacy:metadata:staff'] = 'Wer bei einer gebuchten Prüfung eines Prüflings Prüfer und wer Protokollant ist.';
$string['privacy:metadata:staff:entityid'] = 'Die Person in Entities, die Prüfer oder Protokollant ist.';
$string['privacy:metadata:staff:optionid'] = 'Die Buchungsoption der Prüfung.';
$string['privacy:metadata:staff:role'] = 'Prüfer oder Protokollant.';
$string['privacy:metadata:staff:slotstart'] = 'Wann die Prüfung beginnt.';
$string['privacy:metadata:staff:userid'] = 'Der Prüfling.';
$string['privacy:metadata:staff:usermodified'] = 'Wer die Prüfung gebucht hat.';
$string['problem_absent'] = '{$a->role} {$a->name} ist abwesend.';
$string['problem_dailylimit'] = '{$a->role} {$a->name} hat das Tageskontingent erreicht.';
$string['problem_occupied'] = '{$a->role} {$a->name} ist belegt.';
$string['problem_outsideperiod'] = 'Der Slot liegt außerhalb des Planungszeitraums.';
$string['problem_swap'] = 'Für {$a->participant}: {$a->problem}';
$string['problem_teaching'] = '{$a->role} {$a->name} unterrichtet in einer anderen Option.';
$string['reason_absent'] = '{$a->role} {$a->name} ist in {$a->count} Slots abwesend.';
$string['reason_dailylimit'] = '{$a->role} {$a->name} hat in {$a->count} Slots das Tageskontingent erreicht.';
$string['reason_noclerk'] = 'Kein Protokollant zugeordnet.';
$string['reason_noexaminer'] = 'Die Option hat nicht genau einen Trainer.';
$string['reason_noslots'] = 'Die Option hat keine Slots im Planungszeitraum.';
$string['reason_occupied'] = '{$a->role} {$a->name} ist in {$a->count} Slots belegt.';
$string['reason_outsideperiod'] = '{$a->count} Slots liegen außerhalb des Planungszeitraums.';
$string['reason_teaching'] = '{$a->role} {$a->name} unterrichtet in {$a->count} Slots.';
$string['role_clerk'] = 'Protokollant';
$string['role_examiner'] = 'Prüfer';
$string['rollback'] = 'Plan zurücknehmen';
$string['rollback_done'] = '{$a} Buchungen sind storniert. Der Plan ist wieder ein Vorab-Plan.';
$string['rollback_failed'] = 'Die Buchung von {$a} konnte nicht storniert werden. Es wurde nichts storniert.';
$string['rollback_help'] = 'Storniert alle Buchungen dieses Plans. Danach ist er wieder ein Vorab-Plan: ändern, erneut bestätigen oder neu verteilen. Nur vor der ersten Prüfung.';
$string['rollback_notbooked'] = 'Der Plan ist nicht gebucht.';
$string['rollback_started'] = 'Der Plan kann nicht mehr zurückgenommen werden, die erste Prüfung hat begonnen.';
$string['rollbackq'] = 'Alle Buchungen dieses Plans stornieren?';
$string['selectclerkinbo'] = 'Protokollant der Prüfung';
$string['settings_enabled'] = 'Prüfungsplanung durch den Trainer aktivieren';
$string['settings_enabled_desc'] = 'Slot-Buchungsoptionen bekommen die Einstellung „Vom Trainer geplant".';
$string['settings_heading'] = 'Prüfungsplanung durch den Trainer';
$string['settings_heading_desc'] = 'Der Trainer einer Slot-Buchungsoption ist der Prüfer. Er ordnet jedem Prüfling einen Protokollanten zu, lässt die Slots planen, passt den Plan an und bucht ihn.';
