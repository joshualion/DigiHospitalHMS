# Admissions And Bed Management Architecture

Phase 6A adds inpatient admission requests, bed setup, bed allocation, transfer, discharge and accommodation billing. It reuses the existing hospital/facility scoping, patient, visit, encounter, billing, audit and numbering foundations.

## Model

- `bed_classes` define hospital-scoped accommodation classes and may map to a billable accommodation service.
- `wards`, `ward_rooms` and `beds` model facility bed capacity. Bed states are `available`, `reserved`, `occupied`, `cleaning`, `maintenance`, `blocked` and `inactive`.
- `admissions` link patient, visit, encounter, requesting clinician, attending clinician, department, current ward, current bed and optional invoice.
- `admission_bed_movements` is append-only occupancy history for admission, transfer, discharge and bed-state workflows.
- `admission_events` records workflow transitions and bed state changes.

## Workflow

Admission requests move through `requested`, `approved`, `rejected`, `admitted`, `transferred`, `discharged` and `cancelled`. Admission numbers are allocated only when a patient is actually admitted through `NumberSequenceService`.

Bed allocation and transfer are transactional. The selected bed is locked, the admission is locked, and active admissions are checked before the bed becomes occupied. Previous beds move to `cleaning` after transfer or discharge.

Discharge can be blocked by unresolved administrative clearance. Authorized users with `admissions.discharge.override` may override with a reason, which is audited.

## Billing

Accommodation billing uses the bed class billable service mapping. On discharge, the workflow calculates billable occupancy days from movement start/end timestamps and creates server-side draft invoice lines through `InvoiceWorkflowService`. Prices and service snapshots come from the existing billing backend.

## Deferred

Inpatient progress notes, nursing charts, medication administration, theatre, blood bank, insurance and automated clinical decisions remain outside Phase 6A.
