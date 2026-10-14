# Phase 4 — Hospital workflow acceptance baseline

Date: 2026-10-14. This gate must be run on synthetic data. No live clinical records should be used for CI.

## Cross-module acceptance

The `Phase4PatientJourneyAcceptanceTest` exercises an end-to-end, same-hospital identity linkage for registration -> appointment/check-in -> queue -> encounter -> signed consultation -> invoice draft. It asserts consistent patient/visit/encounter references, terminal states and key audit events. This is one complete technical happy-path **subset**, not proof of complete clinical readiness.

Existing dedicated automated suites establish the following additional checkpoints:

| Step | Automated suite | Essential exceptions and controls |
| --- | --- | --- |
| Registration/identity | Phase2APatientIdentityTest | Duplicate detection, identifier privacy, archive, cross-hospital IDOR |
| Booking/check-in | Phase2BAppointmentsQueuesTest | Conflicting bookings, reschedule/cancel/no-show, queue transition |
| Encounter | Phase2CClinicalEncountersTest | Vitals/diagnosis, signing immutability, amendment, wrong-clinician access |
| Invoicing and payments | Phase3AServiceCatalogueInvoicingTest; Phase3BPaymentsReconciliationTest | Server pricing, immutable issued invoices, voids, reconciliation |
| Laboratory | Phase4ALaboratoryWorkflowTest | Specimen chain, result review, permissions |
| Radiology | Phase4BRadiologyWorkflowTest | Order, report and authorized result handling |
| Pharmacy | Phase5BPrescribingDispensingTest | Prescribing, dispensing, stock and unsafe actions |
| Admission/bed/discharge | Phase6AAdmissionsBedManagementTest; Phase11AAdmissionsProductionCompletionTest | Double-occupancy denial, transfers, clearance/discharge override history |
| Inpatient chart and medication | Phase6BInpatientClinicalChartTest; Phase6CInpatientMedicationAdministrationTest; Phase11BInpatientProductionCompletionTest | Signed medication/chart corrections, dose administration exceptions |
| Blood bank | Phase7ABloodBankFoundationTest; Phase7BPatientBloodRequestTest; Phase7CTransfusionMonitoringTest | Blood compatibility, issue and adverse-event controls |
| Payer/claims | Phase8APayerCoverageTest; Phase8BInsuranceClaimsTest | Coverage restrictions, claims status changes |
| Operational reporting | Phase9AReportingTest | Data integrity and permission-scoped reporting |

## Required staging-based clinical acceptance before sign-off

1. Receptionist registers a synthetic patient, detects a deliberate duplicate, confirms consent/privacy details and books a clinician.
2. Nurse checks in the patient; clinician reviews allergies and records assessment, vitals and a signed encounter. Confirm unsigned/signed corrections.
3. Laboratory and radiology staff process test orders with sample/requisition identifiers; compare final results to encounter records and verify unauthorized staff cannot change them.
4. Prescriber orders medication; pharmacy checks formulary, allergies, stock, dispensing, return/cancellation and patient linkage.
5. Cashier produces and settles an invoice, tests partial/refunded/voided workflows and reconciles with the encounter.
6. Admission coordinator allocates a bed, transfers the patient, completes inpatient documentation and medication administration; then tests clinical/financial clearance and discharge.
7. Hospital auditor verifies immutable events, corrections and cross-hospital/facility denial for every step.
8. Validate exceptional paths: appointment cancellation, wrong-patient ID, laboratory specimen rejection, medication refusal, stock shortage, duplicate bed booking, unpaid discharge, report amendment.
9. Obtain dated scenario-based approval from a receptionist, nurse, clinician, laboratory, radiology, pharmacist, cashier, records/privacy officer and hospital administrator.

## Exit criteria

- All automated tests pass on main with no security advisories in Composer audit.
- CI proves technical linkage without relying on frontend screenshots alone.
- A real staging acceptance run with **synthetic records** closes each scenario above; no unresolved critical clinical or financial defects.
- Signing, read-back, negative testing and user acceptance are recorded as auditable evidence.
- A release cannot be described as clinically certified solely because these CI suites pass.

## Known scope boundary

This change introduces an integrated technical acceptance test and a traceable hospital-wide UAT checklist. It does **not** rewrite clinical workflows, certify patient safety, deploy to hospital installations or substitute a clinician-supervised staging trial.
