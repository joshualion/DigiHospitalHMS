# Radiology Operations Guide

Status: Phase 4B.

## Configuration

Use **Admin > Radiology > Catalogue** to configure modalities and studies. Link a study to a billable service only after the billing catalogue and prices have been approved.

Preparation and safety-screening acknowledgement fields are configuration placeholders. They must be reviewed and maintained by qualified radiology staff.

## Ordering

Authorized clinicians or staff order studies from **Admin > Radiology**. Orders can reference a patient, visit and encounter. Public routes do not expose radiology data.

## Scheduling And Performance

Radiology staff schedule requests by time, room, equipment and assigned staff. The system blocks conflicts for the same room, equipment or assigned staff at the same facility and time.

Workflow actions are:

- Schedule.
- Mark arrived.
- Mark performed with performance notes.
- Move to reporting.
- Cancel with reason.

## Reporting

Authorized radiology staff save draft reports with findings, impression and recommendations. Verification and approval require explicit permissions. Once approved or released, report content is immutable and corrections are recorded as amendments.

Critical findings require communication notes, acknowledgement and escalation history.

## Reports And Timeline

Only approved or released reports are printable and visible in the patient/encounter timeline. Draft and verified reports remain internal work-in-progress.
