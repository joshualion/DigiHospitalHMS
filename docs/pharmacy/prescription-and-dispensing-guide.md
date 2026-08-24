# Prescription And Dispensing Guide

Status: Phase 5B.

Use **Admin > Pharmacy** to manage signed prescriptions, pharmacist review, billing and dispensing.

## Clinical Prescribing

Authorized clinicians create prescription drafts and sign them. The prescribing screen displays configured medicines only; the system does not recommend dose, route, frequency or duration.

Allergies and important alerts appear prominently on the prescription detail page. Staff must use clinical judgment and local policy.

## Pharmacist Review

Pharmacists can:

- Approve a signed prescription.
- Request clarification.
- Reject with reason.
- Authorize a documented substitution.

Reviews do not modify signed prescription content. Clarifications and substitutions are documented as review history.

## Billing

Use the **Bill** action after review. Mapped medicine billable services are added to a draft invoice using backend pricing. Frontend totals are ignored.

## Dispensing

Dispense from an explicit stock location and batch. FEFO suggestions are shown, but the dispenser must select the actual batch. Partial fills are supported until outstanding quantity reaches zero.

Expired, recalled, quarantined, damaged, exhausted or insufficient batches are blocked.

## Corrections And Returns

Patient returns and dispense reversals record new events and stock movements. Do not delete dispense or movement records.

## Deferred

Procurement, supplier purchase orders, automated interaction checking, admissions and insurance workflows are not part of Phase 5B.
