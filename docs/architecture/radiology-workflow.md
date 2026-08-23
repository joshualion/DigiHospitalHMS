# Radiology Workflow Architecture

Status: Phase 4B implemented on 2026-08-23.

Radiology is hospital-scoped and facility-aware. The implemented workflow covers configuration, clinician ordering, scheduling, performance tracking, reporting, approval, secure support attachments and timeline visibility.

## Catalogue

- `radiology_modalities` stores configured modalities per hospital and optional facility.
- `radiology_studies` stores orderable studies and optional links to Phase 3A billable services.
- Preparation and safety-screening acknowledgements are stored as configuration fields only. They must be validated by radiology professionals before operational use.

## Requests And Scheduling

Radiology requests link to patient, facility, visit, clinical encounter and ordering clinician. Request and accession numbers are generated through `NumberSequenceService`.

Scheduling records facility, room, equipment, assigned staff and scheduled time. The workflow prevents same-time conflicts for room, equipment or assigned staff inside the same hospital and facility.

Allowed request states are:

- `ordered`
- `scheduled`
- `arrived`
- `performed`
- `reporting`
- `verified`
- `approved`
- `released`
- `cancelled`

Cancelled studies retain reason and history. Records are not hard-deleted.

## Reporting

Reports store findings, impression, recommendations, reporting radiologist, critical-finding flag and communication notes. Draft reports are editable until approved or released. Approved and released reports become immutable; corrections use append-only amendments.

Report states are:

- `draft`
- `verified`
- `approved`
- `released`

Critical findings can be communicated, acknowledged and escalated with actor and timestamp history.

## Billing

If a radiology study is mapped to a billable service, ordering creates a draft invoice through `InvoiceWorkflowService` and adds server-calculated invoice lines. The frontend does not submit prices or totals.

## Attachments

Attachments are stored on the private `local` disk and served only through authorized controllers. Uploads are quarantined until marked cleared. Only cleared active attachments can be downloaded.

The system accepts PDF, JPEG, PNG and WebP support files. DICOM/PACS ingestion and device integration are outside this phase.

## Audit And Access

Radiology status changes, report actions, critical communications, attachment uploads, clearance, downloads and retirements create radiology events and audit events. Policies enforce hospital scope and explicit permissions.
