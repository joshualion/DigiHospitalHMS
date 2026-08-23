# Laboratory Workflow Architecture

Status: Phase 4A implemented on 2026-08-23.

## Scope

The laboratory module covers catalogue setup, requests, specimens, results, approval, release, reports and append-only amendments. It does not implement radiology, pharmacy, inventory, admissions or device integration.

## Catalogue

Laboratory configuration is hospital-scoped and includes:

- Specimen types.
- Units.
- Laboratory tests.
- Test components/analytes.
- Panels/profiles.
- Optional reference ranges and critical thresholds.
- Optional mapping from lab tests to Phase 3A billable services.

Seeded lab data is structural only and marked for professional configuration. The application does not invent medical ranges or clinical thresholds.

## Request And Billing

Lab requests link to patient, facility, optional department, visit, encounter and ordering clinician. Request and accession numbers are generated through `NumberSequenceService`.

If a lab test maps to a billable service, the workflow creates a draft invoice and adds the invoice line through `InvoiceWorkflowService`. Prices and totals are calculated by the billing backend; the frontend never submits billable totals.

## Specimen Chain Of Custody

Specimens receive label numbers through `NumberSequenceService`. Collection, receipt, rejection and recollection are represented as status transitions with lab events and audit events. Rejected specimens require a reason and remain in history.

## Results

Results support numeric, text, qualitative and comment values. Each result snapshots the component and active reference-range configuration at entry time. Abnormal and critical flags are derived only from configured ranges.

Draft and verified results do not render as approved reports. Reports become visible only after approval or release.

## Approval And Amendments

Result entry, verification, approval and report amendment are separately permissioned. Approval supports separation from the result entry user. Approved results are not overwritten; corrections use append-only report amendments with reason, author and timestamp.

## Audit And Timeline

Lab actions write `lab_events` and application audit events. Approved and released lab reports are added to the patient clinical timeline and encounter payload; draft/unverified results are excluded from final-result visibility.
