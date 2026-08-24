# Inpatient Medication Administration Architecture

Phase 6C adds an inpatient eMAR on top of admissions, inpatient charts, prescriptions, pharmacist review, dispensing, allergies, alerts and audit.

## Model

- Prescription items now carry explicit eMAR metadata: order type, scheduled times, start time and end time.
- `emar_schedules` stores generated scheduled doses for active inpatient charts.
- `emar_administrations` records each administration outcome with patient/medicine/dose/route/timing confirmation, administrator, actual time and dispensed batch traceability.
- `emar_amendments` records append-only corrections to administration records.
- `emar_events` records schedule generation, administration and correction audit events.

## Eligibility

Only prescription items from signed or fully dispensed prescriptions with pharmacist approval or authorized substitution can be scheduled. Medication administration is blocked when the order/prescription is discontinued or cancelled, the item is expired, the inpatient chart is closed, or the admission is discharged.

The existing Phase 5B `completed` prescription state means the prescription was fully dispensed. eMAR therefore allows completed prescriptions while still blocking discontinued/cancelled prescriptions and completed/discontinued/cancelled prescription items.

## Scheduling

Schedules are generated from clinician-entered metadata only:

- `regular`: uses the configured `scheduled_times` for the start date.
- `once` and `stat`: use the configured start time.
- `prn`: creates a PRN-available schedule and requires an indication at administration.

The system does not calculate dosage, interactions, normal ranges or clinical recommendations.

## Inventory Boundary

Dispensing already deducts inventory. eMAR links an administered dose to the selected or next available dispense and batch, then decrements only the remaining dispensed quantity available for administration. It does not post new stock movements.
