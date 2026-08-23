# Clinical Encounters Architecture

Phase 2C adds outpatient vitals and clinical encounters only. It does not add billing, prescriptions, laboratory, radiology, pharmacy or admissions.

## Model

- `clinical_encounters` link hospital, facility, department, patient, visit, optional appointment, optional queue entry and responsible clinician.
- `encounter_vitals` stores append-only measurements with measurement time, recorder and calculated BMI when weight and height are present.
- `encounter_diagnoses` stores clinician-entered diagnosis descriptions with optional external coding system/code and provisional or confirmed status. No diagnostic catalogue is bundled.
- `clinical_encounter_events` stores lifecycle and content-change history.
- `encounter_amendments` stores append-only corrections after signing, with reason, author and timestamp.

## Lifecycle

Allowed encounter states:

- `in_progress`
- `paused`
- `signed`
- `cancelled`

Starting an encounter requires a checked-in visit and an authorized clinician. The workflow locks the visit and rejects a second active encounter for the same visit. Pausing, resuming, signing and cancelling are controlled transitions that create encounter events and audit rows.

Signing updates the visit to `completed`, removes the queue entry and marks the appointment `completed` when present. Queue and appointment changes also receive their own history and audit records. Cancelling returns the visit to `checked_in` and the queue entry to `waiting`.

## Integrity

Signed encounters are immutable. Changes after signing must be recorded as amendments. Cancelled encounters cannot receive new vitals, assessments or diagnoses. Vitals may be recorded by authorized nursing or clinical staff. Assessment, diagnosis, signing and amendment actions require clinician encounter permissions.

## Scope And Access

Policies enforce hospital scope through the same foundation authorization trait used by earlier modules. Public routes never expose clinical records. Viewing protected clinical records creates auditable access through controller-protected routes and hospital-scoped policies.

## Deferred

Orders, prescriptions, billing, laboratory, radiology, pharmacy, admissions, referral workflow automation, normal-range interpretation and ICD catalogue integration remain future phases.
