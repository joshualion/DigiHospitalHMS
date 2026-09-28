# Phase 11B: Inpatient & eMAR Production Completion

## Objective

Complete the inpatient lifecycle so Ward Charts and eMAR reflect real active admissions and automatically close when the admission ends.

## Scope

- Close active inpatient charts automatically at discharge.
- Retire pending, delayed and PRN eMAR schedules when the patient is discharged.
- Reconcile legacy/demo active charts whose admissions are no longer active.
- Exclude stale charts and medication schedules from operational worklists.
- Make inpatient chart controls permission-aware for doctors and nurses.
- Preserve signed clinical documentation as append-only history.

## Safety boundary

Clinical notes, administrations and signed records are not hard-deleted through normal production workflows. Corrections remain amendment-based.
