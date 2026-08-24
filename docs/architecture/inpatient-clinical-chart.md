# Inpatient Clinical Chart Architecture

Phase 6B adds inpatient clinical and nursing documentation linked to an active admission. It reuses the admission, patient, allergy, alert, audit, authorization and patient-activity foundations.

## Model

- `inpatient_charts` are one-to-one with admissions and can only be opened for active `admitted` or `transferred` admissions.
- Progress notes support SOAP fields and configurable note types. Draft notes can be signed; signed notes are immutable.
- Nursing notes, observation charts, intake/output records, care plans, inpatient diagnoses, orders, handovers and discharge summaries are chart-scoped and hospital-scoped.
- `inpatient_amendments` provides append-only corrections for signed notes and signed discharge summaries.
- `inpatient_chart_events` records workflow actions and supports audit reconstruction.

## Workflow

Charting requires an active admission. The service checks admission state before recording new documentation. Signed progress notes and signed discharge summaries cannot be edited; corrections are append-only amendments with author, reason and timestamp.

Orders use controlled states: `draft`, `active`, `acknowledged`, `completed`, `discontinued` and `cancelled`. The ward task list is derived from active and acknowledged orders; it does not invent clinical schedules.

Discharge summaries are drafted from admission context, inpatient diagnoses and signed progress-note content. A clinician must review and sign the summary.

## Boundaries

The module stores staff-entered observations faithfully. It does not calculate clinical scores, normal ranges, medication administration, escalation thresholds, treatment recommendations or automated clinical decisions.
