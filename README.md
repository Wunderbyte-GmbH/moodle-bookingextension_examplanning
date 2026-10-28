# Booking extension: Exam planning by the trainer

Subplugin of [mod_booking](https://github.com/Wunderbyte-GmbH/moodle-mod_booking) (`bookingextension_examplanning`).

The trainer of a slot booking option plans the exams of all participants:

1. The trainer is the examiner. The option needs exactly one trainer.
2. The trainer assigns a clerk to every participant, from pools or single persons of
   [local_entities](https://github.com/Wunderbyte-GmbH/moodle-local_entities).
3. A draft plan is calculated in the background, respecting absences, the slots per day of each person and the
   planning period of local_entities.
4. The trainer adjusts the draft (move, swap, shuffle) and confirms it. Only then the bookings are created and the
   clerks are notified.

Participants of a planned option can not book a slot themselves.

## Requirements

- mod_booking with the booking restriction hook (`bookingrestriction_interface`, condition `extensionrestriction`)
- local_entities 2026102802 or newer

## Installation

Copy the folder to `mod/booking/bookingextension/examplanning` and run the Moodle upgrade. Activate it in
Site administration → Plugins → Activity modules → Booking → Exam planning by the trainer.

## License

GNU GPL v3 or later, see `LICENSE`.
