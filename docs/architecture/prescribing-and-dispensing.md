# Prescribing And Dispensing Architecture

Status: Phase 5B implemented on 2026-08-24.

Phase 5B adds prescription, pharmacist review, medicine billing and dispensing workflows on top of the Phase 5A inventory ledger.

## Prescriptions

Prescriptions are scoped to hospital and facility and link to patient, clinical encounter and prescribing clinician. Prescription numbers come from `NumberSequenceService`.

Prescription states are:

- `draft`
- `signed`
- `discontinued`
- `cancelled`
- `completed`

Signed prescriptions are immutable through the active workflow. Corrections use append-only amendments or discontinuation/cancellation with reason.

## Prescription Items

Items snapshot medicine name and store dose, unit, route, frequency, duration, quantity, instructions, indication and PRN instructions. The system does not calculate or recommend dosage, interactions, contraindications or clinical rules.

## Pharmacist Review

Pharmacists can approve, request clarification, reject with reason or document an authorized substitution. Review records are append-only and do not silently change signed prescription item content.

Dispensing requires a signed prescription and an approving/substitution review.

## Billing

Medicine billing uses the `billable_service_id` mapping on inventory items. The prescription billing workflow creates a draft invoice and adds server-calculated invoice lines through `InvoiceWorkflowService`, preserving normal billing price snapshots.

## Dispensing

Dispensing requires explicit location and batch selection. The workflow checks:

- Outstanding prescribed quantity.
- Batch belongs to the prescribed medicine.
- Batch is available and not expired.
- Batch has sufficient stock at the selected location.

Stock deduction posts an immutable `dispense` stock movement through `InventoryLedgerService`. Returns post a return movement. Reversals post controlled reversal movements; history is retained.

## Deferred

Procurement, suppliers, purchase orders, automated interaction engines, insurance, admissions and generated clinical advice remain outside this phase.
